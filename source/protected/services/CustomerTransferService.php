<?php
/** Reviewed CSV and legacy schedule jobs. Bounded batches; each row is an atomic service mutation. */
class CustomerTransferService
{
    private static function access($action,$batch=null)
    {
        ActionAccess::requireAction('customermove',$action); CutoverService::testDb();
        if ($batch) {
            if ($batch['kind']==='schedule') { if (!ActionAccess::isAll()) { throw new CHttpException(403,'Nguồn lịch cũ cần All.'); } }
            else { CustomerWriteSupport::hotel($batch['hotel_id'],true); if (!ActionAccess::isAll() && (string)$batch['created_by']!==(string)Yii::app()->user->id) { throw new CHttpException(404,'Không tìm thấy batch.'); } }
        }
    }
    private static function batch($id,$action)
    {
        $id=CustomerWriteSupport::id($id); $b=Yii::app()->db->createCommand('SELECT * FROM crm_transfer_batch WHERE id=:id')->queryRow(true,array(':id'=>$id));
        if (!$b) { throw new CHttpException(404,'Không tìm thấy batch.'); } self::access($action,$b); return $b;
    }
    private static function rows($id) { return Yii::app()->db->createCommand('SELECT * FROM crm_transfer_row WHERE batch_id=:id ORDER BY row_no')->queryAll(true,array(':id'=>$id)); }
    public static function view($id,$action='view') { $b=self::batch($id,$action); return array('batch'=>$b,'rows'=>self::rows($b['id'])); }
    public static function listing()
    {
        self::access('admin'); $out=array();
        foreach (Yii::app()->db->createCommand('SELECT * FROM crm_transfer_batch ORDER BY id DESC LIMIT 100')->queryAll() as $b) { try { self::access('admin',$b); $out[]=$b; } catch (CHttpException $ignored) {} } return $out;
    }
    private static function store($kind,$hotel,$hash,array $rows)
    {
        return CutoverService::exclusive(function()use($kind,$hotel,$hash,$rows){
            if ($kind==='schedule') { CutoverService::sourceImport(); }
            $db=Yii::app()->db; $old=$db->createCommand('SELECT id FROM crm_transfer_batch WHERE kind=:kind AND source_hash=:hash AND created_by=:actor AND (hotel_id=:hotel OR (hotel_id IS NULL AND :nohotel=1)) ORDER BY id LIMIT 1')->queryScalar(array(':kind'=>$kind,':hash'=>$hash,':actor'=>Yii::app()->user->id,':hotel'=>$hotel,':nohotel'=>$hotel===null?1:0));
            if ($old) { self::batch($old,'preview'); return (int)$old; }
            $db->createCommand()->insert('crm_transfer_batch',array('kind'=>$kind,'hotel_id'=>$hotel,'source_hash'=>$hash,'created_by'=>Yii::app()->user->id,'created_at_utc'=>gmdate('Y-m-d H:i:s'))); $id=(int)$db->lastInsertID;
            foreach ($rows as $i=>$r) { $db->createCommand()->insert('crm_transfer_row',array('batch_id'=>$id,'row_no'=>$i+1,'payload_json'=>LegacyImportSource::json($r))); }
            CustomerWriteSupport::audit('transfer_batch',$id,'previewed',null,array('kind'=>$kind,'source_hash'=>$hash,'rows'=>count($rows))); return $id;
        });
    }
    public static function previewCsv($stream,$hotel)
    {
        self::access('preview'); $hotel=CustomerWriteSupport::id($hotel); CustomerWriteSupport::hotel($hotel,true); $data=CustomerTransferCsv::read($stream);
        // IDs in a file are hints only. Validate them against this Hotel before persisting a preview.
        foreach ($data['rows'] as $r) {
            $ids=$r['ids']; if (!empty($ids['hotel_id']) && CustomerWriteSupport::id($ids['hotel_id'])!==$hotel) { throw new CHttpException(400,'CSV có khách sạn khác.'); }
            if (!empty($ids['account_id'])) { CustomerPolicy::assertAccountHotel(CustomerWriteSupport::id($ids['account_id']),$hotel,'customermove','preview',true); }
            if (!empty($ids['contact_id'])) { $p=CustomerPolicy::load('ContactPerson','person','customermove','preview',$ids['contact_id']); if (empty($ids['account_id']) || (string)$p->account_id!==(string)$ids['account_id']) { throw new CHttpException(404,'Người liên hệ không thuộc hồ sơ trong file.'); } }
        }
        return self::store('csv',$hotel,$data['hash'],$data['rows']);
    }
    public static function previewSchedules(array $ids)
    {
        self::access('preview'); CutoverService::admin('preview'); CutoverService::sourceImport();
        if (!$ids || count($ids)>150) { throw new CHttpException(400,'Chọn 1–150 báo cáo mỗi batch.'); }
        $ids=array_unique(array_map(array('CustomerWriteSupport','id'),$ids)); sort($ids,SORT_NUMERIC); $rows=array();
        foreach ($ids as $id) {
            $r=Yii::app()->db->createCommand('SELECT * FROM reports WHERE id=:id')->queryRow(true,array(':id'=>$id));
            if (!$r || $r['snapshot_json']!==null) { throw new CHttpException(404,'Chỉ chọn báo cáo nguồn cũ.'); }
            foreach (array('ent'=>'entertainment','sales'=>'visit','site_ins'=>'site_inspection') as $f=>$type) {
                $date=$r[$f]; $valid=is_string($date) && MvpActiveRecord::validDate($date,'Y-m-d');
                $rows[]=array('report_id'=>$id,'field'=>$f,'raw_date'=>$date,'valid'=>$valid,'future'=>$valid && $date>(new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d'),
                    'account_id'=>$r['account_id'],'hotel_id'=>$r['hotel_id'],'contact_id'=>$r['contact_person_id'],'action_type'=>$type,'legacy_key'=>'report:'.$id.':field:'.$f,'source_hash'=>LegacyImportSource::hash($r,'report'));
            }
        }
        return self::store('schedule',null,hash('sha256',LegacyImportSource::json($rows)),$rows);
    }
    private static function row($id,$number)
    {
        $number=CustomerWriteSupport::id($number); $r=Yii::app()->db->createCommand('SELECT * FROM crm_transfer_row WHERE batch_id=:id AND row_no=:n')->queryRow(true,array(':id'=>$id,':n'=>$number)); if (!$r) { throw new CHttpException(404,'Không tìm thấy dòng.'); } return $r;
    }
    private static function version($row,$version) { if ((int)$row['lock_version']!==CustomerWriteSupport::id($version)) { throw new CHttpException(409,'Dữ liệu đã đổi, tải lại trước khi duyệt.'); } }
    private static function decision($b,$r,array $d)
    {
        $p=json_decode($r['payload_json'],true); $allowed=$b['kind']==='csv'?array('mode','account_id','account_version','contact_mode','contact_id','contact_version','owner_user_id','reason'):array('mode','owner_user_id','assignment_version','confirmed','reason');
        $d=CustomerWriteSupport::fields($d,$allowed); $d['reason']=CustomerWriteSupport::reason(isset($d['reason'])?$d['reason']:'');
        if (!isset($d['mode']) || !in_array($d['mode'],array('skip','create','link'),true)) { throw new CHttpException(400,'Cần quyết định create/link/skip.'); }
        if ($d['mode']==='skip') { return $d; }
        if ($b['kind']==='schedule') {
            if ($d['mode']!=='create' || !$p['valid'] || !$p['future'] || empty($d['confirmed']) || (string)$d['confirmed']!=='1') { throw new CHttpException(409,'Chỉ tạo lịch tương lai hợp lệ đã xác nhận còn hiệu lực.'); }
            if (!$p['account_id'] || !$p['hotel_id']) { throw new CHttpException(409,'Cần mapping hồ sơ/khách sạn trước khi preview lại.'); }
            CustomerPolicy::assertAccountHotel($p['account_id'],$p['hotel_id'],'customermove','map',true);
            $ah=AccountHotel::model()->findByPk(array('account_id'=>$p['account_id'],'hotel_id'=>$p['hotel_id']));
            self::version($ah->attributes,isset($d['assignment_version'])?$d['assignment_version']:0);
            if (empty($d['owner_user_id']) || (string)$ah->owner_user_id!==(string)$d['owner_user_id']) { throw new CHttpException(409,'Quản lý cần xác nhận owner hiện tại.'); }
            CustomerWriteSupport::owner($d['owner_user_id'],$p['hotel_id']);
        } else {
            CustomerWriteSupport::owner(isset($d['owner_user_id'])?$d['owner_user_id']:null,$b['hotel_id']);
            if ($d['mode']==='link') {
                if (empty($d['account_id'])) { throw new CHttpException(400,'Cần account_id.'); }
                CustomerPolicy::assertAccountHotel($d['account_id'],$b['hotel_id'],'customermove','map',true);
                $a=Account::model()->findByPk($d['account_id']); self::version($a->attributes,isset($d['account_version'])?$d['account_version']:0);
                if ($a->status!=='active') { throw new CHttpException(409,'Hồ sơ đã lưu trữ.'); }
                $d['account_id']=(int)$a->id;
            }
            if (!isset($d['contact_mode']) || !in_array($d['contact_mode'],array('none','create','link'),true)) { throw new CHttpException(400,'Cần contact_mode none/create/link.'); }
            if ($d['contact_mode']==='link') {
                if ($d['mode']!=='link' || empty($d['contact_id'])) { throw new CHttpException(400,'Chỉ link người liên hệ của hồ sơ có sẵn.'); }
                $c=CustomerPolicy::load('ContactPerson','person','customermove','map',$d['contact_id']);
                if ((string)$c->account_id!==(string)$d['account_id'] || $c->status!=='active') { throw new CHttpException(404,'Người liên hệ không hợp lệ.'); }
                self::version($c->attributes,isset($d['contact_version'])?$d['contact_version']:0);
            }
            if ($d['mode']==='create' && !empty($p['ids']['account_id'])) { throw new CHttpException(409,'File có account_id; cần link hoặc xóa ID để tạo hồ sơ riêng.'); }
        }
        return $d;
    }
    public static function map($id,$number,$expected,array $d)
    {
        return CutoverService::exclusive(function()use($id,$number,$expected,$d){
            $b=self::batch($id,'map'); if ($b['kind']==='schedule') { CutoverService::sourceImport(); } $r=self::row($id,$number); self::version($r,$expected);
            if (in_array($r['state'],array('created','linked','skipped'),true)) { throw new CHttpException(409,'Dòng đã xử lý.'); }
            $d=self::decision($b,$r,$d); $db=Yii::app()->db;
            $db->createCommand()->update('crm_transfer_row',array('decision_json'=>LegacyImportSource::json($d),'state'=>'needs_review','error_code'=>null,'lock_version'=>(int)$expected+1),'id=:id',array(':id'=>$r['id']));
            $db->createCommand()->update('crm_transfer_batch',array('status'=>'preview','approved_by'=>null,'approval_hash'=>null,'lock_version'=>(int)$b['lock_version']+1),'id=:id',array(':id'=>$id));
            CustomerWriteSupport::audit('transfer_batch',$id,'mapped',null,array('row_no'=>$number,'decision'=>$d),$d['reason']); return self::view($id,'map');
        });
    }
    private static function digest($rows) { return hash('sha256',LegacyImportSource::json(array_map(function($r){return array($r['row_no'],$r['payload_json'],$r['decision_json']);},$rows))); }
    public static function approve($id,$expected)
    {
        return CutoverService::exclusive(function()use($id,$expected){
            $b=self::batch($id,'approve'); if ($b['kind']==='schedule') { CutoverService::sourceImport(); } self::version($b,$expected); $rows=self::rows($id);
            foreach ($rows as $r) { if (!$r['decision_json']) { throw new CHttpException(409,'Còn dòng chưa quyết định.'); } if (!in_array($r['state'],array('created','linked','skipped'),true)) { self::decision($b,$r,json_decode($r['decision_json'],true)); } }
            Yii::app()->db->createCommand()->update('crm_transfer_batch',array('status'=>'approved','approved_by'=>Yii::app()->user->id,'approval_hash'=>self::digest($rows),'lock_version'=>(int)$expected+1),'id=:id',array(':id'=>$id));
            CustomerWriteSupport::audit('transfer_batch',$id,'approved',null,array('digest'=>self::digest($rows))); return self::view($id,'approve');
        });
    }
    private static function sourceCheck($p,$lock=false)
    {
        $source=Yii::app()->db->createCommand('SELECT * FROM reports WHERE id=:id'.($lock?' FOR UPDATE':''))->queryRow(true,array(':id'=>$p['report_id']));
        if (!$source || LegacyImportSource::hash($source,'report')!==$p['source_hash'] || (string)$source['account_id']!==(string)$p['account_id'] || (string)$source['hotel_id']!==(string)$p['hotel_id'] || $source['identity_state']!=='linked') { throw new CHttpException(409,'Nguồn/mapping báo cáo đã đổi; preview lại.'); }
    }
    private static function apply($b,$r,$d,$p)
    {
        $db=Yii::app()->db; CutoverService::canonicalWrite();
        if ($b['kind']==='schedule') {
            CutoverService::sourceImport(); if ($d['mode']!=='skip') { self::sourceCheck($p); }
        }
        if ($d['mode']==='skip') { return array('state'=>'skipped','reason'=>$d['reason']); }
        self::decision($b,$r,$d); $key='wp08_'.hash('sha224',$b['id'].':'.$r['row_no']);
        if ($b['kind']==='schedule') {
            // Recheck actual wall date at commit; preview's future boolean is never authority.
            if ($p['raw_date']<=(new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d')) { throw new CHttpException(409,'Lịch đã đến hạn/quá hạn; chỉ rà soát, không backfill việc.'); }
            CustomerWriteSupport::lockHotels(array($p['hotel_id']));
            $a=CustomerWriteSupport::current('Account','crm_account','id=:id',array(':id'=>$p['account_id']));
            $ah=CustomerWriteSupport::current('AccountHotel','crm_account_hotel','account_id=:a AND hotel_id=:h',array(':a'=>$p['account_id'],':h'=>$p['hotel_id'])); self::version($ah->attributes,$d['assignment_version']);
            if ((string)$ah->owner_user_id!==(string)$d['owner_user_id']) { throw new CHttpException(409,'Owner đã đổi.'); }
            self::sourceCheck($p,true);
            $existing=Task::model()->find('legacy_key=:key',array(':key'=>$p['legacy_key']));
            if ($existing) {
                $verified=false;
                foreach ($db->createCommand("SELECT r.payload_json,r.result_json FROM crm_transfer_row r JOIN crm_transfer_batch b ON b.id=r.batch_id WHERE b.kind='schedule' AND r.state IN ('created','linked')")->queryAll() as $prior) {
                    $pp=json_decode($prior['payload_json'],true); $rr=json_decode($prior['result_json'],true);
                    if ($pp['legacy_key']===$p['legacy_key'] && $pp['source_hash']===$p['source_hash'] && isset($rr['task_id']) && (int)$rr['task_id']===(int)$existing->id) { $verified=true; break; }
                }
                if (!$verified) { throw new CHttpException(409,'Legacy key đã tồn tại nhưng nguồn đã đổi/chưa đối soát.'); }
                if ((int)$existing->account_id!==(int)$p['account_id'] || (int)$existing->report_id!==(int)$p['report_id'] || (int)$existing->hotel_id!==(int)$p['hotel_id']) { throw new CHttpException(409,'Legacy key xung đột.'); } return array('state'=>'linked','account_id'=>$existing->account_id,'task_id'=>$existing->id,'legacy_key'=>$p['legacy_key']); }
            $task=TaskService::prepareCreate($p['account_id'],$p['hotel_id'],array('title'=>'Lịch cũ đã duyệt: '.$p['field'],'description'=>$d['reason'],'kind'=>'task','action_type'=>$p['action_type'],'due_mode'=>'date','due_date'=>$p['raw_date'],'timezone'=>'Asia/Ho_Chi_Minh','contact_person_id'=>$p['contact_id']),$key,false,'',$d['owner_user_id'],$p['report_id']);
            $task->legacy_key=$p['legacy_key']; TaskService::persistPrepared($task); CustomerWriteSupport::audit('task',$task->id,'legacy_schedule_reviewed',null,array('legacy_key'=>$p['legacy_key'],'source_hash'=>$p['source_hash']),$d['reason']);
            return array('state'=>'created','account_id'=>$task->account_id,'task_id'=>$task->id,'legacy_key'=>$p['legacy_key']);
        }
        if ($d['mode']==='create') {
            $res=AccountService::create($p['account'],$b['hotel_id'],isset($d['owner_user_id'])?$d['owner_user_id']:null,$d['contact_mode']==='create'?$p['contact']:null,$p['markets'],$key); $res['state']='created'; return $res;
        }
        CustomerWriteSupport::lockHotels(CustomerWriteSupport::accountHotels($d['account_id']));
        $current=CustomerWriteSupport::current('Account','crm_account','id=:id',array(':id'=>$d['account_id'])); self::version($current->attributes,$d['account_version']);
        CustomerPolicy::assertAccountHotel($d['account_id'],$b['hotel_id'],'customermove','commit',true);
        if ($d['contact_mode']==='link') {
            $c=CustomerWriteSupport::current('ContactPerson','crm_contact_person','id=:id AND account_id=:a',array(':id'=>$d['contact_id'],':a'=>$d['account_id'])); self::version($c->attributes,$d['contact_version']);
            if ($c->status!=='active') { throw new CHttpException(409,'Người liên hệ đã lưu trữ.'); }
        }
        $res=array('state'=>'linked','account_id'=>$d['account_id']);
        if ($d['contact_mode']==='create') { $res=AccountService::person($d['account_id'],null,$p['contact'],null,$key); $res['state']='created'; }
        elseif ($d['contact_mode']==='link') { $res['contact_id']=$d['contact_id']; }
        return $res;
    }
    public static function commit($id,$limit=50)
    {
        $limit=CustomerWriteSupport::id($limit); if ($limit>100) { throw new CHttpException(400,'Giới hạn 100 dòng/lần.'); }
        $b=self::batch($id,'commit'); if ($b['kind']==='schedule') { CutoverService::sourceImport(); } $rows=self::rows($id); if (!in_array($b['status'],array('approved','completed'),true) || !hash_equals((string)$b['approval_hash'],self::digest($rows))) { throw new CHttpException(409,'Cần duyệt batch trước khi ghi.'); }
        $n=0;
        foreach ($rows as $candidate) {
            if (in_array($candidate['state'],array('created','linked','skipped'),true)) { continue; } if (++$n>$limit) { break; }
            try { CutoverService::exclusive(function()use($id,$candidate){
                $b=self::batch($id,'commit'); $rows=self::rows($id); if ($b['status']!=='approved' || !hash_equals((string)$b['approval_hash'],self::digest($rows))) { throw new CHttpException(409,'Mapping/duyệt đã đổi.'); }
                $r=self::row($id,$candidate['row_no']); if (in_array($r['state'],array('created','linked','skipped'),true)) { return; }
                $fn=function()use($b,$r){ return self::apply($b,$r,json_decode($r['decision_json'],true),json_decode($r['payload_json'],true)); };
                $result=ActionAccess::isAll()?CutoverService::maintenance($fn):$fn();
                Yii::app()->db->createCommand()->update('crm_transfer_row',array('state'=>$result['state'],'result_json'=>LegacyImportSource::json($result),'error_code'=>null,'lock_version'=>(int)$r['lock_version']+1),'id=:id',array(':id'=>$r['id']));
                CustomerWriteSupport::audit('transfer_batch',$id,'row_committed',null,array('row_no'=>$r['row_no'],'result'=>$result));
            }); } catch (Exception $e) {
                if ($e instanceof CHttpException && in_array($e->statusCode,array(403,404,410,503),true)) { throw $e; }
                // Keep payload and decisions intact. Error recording must not overwrite a concurrent successful row/remap.
                CutoverService::exclusive(function()use($id,$candidate,$e){ self::batch($id,'commit'); $r=self::row($id,$candidate['row_no']); if ((int)$r['lock_version']!==(int)$candidate['lock_version']) { return; } Yii::app()->db->createCommand()->update('crm_transfer_row',array('state'=>'error','error_code'=>$e instanceof CHttpException?'HTTP_'.$e->statusCode:($e instanceof CustomerValidationException?'VALIDATION_FAILED':'DB_WRITE_FAILED'),'result_json'=>LegacyImportSource::json(array('error'=>$e instanceof CHttpException?$e->getMessage():'Không ghi được dòng; kiểm tra trường hoặc thử lại.','fields'=>$e instanceof CustomerValidationException?$e->errors:null))),'id=:id',array(':id'=>$r['id'])); });
            }
        }
        CutoverService::exclusive(function()use($id){ $b=self::batch($id,'commit'); if ($b['status']==='approved' && !array_filter(self::rows($id),function($r){return !in_array($r['state'],array('created','linked','skipped'),true);})) { Yii::app()->db->createCommand()->update('crm_transfer_batch',array('status'=>'completed'),'id=:id',array(':id'=>$id)); } });
        return self::reconcile($id,'commit');
    }
    public static function reconcile($id,$action='reconcile')
    {
        $b=self::batch($id,$action); $counts=array(); $issues=array(); $warnings=array(); $rows=self::rows($id);
        foreach ($rows as $r) {
            $counts[$r['state']]=isset($counts[$r['state']])?$counts[$r['state']]+1:1; $p=json_decode($r['payload_json'],true); $res=$r['result_json']?json_decode($r['result_json'],true):null;
            if ($b['kind']==='schedule') {
                $source=Yii::app()->db->createCommand('SELECT * FROM reports WHERE id=:id')->queryRow(true,array(':id'=>$p['report_id'])); $code=null;
                if (!$source || LegacyImportSource::hash($source,'report')!==$p['source_hash']) { $code='SOURCE_DRIFT'; }
                elseif ((string)$source['account_id']!==(string)$p['account_id'] || (string)$source['hotel_id']!==(string)$p['hotel_id'] || (string)$source['contact_person_id']!==(string)$p['contact_id']) { $code='MAPPING_DRIFT'; }
                if ($code) { $item=array('row'=>$r['row_no'],'code'=>$code); if ($r['state']==='skipped') { $warnings[]=$item; } else { $issues[]=$item; } }
            }
            if ($res && !empty($res['account_id']) && !Account::model()->findByPk($res['account_id'])) { $issues[]=array('row'=>$r['row_no'],'code'=>'ACCOUNT_MISSING'); }
            if ($res && !empty($res['contact_id'])) { $c=ContactPerson::model()->findByPk($res['contact_id']); if (!$c || (string)$c->account_id!==(string)$res['account_id']) { $issues[]=array('row'=>$r['row_no'],'code'=>'CONTACT_MISMATCH'); } }
            if ($res && !empty($res['task_id'])) { $t=Task::model()->findByPk($res['task_id']); if (!$t || $t->legacy_key!==$p['legacy_key'] || (int)$t->report_id!==(int)$p['report_id'] || (int)$t->account_id!==(int)$p['account_id'] || (int)$t->hotel_id!==(int)$p['hotel_id']) { $issues[]=array('row'=>$r['row_no'],'code'=>'TASK_MISMATCH'); } }
        }
        return array('batch_id'=>(int)$id,'status'=>$b['status'],'counts'=>$counts,'total'=>count($rows),'consistent'=>!$issues,'issues'=>$issues,'warnings'=>$warnings);
    }
}
