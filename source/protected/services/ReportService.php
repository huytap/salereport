<?php

/** Canonical snapshot + Report + new Tasks are one replayable transaction. */
class ReportService
{
    public static function fields() { return array('date','sales_type','objective','expected_result','report','follow_up','action','new_account','comments','highlight'); }
    public static function ready()
    {
        AccountReadService::ready(); $schema=Yii::app()->db->schema->getTable('reports');
        foreach (array('snapshot_json','created_at_utc','updated_at_utc') as $name) { if (!$schema || !isset($schema->columns[$name])) { throw new CHttpException(503,'Chưa triển khai schema Sales Report MVP.'); } }
    }
    public static function authorize($action)
    {
        ActionAccess::requireAction('report',$action); CutoverService::canonicalWrite(); $db=Yii::app()->db;
        if (empty(Yii::app()->params['mvp']['reportWrite']) || !($db instanceof IsolatedTestDbConnection)
            || $db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CHttpException(503,'Ghi Report MVP chỉ được bật trên DB test.'); }
        self::ready();
    }
    public static function context($accountId,$hotelId,$action,$lock=false)
    {
        ActionAccess::requireAction('report',$action); $accountId=CustomerWriteSupport::id($accountId); $hotelId=CustomerWriteSupport::id($hotelId);
        if ($lock) {
            $hotels=CustomerWriteSupport::accountHotels($accountId); $hotels[]=$hotelId; CustomerWriteSupport::lockHotels($hotels);
            $hotel=CustomerWriteSupport::current('Hotel','crm_hotel','id=:id',array(':id'=>$hotelId));
            $account=CustomerWriteSupport::current('Account','crm_account','id=:id',array(':id'=>$accountId));
            $assignment=CustomerWriteSupport::current('AccountHotel','crm_account_hotel','account_id=:account AND hotel_id=:hotel',array(':account'=>$accountId,':hotel'=>$hotelId));
            if (!ActionAccess::isAll()) {
                $scope=Yii::app()->db->createCommand('SELECT scope_level FROM crm_user_hotel_access WHERE user_id=:user AND hotel_id=:hotel FOR UPDATE')->queryScalar(array(':user'=>Yii::app()->user->id,':hotel'=>$hotelId));
                if (!in_array($scope,array('own','hotel'),true) || ($scope==='own' && (string)$assignment->owner_user_id!==(string)Yii::app()->user->id)) { throw new CHttpException(404,'Không có quyền tại hồ sơ/khách sạn này.'); }
            }
            if ($hotel->status!=='active' || $account->status!=='active' || $assignment->status!=='active') { throw new CHttpException(409,'Hồ sơ hoặc khách sạn đã ngừng hoạt động.'); }
        } else {
            CustomerPolicy::assertAccountHotel($accountId,$hotelId,'report',$action);
            $hotel=CustomerWriteSupport::hotel($hotelId); $account=CustomerPolicy::load('Account','account','report',$action,$accountId);
            if ($account->status!=='active') { throw new CHttpException(409,'Hồ sơ khách hàng đã lưu trữ.'); }
            if (!ActionAccess::isAll()) { $grant=UserHotelAccess::model()->findByPk(array('user_id'=>Yii::app()->user->id,'hotel_id'=>$hotelId)); if (!$grant || !in_array($grant->scope_level,array('own','hotel'),true)) { throw new CHttpException(404,'Không có quyền tại khách sạn này.'); } }
            $assignment=AccountHotel::model()->findByPk(array('account_id'=>$accountId,'hotel_id'=>$hotelId));
        }
        return array($account,$hotel,$assignment);
    }
    private static function contact($id,$accountId,$lock)
    {
        if ($id===null || $id==='') { return null; } $id=CustomerWriteSupport::id($id);
        $person=$lock?CustomerWriteSupport::current('ContactPerson','crm_contact_person','id=:id AND account_id=:account',array(':id'=>$id,':account'=>$accountId)):ContactPerson::model()->findByPk($id);
        if (!$person || (string)$person->account_id!==(string)$accountId || $person->status!=='active') { throw new CustomerValidationException(array('contact_person_id'=>array('Chọn người liên hệ đang hoạt động thuộc hồ sơ này.'))); }
        return $person;
    }
    private static function newContact($fields,$accountId)
    {
        if ($fields===null || $fields===array()) { return null; }
        if (!is_array($fields)) { throw new CHttpException(400,'Người liên hệ mới không hợp lệ.'); }
        CustomerWriteSupport::authorize('contactcreate'); $fields=CustomerWriteSupport::fields($fields,AccountService::personFields());
        $p=new ContactPerson; $p->account_id=$accountId; $p->attributes=$fields;
        if (!$p->validate()) { $errors=array(); self::mergeErrors($errors,'new_contact',$p->errors); throw new CustomerValidationException($errors); }
        $name=LegacyImportSource::normalize($p->first_name.' '.$p->last_name);
        foreach (Yii::app()->db->createCommand('SELECT first_name,last_name FROM crm_contact_person WHERE account_id=:id FOR UPDATE')->queryAll(true,array(':id'=>$accountId)) as $other) {
            if ($name===LegacyImportSource::normalize($other['first_name'].' '.$other['last_name'])) { throw new CustomerValidationException(array('new_contact.first_name'=>array('Đã có người liên hệ cùng họ tên; hãy chọn bản ghi hiện có.'))); }
        }
        return $p;
    }
    public static function snapshot(Account $a,Hotel $hotel,AccountHotel $assignment,$person=null)
    {
        $tx=Yii::app()->db->getCurrentTransaction();
        $markets=Yii::app()->db->createCommand('SELECT market_code FROM crm_account_market WHERE account_id=:id ORDER BY market_code'.($tx && $tx->active?' FOR UPDATE':''))->queryColumn(array(':id'=>$a->id));
        $account=array(); foreach (array('id','code','name','segment','biz_scope','phone','email','building','address_1','address_2','address_3','city','country') as $name) { $account[$name]=$a->$name; } $account['markets']=$markets;
        $contact=null; if ($person) { $contact=array(); foreach (array('id','title','first_name','last_name','position','email','phone','mobile') as $name) { $contact[$name]=$person->$name; } }
        return array('version'=>1,'captured_at_utc'=>gmdate('Y-m-d H:i:s'),'account'=>$account,'contact'=>$contact,'hotel'=>array('id'=>$hotel->id,'code'=>$hotel->code,'name'=>$hotel->name,'timezone'=>$hotel->timezone),'owner_user_id'=>$assignment->owner_user_id);
    }
    public static function preview($accountId,$hotelId,$contactId=null)
    {
        self::ready(); list($account,$hotel,$assignment)=self::context($accountId,$hotelId,'create');
        return self::snapshot($account,$hotel,$assignment,self::contact($contactId,$accountId,false));
    }
    private static function snapshotColumns(array $s)
    {
        $a=$s['account']; $p=$s['contact']; $address=array(); foreach (array('building','address_1','address_2','address_3') as $key) { if (trim($a[$key])!=='') { $address[]=$a[$key]; } }
        return array('company_name'=>$a['name'],'biz_scope'=>$a['biz_scope'],'company_address'=>implode("\n",$address),'city'=>$a['city'],'country'=>$a['country'],
            'phone'=>$p && ($p['phone'] || $p['mobile'])?($p['phone'] ?: $p['mobile']):$a['phone'],'email'=>$p && $p['email']?$p['email']:$a['email'],
            'title'=>$p?$p['title']:'','first_name'=>$p?$p['first_name']:'','last_name'=>$p?$p['last_name']:'','position'=>$p?$p['position']:'',
            'account_type'=>$a['segment'],'market'=>implode(',',$a['markets']),'hotels'=>$s['hotel']['name']);
    }
    private static function validate(Report $m,$changed=null)
    {
        $errors=array();
        foreach (array('sales_type','objective','expected_result') as $key) {
            if ($changed!==null && !array_key_exists($key,$changed)) { continue; }
            $allowed=require Yii::app()->basePath.'/../confgs/'.$key.'.php'; if ($m->$key!==null && $m->$key!=='' && !in_array($m->$key,array_values($allowed),true)) { $errors[$key]=array('Giá trị không thuộc danh mục.'); }
        }
        if (($changed===null || array_key_exists('report',$changed)) && !trim((string)$m->report)) { $errors['report']=array('Cần nội dung cuộc tiếp xúc.'); }
        if (($changed===null || array_key_exists('new_account',$changed)) && !in_array((string)$m->new_account,array('','Yes','No'),true)) { $errors['new_account']=array('Chỉ nhận Yes/No hoặc để trống.'); }
        if (($changed===null || array_key_exists('highlight',$changed)) && !in_array((string)$m->highlight,array('0','1'),true)) { $errors['highlight']=array('Chỉ nhận 0 hoặc 1.'); }
        foreach (array('report','comments') as $name) { if (strlen((string)$m->$name)>65535) { $errors[$name]=array('Nội dung tối đa 65.535 byte.'); } }
        if (!$m->validate()) { $errors=array_merge($errors,$m->errors); }
        $out=array(); self::mergeErrors($out,'Report',$errors); return $out;
    }
    private static function mergeErrors(array &$errors,$prefix,array $fields)
    {
        foreach ($fields as $field=>$messages) { $errors[$prefix.'.'.$field]=$messages; }
    }
    private static function tasks(array $rows,$accountId,$hotelId,$contactId,$key,$operation,array &$errors)
    {
        if (count($rows)>20 || ($rows && array_keys($rows)!==range(0,count($rows)-1))) { throw new CHttpException(400,'Tối đa 20 việc mới, danh sách phải có thứ tự liên tục.'); }
        if ($rows) { TaskService::authorize('create'); }
        $prepared=array();
        foreach ($rows as $i=>$row) {
            if (!is_array($row) || array_diff(array_keys($row),array('fields','allow_overdue','reason','assignee_user_id')) || !isset($row['fields']) || !is_array($row['fields'])) { throw new CustomerValidationException(array('tasks.'.$i=>array('Dòng việc không hợp lệ.'))); }
            $fields=$row['fields']; if (!array_key_exists('contact_person_id',$fields)) { $fields['contact_person_id']=$contactId; }
            $allow=isset($row['allow_overdue'])?$row['allow_overdue']:false;
            if (!in_array($allow,array(false,true,0,1,'0','1',''),true)) { throw new CustomerValidationException(array('tasks.'.$i.'.allow_overdue'=>array('Xác nhận quá hạn không hợp lệ.'))); }
            $allow=$allow===true || $allow===1 || $allow==='1'; $reason=isset($row['reason'])?$row['reason']:'';
            if (!is_string($reason) || mb_strlen($reason)>4000) { throw new CustomerValidationException(array('tasks.'.$i.'.reason'=>array('Lý do tối đa 4.000 ký tự.'))); }
            $assignee=isset($row['assignee_user_id']) && $row['assignee_user_id']!==''?CustomerWriteSupport::id($row['assignee_user_id']):null;
            $childKey=hash('sha256','report|'.Yii::app()->user->id.'|'.$operation.'|'.$key.'|'.$i);
            try { $prepared[$i]=array('model'=>TaskService::prepareCreate($accountId,$hotelId,$fields,$childKey,$allow,$reason,$assignee),'allow'=>$allow,'reason'=>$reason,'inherit'=>!array_key_exists('contact_person_id',$row['fields'])); }
            catch (CustomerValidationException $e) { self::mergeErrors($errors,'tasks.'.$i,$e->errors); }
            catch (CHttpException $e) { if (!in_array($e->statusCode,array(400,409),true)) { throw $e; } $errors['tasks.'.$i]=array($e->getMessage()); }
        }
        return $prepared;
    }
    private static function saveTasks(array $prepared,$reportId,$contactId)
    {
        $result=array(); foreach ($prepared as $i=>$item) {
            $m=$item['model']; $m->report_id=$reportId; if ($item['inherit']) { $m->contact_person_id=$contactId; }
            try { $result[]=TaskService::persistPrepared($m,$item['allow'],$item['reason']); }
            catch (CustomerValidationException $e) { $errors=array(); self::mergeErrors($errors,'tasks.'.$i,$e->errors); throw new CustomerValidationException($errors); }
        } return $result;
    }
    private static function mutate($operation,$key,array $payload,$callback,$guard)
    {
        $outer=Yii::app()->db->getCurrentTransaction();
        for ($attempt=0; ; $attempt++) {
            try { return CustomerWriteSupport::mutate($operation,$key,$payload,$callback,$guard); }
            catch (CDbException $e) { $vendor=isset($e->errorInfo[1])?(int)$e->errorInfo[1]:0; if ($outer || $attempt>=2 || !in_array($vendor,array(1205,1213),true)) { throw $e; } }
        }
    }
    private static function replay(array $result,$action)
    {
        $m=CustomerPolicy::load('Report','report','report',$action,$result['report_id']); self::context($m->account_id,$m->hotel_id,$action,true);
    }
    private static function auditDates(Report $m,$create)
    {
        $now=gmdate('Y-m-d H:i:s'); $local=(new DateTimeImmutable($now,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d H:i:s');
        $m->updated_by=Yii::app()->user->id; $m->updated_date=$local; $m->updated_at_utc=$now;
        if ($create) { $m->added_by=Yii::app()->user->id; $m->added_date=$local; $m->created_at_utc=$now; }
    }
    public static function create($accountId,$hotelId,$contactId,array $fields,array $tasks,$key,$newContact=null)
    {
        self::authorize('create'); $accountId=CustomerWriteSupport::id($accountId); $hotelId=CustomerWriteSupport::id($hotelId); $contactId=$contactId===null || $contactId===''?null:CustomerWriteSupport::id($contactId);
        if ($contactId!==null && $newContact) { throw new CHttpException(400,'Chỉ chọn liên hệ hiện có hoặc tạo liên hệ mới.'); }
        $fields=CustomerWriteSupport::fields($fields,self::fields()); self::context($accountId,$hotelId,'create');
        return self::mutate('report_create',$key,array($accountId,$hotelId,$contactId,$fields,$tasks,$newContact),function()use($accountId,$hotelId,$contactId,$fields,$tasks,$key,$newContact){
            self::authorize('create'); list($account,$hotel,$assignment)=self::context($accountId,$hotelId,'create',true);
            $person=self::contact($contactId,$accountId,true); $quick=self::newContact($newContact,$accountId); if ($quick) { $person=$quick; }
            $snapshot=self::snapshot($account,$hotel,$assignment,$person); $m=new Report; $m->setAttributes(array_merge(self::snapshotColumns($snapshot),$fields),false);
            $m->account_id=$accountId; $m->hotel_id=$hotelId; $m->contact_person_id=$contactId; $m->identity_state='linked'; $m->client_request_key=$key; $m->lock_version=1;
            $m->ent=null; $m->sales=null; $m->site_ins=null; $m->done=null; $m->highlight=isset($fields['highlight'])?$fields['highlight']:0;
            $errors=self::validate($m); $prepared=self::tasks($tasks,$accountId,$hotelId,$contactId,$key,'create',$errors);
            if ($errors) { throw new CustomerValidationException($errors); }
            if ($quick) {
                $created=AccountService::person($accountId,null,$newContact,null,hash('sha256','report-contact|'.Yii::app()->user->id.'|'.$key));
                $m->contact_person_id=$created['contact_id']; $snapshot['contact']['id']=$created['contact_id'];
            }
            $m->snapshot_json=LegacyImportSource::json($snapshot); self::auditDates($m,true);
            if (!$m->save(false)) { throw new CDbException('Report insert failed.'); }
            $createdTasks=self::saveTasks($prepared,$m->id,$m->contact_person_id);
            CustomerWriteSupport::audit('report',$m->id,'created',null,$m->attributes);
            return array('account_id'=>$accountId,'report_id'=>$m->id,'version'=>1,'contact_id'=>$m->contact_person_id,'tasks'=>$createdTasks);
        },function($result){ self::replay($result,'create'); });
    }
    public static function editDeadline(Report $m)
    {
        try {
            $tz=new DateTimeZone('Asia/Ho_Chi_Minh');
            if ($m->hasAttribute('created_at_utc') && $m->created_at_utc) {
                if (!MvpActiveRecord::validDate($m->created_at_utc,'Y-m-d H:i:s')) { return null; }
                $created=(new DateTimeImmutable($m->created_at_utc,new DateTimeZone('UTC')))->setTimezone($tz);
            } else {
                if (!MvpActiveRecord::validDate($m->added_date,'Y-m-d H:i:s')) { return null; }
                $created=new DateTimeImmutable($m->added_date,$tz);
            }
            if ($created>new DateTimeImmutable('now',$tz)) { return null; }
            return $created->setTime(0,0)->modify('+8 days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Exception $e) { return null; }
    }
    public static function canEdit(Report $m,$nowUtc=null)
    {
        $end=self::editDeadline($m); return $end!==null && ($nowUtc ?: gmdate('Y-m-d H:i:s'))<$end;
    }
    public static function update($id,array $fields,array $tasks,$expected,$key)
    {
        self::authorize('update'); $id=CustomerWriteSupport::id($id); $expected=CustomerWriteSupport::id($expected); $fields=CustomerWriteSupport::fields($fields,self::fields());
        $initial=CustomerPolicy::load('Report','report','report','update',$id);
        if ($initial->identity_state!=='linked' || !$initial->account_id || !$initial->hotel_id) { throw new CHttpException(409,'Cần mapping Report cũ trước khi chỉnh sửa.'); }
        return self::mutate('report_update',$key,array($id,$fields,$tasks,$expected),function()use($id,$fields,$tasks,$expected,$key,$initial){
            self::authorize('update'); self::context($initial->account_id,$initial->hotel_id,'update',true);
            $m=CustomerWriteSupport::current('Report','reports','id=:id',array(':id'=>$id));
            if ($m->identity_state!=='linked' || (string)$m->account_id!==(string)$initial->account_id || (string)$m->hotel_id!==(string)$initial->hotel_id) { throw new CHttpException(409,'Mapping báo cáo đã thay đổi.'); }
            CustomerWriteSupport::version($m,$expected);
            if (!self::canEdit($m)) { throw new CHttpException(409,'Báo cáo đã khóa sau 7 ngày. Công việc được xử lý độc lập.'); }
            $before=$m->attributes; $m->setAttributes($fields,false); $errors=self::validate($m,$fields);
            $prepared=self::tasks($tasks,$m->account_id,$m->hotel_id,$m->contact_person_id,$key,'update:'.$id,$errors);
            if ($errors) { throw new CustomerValidationException($errors); }
            self::auditDates($m,false); $values=$fields; $values['updated_by']=$m->updated_by; $values['updated_date']=$m->updated_date; $values['updated_at_utc']=$m->updated_at_utc; $values['lock_version']=$expected+1;
            if (Yii::app()->db->createCommand()->update('reports',$values,'id=:id AND lock_version=:version',array(':id'=>$id,':version'=>$expected))!==1) { throw new CHttpException(409,'Báo cáo vừa được thay đổi.'); }
            $m->lock_version=$expected+1; $newTasks=self::saveTasks($prepared,$m->id,$m->contact_person_id); CustomerWriteSupport::audit('report',$id,'updated',$before,$m->attributes);
            return array('account_id'=>$m->account_id,'report_id'=>$id,'version'=>$expected+1,'contact_id'=>$m->contact_person_id,'tasks'=>$newTasks);
        },function($result){ self::replay($result,'update'); });
    }
    public static function detail(Report $m,$page=1,$auditPage=1)
    {
        $page=CustomerWriteSupport::id($page); $tasks=array(); $total=0;
        if (ActionAccess::can('task','view') || ActionAccess::can('task','admin')) { $c=CustomerPolicy::scopeCriteria('task'); $c->compare('t.report_id',$m->id); $total=(int)Task::model()->count($c); $c->limit=25; $c->offset=($page-1)*25; $c->order='t.id'; $tasks=Task::model()->findAll($c); }
        $auditPage=CustomerWriteSupport::id($auditPage); $audit=new CDbCriteria; $audit->compare('entity_type','report'); $audit->compare('entity_id',(string)$m->id); $auditTotal=(int)AuditEvent::model()->count($audit); $audit->order='t.id DESC'; $audit->limit=25; $audit->offset=($auditPage-1)*25;
        return array('model'=>$m,'tasks'=>$tasks,'page'=>$page,'total'=>$total,'canEdit'=>self::canEdit($m),'audit'=>AuditEvent::model()->findAll($audit),'auditPage'=>$auditPage,'auditTotal'=>$auditTotal);
    }
}
