<?php

/** WP03: immutable source snapshots, explicit review, per-row atomic/idempotent import. */
class CustomerImportService
{
    const VERSION='wp03-v1';
    private static function authorize($action)
    {
        ActionAccess::requireAction('customerimport',$action);
        if (!ActionAccess::isAll()) { throw new CHttpException(403,'Unmapped legacy sources require All scope.'); }
        if (empty(Yii::app()->params['mvp']['legacyImportEnabled']) || !(Yii::app()->db instanceof IsolatedTestDbConnection)) {
            throw new CHttpException(503,'Legacy import is staged for isolated test databases only.');
        }
        $db=Yii::app()->db;
        if ($db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CHttpException(503,'Import database mismatch.'); }
    }
    public static function lockName()
    {
        return 'crm_import_'.substr(hash('sha256',Yii::app()->db->createCommand('SELECT DATABASE()')->queryScalar()),0,40);
    }
    private static function locked($callback)
    {
        $db=Yii::app()->db; $key=self::lockName();
        if ((int)$db->createCommand('SELECT GET_LOCK(:name,0)')->queryScalar(array(':name'=>$key))!==1) { throw new CHttpException(409,'Another import is running; retry later.'); }
        try { return $callback(); }
        finally { $db->createCommand('SELECT RELEASE_LOCK(:name)')->queryScalar(array(':name'=>$key)); }
    }
    private static function atomic($callback)
    {
        $db=Yii::app()->db; $outer=$db->getCurrentTransaction(); $owns=!$outer || !$outer->active;
        $savepoint='import_'.bin2hex(random_bytes(8));
        if ($owns) { $tx=$db->beginTransaction(); } else { $db->createCommand('SAVEPOINT '.$savepoint)->execute(); }
        try {
            $value=$callback();
            if ($owns) { $tx->commit(); } else { $db->createCommand('RELEASE SAVEPOINT '.$savepoint)->execute(); }
            return $value;
        } catch (Exception $e) {
            if ($owns) { $tx->rollback(); } else { $db->createCommand('ROLLBACK TO SAVEPOINT '.$savepoint)->execute(); $db->createCommand('RELEASE SAVEPOINT '.$savepoint)->execute(); }
            throw $e;
        }
    }
    private static function id($value)
    {
        if (!is_scalar($value) || !ctype_digit((string)$value) || (float)$value<1 || (float)$value>4294967295) { throw new CHttpException(400,'A positive integer ID/version is required.'); }
        return (int)$value;
    }
    private static function batch($id,$lock=false)
    {
        $id=self::id($id);
        $row=Yii::app()->db->createCommand('SELECT * FROM crm_import_batch WHERE id=:id'.($lock?' FOR UPDATE':''))->queryRow(true,array(':id'=>$id));
        if (!$row || !in_array($row['source_type'],array('legacy_contact','legacy_report'),true) || $row['mapping_version']!==self::VERSION) { throw new CHttpException(404,'Legacy import batch not found.'); }
        return $row;
    }
    private static function type(array $batch) { return substr($batch['source_type'],7); }
    private static function rows($id,$lock=false)
    {
        return Yii::app()->db->createCommand('SELECT * FROM crm_import_row WHERE batch_id=:id ORDER BY row_no'.($lock?' FOR UPDATE':''))->queryAll(true,array(':id'=>$id));
    }
    private static function terminal($row) { return in_array($row['result_state'],array('created','linked','skipped'),true); }
    private static function audit($batchId,$entity,$id,$event,$before,$after,$reason='')
    {
        $m=new AuditEvent; $m->entity_type=$entity; $m->entity_id=(string)$id; $m->event_type=$event;
        $m->actor_user_id=Yii::app()->user->id; $m->import_batch_id=$batchId; $m->request_id=bin2hex(random_bytes(16));
        $m->before_json=$before===null?null:LegacyImportSource::json($before); $m->after_json=LegacyImportSource::json($after); $m->reason=$reason;
        if (!$m->save()) { throw new LegacyImportException('AUDIT_FAILED'); }
    }
    private static function save($class,array $values)
    {
        $m=new $class; $m->setAuditActorId(Yii::app()->user->id); $m->setAttributes($values,false);
        if (!$m->save()) { throw new LegacyImportException('VALIDATION_FAILED',LegacyImportSource::json($m->errors)); }
        return $m;
    }
    public static function preview($type,array $ids)
    {
        self::authorize('preview'); $table=LegacyImportSource::table($type);
        $ids=array_values(array_unique(array_map(array(__CLASS__,'id'),$ids))); sort($ids,SORT_NUMERIC);
        if (!$ids || count($ids)>500) { throw new CHttpException(400,'Select between 1 and 500 source records.'); }
        return self::locked(function()use($type,$table,$ids){ return self::atomic(function()use($type,$table,$ids){
            $db=Yii::app()->db;
            $rawRows=$db->createCommand('SELECT * FROM '.$table.' WHERE id IN ('.implode(',',$ids).') ORDER BY id')->queryAll();
            if (count($rawRows)!==count($ids)) { throw new CHttpException(404,'One or more source records no longer exist.'); }
            $identity=array(); $names=array();
            foreach ($rawRows as $i=>$raw) {
                $identity[]=array((string)$raw['id'],LegacyImportSource::hash($raw,$type));
                $values=LegacyImportSource::values($raw,$type); $name=LegacyImportSource::normalize($values['account']['name']);
                $names[$name][]=$i+1;
            }
            $key=hash('sha256',LegacyImportSource::json($identity));
            $old=CustomerImportBatch::model()->findByAttributes(array('source_type'=>'legacy_'.$type,'source_key'=>$key,'mapping_version'=>self::VERSION));
            if ($old) { return (int)$old->id; }
            $batch=self::save('CustomerImportBatch',array('source_type'=>'legacy_'.$type,'source_key'=>$key,'mapping_version'=>self::VERSION));
            foreach ($rawRows as $i=>$raw) {
                $name=LegacyImportSource::normalize(LegacyImportSource::values($raw,$type)['account']['name']);
                self::save('CustomerImportRow',array('batch_id'=>$batch->id,'row_no'=>$i+1,'source_table'=>$table,'source_id'=>(string)$raw['id'],
                    'raw_json'=>LegacyImportSource::json($raw),'source_hash'=>LegacyImportSource::hash($raw,$type),
                    'proposed_json'=>LegacyImportSource::json(LegacyImportSource::proposal($raw,$type,$names[$name]))));
            }
            self::audit($batch->id,'import_batch',$batch->id,'previewed',null,array('type'=>$type,'source_key'=>$key,'rows'=>count($rawRows)));
            self::refreshCounts($batch->id); return (int)$batch->id;
        }); });
    }
    private static function fields($value,array $allowed)
    {
        if (!is_array($value) || array_diff(array_keys($value),$allowed)) { throw new CHttpException(400,'Unsupported field overrides.'); }
        foreach ($value as $v) { if ($v!==null && !is_scalar($v)) { throw new CHttpException(400,'Field overrides must be scalar.'); } }
        return $value;
    }
    private static function decision(array $input,$rowNo,$type)
    {
        $allowed=array('mode','reason','account_kind','account_id','account_row_no','account_fields','contact_kind','contact_person_id','contact_row_no','contact_fields','hotels','markets','allow_duplicate_account','allow_duplicate_contact','remember_alias');
        if (array_diff(array_keys($input),$allowed) || !isset($input['mode'],$input['reason']) || !is_string($input['reason']) || !trim($input['reason']) || strlen($input['reason'])>4000) { throw new CHttpException(400,'A reason and supported mapping fields are required.'); }
        if (!in_array($input['mode'],array('map','skip','existing'),true)) { throw new CHttpException(400,'Invalid mapping mode.'); }
        $d=array('mode'=>$input['mode'],'reason'=>trim($input['reason']));
        if ($d['mode']!=='map') { return $d; }
        $d['account_kind']=isset($input['account_kind'])?$input['account_kind']:'create';
        if (!in_array($d['account_kind'],array('create','link','row'),true)) { throw new CHttpException(400,'Invalid account mapping.'); }
        if ($d['account_kind']==='link') {
            $d['account_id']=self::id(isset($input['account_id'])?$input['account_id']:null);
            $account=Account::model()->findByPk($d['account_id']);
            if (!$account || $account->status!=='active') { throw new CHttpException(400,'Active destination account is required.'); }
            $d['account_version']=(int)$account->lock_version;
        }
        if ($d['account_kind']==='row') { $d['account_row_no']=self::id(isset($input['account_row_no'])?$input['account_row_no']:null); if ($d['account_row_no']>=$rowNo) { throw new CHttpException(400,'Reuse must point to an earlier row of the same batch.'); } }
        $d['account_fields']=self::fields(isset($input['account_fields'])?$input['account_fields']:array(),array('name','segment','biz_scope','website','phone','email','building','address_1','address_2','address_3','city','country','source','fit_series','dmc','notes'));
        if ($d['account_kind']!=='create' && $d['account_fields']) { throw new CHttpException(400,'Linking must not overwrite existing account fields.'); }
        $d['contact_kind']=isset($input['contact_kind'])?$input['contact_kind']:'none';
        if (!in_array($d['contact_kind'],array('none','create','link','row'),true)) { throw new CHttpException(400,'Invalid contact mapping.'); }
        if ($d['contact_kind']==='link') {
            $d['contact_person_id']=self::id(isset($input['contact_person_id'])?$input['contact_person_id']:null);
            $person=ContactPerson::model()->findByPk($d['contact_person_id']);
            if (!$person || $person->status!=='active') { throw new CHttpException(400,'Active destination contact is required.'); }
            $d['contact_version']=(int)$person->lock_version;
        }
        if ($d['contact_kind']==='row') { $d['contact_row_no']=self::id(isset($input['contact_row_no'])?$input['contact_row_no']:null); if ($d['contact_row_no']>=$rowNo) { throw new CHttpException(400,'Contact reuse must point to an earlier row.'); } }
        $d['contact_fields']=self::fields(isset($input['contact_fields'])?$input['contact_fields']:array(),array('title','first_name','last_name','position','email','phone','mobile','dob','contact_role','notes'));
        if ($d['contact_kind']!=='create' && $d['contact_fields']) { throw new CHttpException(400,'Linking must not overwrite existing contact fields.'); }
        if (empty($input['hotels']) || !is_array($input['hotels']) || count($input['hotels'])>20 || ($type==='report' && count($input['hotels'])!==1)) { throw new CHttpException(400,'Select Hotel assignments; each Report requires exactly one Hotel.'); }
        $d['hotels']=array(); $seen=array();
        foreach ($input['hotels'] as $h) {
            if (!is_array($h) || !isset($h['hotel_id']) || array_diff(array_keys($h),array('hotel_id','owner_user_id'))) { throw new CHttpException(400,'Invalid Hotel assignment.'); }
            $id=self::id($h['hotel_id']); if (isset($seen[$id])) { throw new CHttpException(400,'Duplicate Hotel assignment.'); } $seen[$id]=true;
            $entry=array('hotel_id'=>$id);
            if (array_key_exists('owner_user_id',$h)) { $entry['owner_user_id']=$h['owner_user_id']===null || $h['owner_user_id']===''?null:self::id($h['owner_user_id']); }
            $d['hotels'][]=$entry;
        }
        usort($d['hotels'],function($a,$b){return $a['hotel_id']<=>$b['hotel_id'];});
        $d['markets']=array();
        if (isset($input['markets'])) {
            if (!is_array($input['markets']) || count($input['markets'])>20) { throw new CHttpException(400,'Invalid markets.'); }
            foreach ($input['markets'] as $market) { if (!is_string($market) || !trim($market) || mb_strlen($market)>64) { throw new CHttpException(400,'Invalid market code.'); } $d['markets'][]=trim($market); }
            $d['markets']=array_values(array_unique($d['markets']));
        }
        foreach (array('allow_duplicate_account','allow_duplicate_contact','remember_alias') as $key) {
            $value=isset($input[$key])?$input[$key]:false;
            if (!in_array($value,array(true,false,0,1,'0','1'),true)) { throw new CHttpException(400,'Invalid review flag.'); }
            $d[$key]=(bool)$value;
        }
        return $d;
    }
    public static function map($id,$rowNo,$expectedVersion,array $input)
    {
        self::authorize('map'); $rowNo=self::id($rowNo); $expectedVersion=self::id($expectedVersion);
        return self::locked(function()use($id,$rowNo,$expectedVersion,$input){ return self::atomic(function()use($id,$rowNo,$expectedVersion,$input){
            $batch=self::batch($id,true); $db=Yii::app()->db;
            $row=$db->createCommand('SELECT * FROM crm_import_row WHERE batch_id=:batch AND row_no=:row FOR UPDATE')->queryRow(true,array(':batch'=>$batch['id'],':row'=>$rowNo));
            if (!$row) { throw new CHttpException(404,'Import row not found.'); }
            if (self::terminal($row) || (int)$row['lock_version']!==$expectedVersion) { throw new CHttpException(409,'This row is already committed or changed; reload it.'); }
            $d=self::decision($input,$rowNo,self::type($batch));
            $db->createCommand()->update('crm_import_row',array('decision_json'=>LegacyImportSource::json($d),'result_state'=>'needs_review','error_code'=>null,'lock_version'=>$expectedVersion+1),'batch_id=:batch AND row_no=:row',array(':batch'=>$batch['id'],':row'=>$rowNo));
            self::touch($batch['id'],array('status'=>'preview','approval_hash'=>null,'approved_by'=>null,'approved_at_utc'=>null,'completed_at_utc'=>null));
            self::audit($batch['id'],'import_row',$batch['id'].':'.$rowNo,'mapping_reviewed',$row['decision_json']?json_decode($row['decision_json'],true):null,$d,$d['reason']);
            self::refreshCounts($batch['id']); return self::batch($batch['id']);
        }); });
    }
    private static function digest($rows)
    {
        $parts=array();
        foreach ($rows as $row) { $parts[]=array($row['row_no'],$row['source_hash'],$row['decision_json']); }
        return hash('sha256',LegacyImportSource::json($parts));
    }
    private static function touch($id,array $values)
    {
        $values['updated_by']=Yii::app()->user->id; $values['updated_at_utc']=gmdate('Y-m-d H:i:s'); $values['lock_version']=new CDbExpression('lock_version+1');
        Yii::app()->db->createCommand()->update('crm_import_batch',$values,'id=:id',array(':id'=>$id));
    }
    public static function approve($id,$expectedVersion)
    {
        self::authorize('approve'); $expectedVersion=self::id($expectedVersion);
        return self::locked(function()use($id,$expectedVersion){ return self::atomic(function()use($id,$expectedVersion){
            $batch=self::batch($id,true); if ((int)$batch['lock_version']!==$expectedVersion) { throw new CHttpException(409,'Batch changed; review it again.'); }
            $rows=self::rows($batch['id'],true);
            foreach ($rows as $row) { if (!$row['source_hash'] || !$row['decision_json']) { throw new CHttpException(409,'Every row must have an explicit mapping or skip decision.'); } }
            if ($batch['status']==='completed') { return $batch; }
            self::touch($batch['id'],array('status'=>'approved','approval_hash'=>self::digest($rows),'approved_by'=>Yii::app()->user->id,'approved_at_utc'=>gmdate('Y-m-d H:i:s')));
            self::audit($batch['id'],'import_batch',$batch['id'],'approved',null,array('digest'=>self::digest($rows),'rows'=>count($rows)));
            return self::batch($batch['id']);
        }); });
    }
    private static function dependency($batchId,$rowNo)
    {
        $row=Yii::app()->db->createCommand('SELECT * FROM crm_import_row WHERE batch_id=:batch AND row_no=:row')->queryRow(true,array(':batch'=>$batchId,':row'=>$rowNo));
        if (!$row || !in_array($row['result_state'],array('created','linked'),true) || !$row['result_account_id']) { throw new LegacyImportException('DEPENDENCY_NOT_IMPORTED'); }
        return $row;
    }
    private static function owner($id,$hotelId)
    {
        if ($id===null) { return; }
        if (!ActionAccess::user($id)) { throw new LegacyImportException('OWNER_INACTIVE_OR_MISSING'); }
        if (!ActionAccess::isAll($id) && !UserHotelAccess::model()->exists('user_id=:user AND hotel_id=:hotel',array(':user'=>$id,':hotel'=>$hotelId))) { throw new LegacyImportException('OWNER_WITHOUT_HOTEL_ACCESS'); }
    }
    private static function currentRecord($class,$table,$condition,array $params)
    {
        $raw=Yii::app()->db->createCommand('SELECT * FROM '.$table.' WHERE '.$condition.' FOR UPDATE')->queryRow(true,$params);
        return $raw?$class::model()->populateRecord($raw,true):null;
    }
    private static function applyRow($batch,$row,$decision,$current)
    {
        $type=self::type($batch); $db=Yii::app()->db; $bid=$batch['id'];
        if ($decision['mode']==='skip') { return array('state'=>'skipped','account_id'=>null,'contact_id'=>null,'hotels'=>array()); }
        $link=$db->createCommand('SELECT * FROM crm_legacy_link WHERE source_type=:type AND source_id=:id FOR UPDATE')->queryRow(true,array(':type'=>$type,':id'=>$row['source_id']));
        if ($decision['mode']==='existing') {
            if (!$link || $link['resolution_state']!=='linked' || !$link['source_hash'] || $link['source_hash']!==$row['source_hash']) { throw new LegacyImportException('EXISTING_LINK_NOT_VERIFIED'); }
            if (!Account::model()->findByPk($link['account_id'])) { throw new LegacyImportException('DESTINATION_MISSING'); }
            if ($type==='report' && ((string)$current['account_id']!==(string)$link['account_id'] || (string)$current['contact_person_id']!==(string)$link['contact_person_id'] || $current['identity_state']!=='linked')) { throw new LegacyImportException('REPORT_LINK_CONFLICT'); }
            if ($type==='report') {
                $original=$db->createCommand("SELECT result_json FROM crm_import_row WHERE batch_id=:batch AND source_table='reports' AND source_id=:source AND result_state IN ('created','linked') ORDER BY row_no LIMIT 1")
                    ->queryScalar(array(':batch'=>$link['import_batch_id'],':source'=>$row['source_id']));
                $original=$original?json_decode($original,true):null;
                if (!$original || !isset($original['hotels'][0]) || (int)$current['hotel_id']!==(int)$original['hotels'][0]) { throw new LegacyImportException('REPORT_LINK_CONFLICT'); }
            }
            return array('state'=>'linked','account_id'=>$link['account_id'],'contact_id'=>$link['contact_person_id'],'hotels'=>$type==='report'?array((int)$current['hotel_id']):array(),'reused_existing'=>true);
        }
        if ($link) { throw new LegacyImportException('SOURCE_ALREADY_MAPPED'); }
        if ($type==='report') {
            $raw=json_decode($row['raw_json'],true);
            foreach (array('account_id','contact_person_id','hotel_id','identity_state','lock_version','client_request_key') as $field) {
                if ((string)$current[$field]!==(string)$raw[$field]) { throw new LegacyImportException('REPORT_IDENTITY_CHANGED'); }
            }
            if ($current['account_id']!==null || $current['hotel_id']!==null || $current['contact_person_id']!==null) { throw new LegacyImportException('REPORT_ALREADY_LINKED'); }
        }
        $values=LegacyImportSource::values(json_decode($row['raw_json'],true),$type); $created=false;
        if ($decision['account_kind']==='create') {
            $fields=array_merge($values['account'],$decision['account_fields']); $name=LegacyImportSource::normalize($fields['name']);
            if (Account::model()->exists('normalized_name=:name',array(':name'=>$name)) && !$decision['allow_duplicate_account']) { throw new LegacyImportException('DUPLICATE_ACCOUNT_REVIEW'); }
            $fields['code']='IMP-'.$bid.'-'.$row['row_no']; $account=self::save('Account',$fields); $created=true;
            self::audit($bid,'account',$account->id,'import_created',null,array('source'=>$row['source_id']),$decision['reason']);
        } elseif ($decision['account_kind']==='row') {
            $dep=self::dependency($bid,$decision['account_row_no']); $account=self::currentRecord('Account','crm_account','id=:id',array(':id'=>$dep['result_account_id']));
        } else {
            $account=self::currentRecord('Account','crm_account','id=:id',array(':id'=>$decision['account_id']));
            if ($account && (int)$account->lock_version!==$decision['account_version']) { throw new LegacyImportException('ACCOUNT_CHANGED_AFTER_REVIEW'); }
        }
        if (!$account || $account->status!=='active') { throw new LegacyImportException('DESTINATION_ACCOUNT_INACTIVE'); }
        $hotelIds=array();
        foreach ($decision['hotels'] as $choice) {
            $hotel=Hotel::model()->findByPk($choice['hotel_id']); if (!$hotel || $hotel->status!=='active') { throw new LegacyImportException('HOTEL_INACTIVE_OR_MISSING'); }
            $assignment=self::currentRecord('AccountHotel','crm_account_hotel','account_id=:account AND hotel_id=:hotel',array(':account'=>$account->id,':hotel'=>$hotel->id));
            $owner=array_key_exists('owner_user_id',$choice)?$choice['owner_user_id']:($assignment?$assignment->owner_user_id:null);
            self::owner($owner,$hotel->id);
            if ($assignment) {
                if ($assignment->status!=='active' || (string)$assignment->owner_user_id!==(string)$owner) { throw new LegacyImportException('OWNER_OR_ASSIGNMENT_CONFLICT'); }
            } else {
                self::save('AccountHotel',array('account_id'=>$account->id,'hotel_id'=>$hotel->id,'owner_user_id'=>$owner));
                self::audit($bid,'account_hotel',$account->id.':'.$hotel->id,'import_assigned',null,array('owner_user_id'=>$owner),$decision['reason']);
            }
            $hotelIds[]=(int)$hotel->id;
        }
        $person=null;
        if ($decision['contact_kind']==='create') {
            $fields=array_merge($values['contact'],$decision['contact_fields']); $name=LegacyImportSource::normalize($fields['first_name'].' '.$fields['last_name']);
            foreach (ContactPerson::model()->findAll('account_id=:id',array(':id'=>$account->id)) as $existing) {
                if ($name!=='' && LegacyImportSource::normalize($existing->first_name.' '.$existing->last_name)===$name && !$decision['allow_duplicate_contact']) { throw new LegacyImportException('DUPLICATE_CONTACT_REVIEW'); }
            }
            $fields['account_id']=$account->id; $person=self::save('ContactPerson',$fields);
            self::audit($bid,'contact_person',$person->id,'import_created',null,array('account_id'=>$account->id,'source'=>$row['source_id']),$decision['reason']);
        } elseif ($decision['contact_kind']==='row') {
            $dep=self::dependency($bid,$decision['contact_row_no']); $person=$dep['result_contact_id']?self::currentRecord('ContactPerson','crm_contact_person','id=:id',array(':id'=>$dep['result_contact_id'])):null;
            if (!$person) { throw new LegacyImportException('CONTACT_DEPENDENCY_EMPTY'); }
        } elseif ($decision['contact_kind']==='link') {
            $person=self::currentRecord('ContactPerson','crm_contact_person','id=:id',array(':id'=>$decision['contact_person_id']));
            if ($person && (int)$person->lock_version!==$decision['contact_version']) { throw new LegacyImportException('CONTACT_CHANGED_AFTER_REVIEW'); }
            if (!$person) { throw new LegacyImportException('DESTINATION_CONTACT_MISSING'); }
        }
        if ($person && ($person->status!=='active' || (string)$person->account_id!==(string)$account->id)) { throw new LegacyImportException('CONTACT_ACCOUNT_MISMATCH'); }
        foreach ($decision['markets'] as $market) { if (!AccountMarket::model()->findByPk(array('account_id'=>$account->id,'market_code'=>$market))) { self::save('AccountMarket',array('account_id'=>$account->id,'market_code'=>$market)); } }
        if ($decision['remember_alias']) {
            $alias=LegacyImportSource::normalize($values['account']['name']);
            if (!$alias || EntityAlias::model()->exists("entity_type='account' AND normalized_alias=:name AND entity_id<>:id",array(':name'=>$alias,':id'=>$account->id))) { throw new LegacyImportException('ALIAS_CONFLICT'); }
            if (!EntityAlias::model()->exists("entity_type='account' AND normalized_alias=:name AND entity_id=:id",array(':name'=>$alias,':id'=>$account->id))) { self::save('EntityAlias',array('entity_type'=>'account','entity_id'=>$account->id,'normalized_alias'=>$alias,'source'=>'reviewed legacy import')); }
        }
        self::save('LegacyLink',array('source_type'=>$type,'source_id'=>$row['source_id'],'account_id'=>$account->id,'contact_person_id'=>$person?$person->id:null,'resolution_state'=>'linked',
            'import_batch_id'=>$bid,'reviewed_by'=>$batch['approved_by'],'reviewed_at_utc'=>$batch['approved_at_utc'],'source_hash'=>$row['source_hash']));
        if ($type==='report') {
            $db->createCommand()->update('reports',array('account_id'=>$account->id,'contact_person_id'=>$person?$person->id:null,'hotel_id'=>$hotelIds[0],'identity_state'=>'linked','lock_version'=>new CDbExpression('lock_version+1')),'id=:id',array(':id'=>$row['source_id']));
        }
        return array('state'=>$created?'created':'linked','account_id'=>$account->id,'contact_id'=>$person?$person->id:null,'hotels'=>$hotelIds);
    }
    public static function commit($id,$limit=100)
    {
        self::authorize('commit'); $limit=self::id($limit); if ($limit>500) { throw new CHttpException(400,'Commit limit is at most 500.'); }
        return self::locked(function()use($id,$limit){
            $batch=self::batch($id); $rows=self::rows($batch['id']);
            if ($batch['status']==='completed') { return self::report($batch['id']); }
            if (!in_array($batch['status'],array('approved','running','failed'),true) || !$batch['approval_hash'] || !hash_equals($batch['approval_hash'],self::digest($rows))) { throw new CHttpException(409,'Approve the current decisions before importing.'); }
            if (!$batch['approved_by'] || !ActionAccess::isAll($batch['approved_by']) || !ActionAccess::can('customerimport','approve',$batch['approved_by'])) { throw new CHttpException(409,'The approver no longer has permission; approve this batch again.'); }
            $done=0;
            foreach ($rows as $candidate) {
                if (self::terminal($candidate)) { continue; }
                if ($done++ >= $limit) { break; }
                self::authorize('commit');
                try {
                    self::atomic(function()use($batch,$candidate){
                        $db=Yii::app()->db;
                        // Same Hotel lock ordering as grant administration. No owner/grant auto-updates.
                        $decision=json_decode($candidate['decision_json'],true);
                        if (isset($decision['hotels'])) { foreach ($decision['hotels'] as $h) { $db->createCommand('SELECT id FROM crm_hotel WHERE id=:id FOR UPDATE')->queryScalar(array(':id'=>$h['hotel_id'])); } }
                        $fresh=self::batch($batch['id'],true); $row=$db->createCommand('SELECT * FROM crm_import_row WHERE batch_id=:batch AND row_no=:row FOR UPDATE')->queryRow(true,array(':batch'=>$batch['id'],':row'=>$candidate['row_no']));
                        if (self::terminal($row)) { return; }
                        if ($fresh['approval_hash']!==$batch['approval_hash'] || $row['decision_json']!==$candidate['decision_json']) { throw new LegacyImportException('APPROVAL_CHANGED'); }
                        if (!$fresh['approved_by'] || !ActionAccess::isAll($fresh['approved_by']) || !ActionAccess::can('customerimport','approve',$fresh['approved_by'])) { throw new CHttpException(409,'Approver permission changed; review this batch again.'); }
                        $type=self::type($fresh); $current=$db->createCommand('SELECT * FROM '.LegacyImportSource::table($type).' WHERE id=:id FOR UPDATE')->queryRow(true,array(':id'=>$row['source_id']));
                        if (!$current || !hash_equals($row['source_hash'],LegacyImportSource::hash($current,$type))) { throw new LegacyImportException('SOURCE_CHANGED_SINCE_PREVIEW'); }
                        $result=self::applyRow($fresh,$row,$decision,$current);
                        $db->createCommand()->update('crm_import_row',array('result_state'=>$result['state'],'result_account_id'=>$result['account_id'],'result_contact_id'=>$result['contact_id'],
                            'error_code'=>null,'result_json'=>LegacyImportSource::json($result),'lock_version'=>new CDbExpression('lock_version+1')),'batch_id=:batch AND row_no=:row',array(':batch'=>$batch['id'],':row'=>$row['row_no']));
                        self::audit($batch['id'],'import_row',$batch['id'].':'.$row['row_no'],'import_committed',null,$result,$decision['reason']);
                    });
                } catch (Exception $e) {
                    if ($e instanceof CHttpException) { throw $e; }
                    $code=$e instanceof LegacyImportException?$e->errorCode:'DB_WRITE_FAILED';
                    self::atomic(function()use($batch,$candidate,$code,$e){
                        $db=Yii::app()->db; self::batch($batch['id'],true);
                        $db->createCommand()->update('crm_import_row',array('result_state'=>'error','error_code'=>$code,'result_json'=>LegacyImportSource::json(array('error'=>$code,'fields'=>$e instanceof LegacyImportException && $code==='VALIDATION_FAILED'?json_decode($e->getMessage(),true):null)),
                            'lock_version'=>new CDbExpression('lock_version+1')),'batch_id=:batch AND row_no=:row',array(':batch'=>$batch['id'],':row'=>$candidate['row_no']));
                        self::audit($batch['id'],'import_row',$batch['id'].':'.$candidate['row_no'],'import_failed',null,array('error'=>$code));
                    });
                }
            }
            self::atomic(function()use($batch){ self::batch($batch['id'],true); self::refreshCounts($batch['id'],true); });
            return self::report($batch['id']);
        });
    }
    private static function refreshCounts($id,$afterCommit=false)
    {
        $counts=self::counts(self::rows($id)); $values=array('counts_json'=>LegacyImportSource::json($counts));
        if ($afterCommit) {
            $values['status']=$counts['needs_review']===0 && $counts['error']===0?'completed':($counts['error']>0?'failed':'running');
            if ($values['status']==='completed') { $values['completed_at_utc']=gmdate('Y-m-d H:i:s'); }
        }
        self::touch($id,$values);
    }
    private static function counts(array $rows)
    {
        $counts=array('total'=>count($rows),'created'=>0,'linked'=>0,'skipped'=>0,'error'=>0,'needs_review'=>0,'mapped'=>0);
        foreach ($rows as $row) { if (isset($counts[$row['result_state']])) { $counts[$row['result_state']]++; } if ($row['decision_json']) { $counts['mapped']++; } }
        $counts['balanced']=$counts['total']===$counts['created']+$counts['linked']+$counts['skipped']+$counts['error']+$counts['needs_review'];
        return $counts;
    }
    private static function report($id)
    {
        $batch=self::batch($id); $rows=self::rows($id); $issues=array();
        foreach ($rows as $row) {
            if (!in_array($row['result_state'],array('created','linked'),true)) { continue; }
            $link=LegacyLink::model()->findByPk(array('source_type'=>self::type($batch),'source_id'=>$row['source_id']));
            if (!$link || (string)$link->account_id!==(string)$row['result_account_id'] || (string)$link->contact_person_id!==(string)$row['result_contact_id'] || $link->source_hash!==$row['source_hash']) { $issues[]=array('row_no'=>$row['row_no'],'code'=>'LINK_MISMATCH'); }
            if (!Account::model()->findByPk($row['result_account_id']) || ($row['result_contact_id'] && !ContactPerson::model()->findByPk($row['result_contact_id']))) { $issues[]=array('row_no'=>$row['row_no'],'code'=>'DESTINATION_MISSING'); }
            $current=Yii::app()->db->createCommand('SELECT * FROM '.LegacyImportSource::table(self::type($batch)).' WHERE id=:id')->queryRow(true,array(':id'=>$row['source_id']));
            if (!$current || LegacyImportSource::hash($current,self::type($batch))!==$row['source_hash']) { $issues[]=array('row_no'=>$row['row_no'],'code'=>'SOURCE_DRIFT'); }
            if (self::type($batch)==='report' && $current) {
                $result=json_decode($row['result_json'],true);
                if ((string)$current['account_id']!==(string)$row['result_account_id'] || (string)$current['contact_person_id']!==(string)$row['result_contact_id'] || $current['identity_state']!=='linked' || (isset($result['hotels'][0]) && (int)$current['hotel_id']!==(int)$result['hotels'][0])) { $issues[]=array('row_no'=>$row['row_no'],'code'=>'REPORT_LINK_MISMATCH'); }
            }
        }
        $events=Yii::app()->db->createCommand("SELECT entity_type,COUNT(*) n FROM crm_audit_event WHERE import_batch_id=:id AND event_type='import_created' GROUP BY entity_type")->queryAll(true,array(':id'=>$id));
        $objects=array('accounts_created'=>0,'contacts_created'=>0,'assignments_created'=>(int)Yii::app()->db->createCommand("SELECT COUNT(*) FROM crm_audit_event WHERE import_batch_id=:id AND event_type='import_assigned'")->queryScalar(array(':id'=>$id)),
            'reports_linked'=>self::type($batch)==='report'?count(array_filter($rows,function($row){return in_array($row['result_state'],array('created','linked'),true);})):0);
        foreach ($events as $event) { if ($event['entity_type']==='account') { $objects['accounts_created']=(int)$event['n']; } if ($event['entity_type']==='contact_person') { $objects['contacts_created']=(int)$event['n']; } }
        return array('batch'=>$batch,'counts'=>self::counts($rows),'objects'=>$objects,'issues'=>$issues,'consistent'=>!$issues);
    }
    public static function reconcile($id) { self::authorize('reconcile'); return self::report($id); }
    public static function view($id)
    {
        self::authorize('view'); $batch=self::batch($id); $rows=self::rows($id);
        foreach ($rows as &$row) { foreach (array('raw_json','proposed_json','decision_json','result_json') as $key) { $row[substr($key,0,-5)]=$row[$key]?json_decode($row[$key],true):null; unset($row[$key]); } } unset($row);
        return array('batch'=>$batch,'rows'=>$rows,'counts'=>self::counts(self::rows($id)));
    }
    public static function listing()
    {
        self::authorize('admin'); return Yii::app()->db->createCommand("SELECT * FROM crm_import_batch WHERE source_type IN ('legacy_contact','legacy_report') AND mapping_version=:version ORDER BY id DESC LIMIT 50")->queryAll(true,array(':version'=>self::VERSION));
    }
    public static function browseSources($type,$page=1)
    {
        self::authorize('preview'); $table=LegacyImportSource::table($type); $page=self::id($page);
        if ($page>100000) { throw new CHttpException(400,'Invalid page.'); }
        $name=$type==='contact'?'company':'company_name';
        $rows=Yii::app()->db->createCommand('SELECT id,'.$name.' AS company,first_name,last_name,email FROM '.$table.' ORDER BY id LIMIT 50 OFFSET '.(($page-1)*50))->queryAll();
        return array('rows'=>$rows,'page'=>$page,'total'=>(int)Yii::app()->db->createCommand('SELECT COUNT(*) FROM '.$table)->queryScalar(),'type'=>$type);
    }
}
