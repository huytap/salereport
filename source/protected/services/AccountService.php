<?php

class AccountService
{
    public static function accountFields() { return array('name','segment','biz_scope','website','phone','email','building','address_1','address_2','address_3','city','country','source','fit_series','dmc','notes'); }
    public static function personFields() { return array('title','first_name','last_name','position','email','phone','mobile','dob','contact_role','notes'); }
    private static function active($account)
    {
        if ($account->status!=='active') { throw new CHttpException(409,'Hồ sơ đã lưu trữ, không thể sửa hoặc thêm người liên hệ.'); }
    }
    private static function duplicate($name,$except=null,$allow=false,$reason='')
    {
        $criteria=new CDbCriteria; $criteria->compare('normalized_name',LegacyImportSource::normalize($name));
        if ($except) { $criteria->addCondition('id<>:except'); $criteria->params[':except']=$except; }
        if (Account::model()->exists($criteria)) {
            if (!$allow || !ActionAccess::isAll()) { throw new CHttpException(409,'Hồ sơ có thể đã tồn tại. Hãy tra cứu hoặc nhờ quản trị đối chiếu trước khi tạo trùng.'); }
            CustomerWriteSupport::reason($reason);
        }
    }
    private static function markets($values)
    {
        if (!is_array($values) || count($values)>20) { throw new CHttpException(400,'Danh sách thị trường không hợp lệ.'); }
        $out=array(); foreach ($values as $v) { if (!is_string($v) || !trim($v) || mb_strlen($v)>64) { throw new CHttpException(400,'Mã thị trường không hợp lệ.'); } $out[]=mb_strtoupper(trim($v),'UTF-8'); }
        $out=array_values(array_unique($out)); sort($out,SORT_STRING); return $out;
    }
    private static function setMarkets($id,array $markets)
    {
        $db=Yii::app()->db; $db->createCommand()->delete('crm_account_market','account_id=:id',array(':id'=>$id));
        foreach ($markets as $market) { $db->createCommand()->insert('crm_account_market',array('account_id'=>$id,'market_code'=>$market)); }
    }
    public static function create(array $fields,$hotelId,$ownerId,$personFields,$markets,$requestKey,$allowDuplicate=false,$reason='')
    {
        CustomerWriteSupport::authorize('create'); $fields=CustomerWriteSupport::fields($fields,self::accountFields());
        $hotelId=CustomerWriteSupport::id($hotelId); CustomerWriteSupport::hotel($hotelId);
        $ownerId=$ownerId===null || $ownerId===''?null:CustomerWriteSupport::id($ownerId); $markets=self::markets($markets);
        if ($personFields!==null) { if (!is_array($personFields)) { throw new CHttpException(400,'Người liên hệ không hợp lệ.'); } $personFields=CustomerWriteSupport::fields($personFields,self::personFields()); if (count(array_filter($personFields,function($v){return $v!==null && $v!=='';}))) { ActionAccess::requireAction('account','contactcreate'); } }
        $grant=UserHotelAccess::model()->findByPk(array('user_id'=>Yii::app()->user->id,'hotel_id'=>$hotelId));
        if (!ActionAccess::isAll() && $grant && $grant->scope_level==='own') {
            if ($ownerId!==null && (string)$ownerId!==(string)Yii::app()->user->id) { throw new CHttpException(403,'Sales chỉ được tạo khách cho chính mình.'); }
            $ownerId=(int)Yii::app()->user->id;
        }
        return CustomerWriteSupport::mutate('account_create',$requestKey,array($fields,$hotelId,$ownerId,$personFields,$markets,(bool)$allowDuplicate,$reason),function()use($fields,$hotelId,$ownerId,$personFields,$markets,$allowDuplicate,$reason){
            CustomerWriteSupport::lockHotels(array($hotelId)); ActionAccess::requireAction('account','create'); CustomerWriteSupport::hotel($hotelId);
            $currentGrant=UserHotelAccess::model()->findByPk(array('user_id'=>Yii::app()->user->id,'hotel_id'=>$hotelId));
            if (!ActionAccess::isAll() && $currentGrant->scope_level==='own') {
                if ($ownerId!==null && (string)$ownerId!==(string)Yii::app()->user->id) { throw new CHttpException(403,'Sales chỉ được tạo khách cho chính mình.'); }
                $ownerId=(int)Yii::app()->user->id;
            }
            if ($personFields && count(array_filter($personFields,function($v){return $v!==null && $v!=='';}))) { ActionAccess::requireAction('account','contactcreate'); }
            CustomerWriteSupport::owner($ownerId,$hotelId);
            self::duplicate(isset($fields['name'])?$fields['name']:'',null,$allowDuplicate,$reason);
            $m=new Account; $m->attributes=$fields; $m->code='ACC-'.strtoupper(bin2hex(random_bytes(8))); CustomerWriteSupport::save($m);
            $a=new AccountHotel; $a->account_id=$m->id; $a->hotel_id=$hotelId; $a->owner_user_id=$ownerId; CustomerWriteSupport::save($a);
            $person=null;
            if ($personFields && count(array_filter($personFields,function($v){return $v!==null && $v!=='';}))) {
                $person=new ContactPerson; $person->attributes=$personFields; $person->account_id=$m->id; CustomerWriteSupport::save($person);
                CustomerWriteSupport::audit('contact_person',$person->id,'created',null,$person->attributes);
            }
            self::setMarkets($m->id,$markets);
            CustomerWriteSupport::audit('account',$m->id,'created',null,array('profile'=>$m->attributes,'markets'=>$markets),$reason);
            CustomerWriteSupport::audit('account_hotel',$m->id.':'.$hotelId,'assigned',null,$a->attributes,$reason);
            return array('account_id'=>$m->id,'contact_id'=>$person?$person->id:null,'version'=>(int)$m->lock_version);
        });
    }
    public static function update($id,array $fields,$markets,$expected,$key,$allowDuplicate=false,$reason='')
    {
        CustomerWriteSupport::authorize('update'); $id=CustomerWriteSupport::id($id); CustomerPolicy::load('Account','account','account','update',$id);
        $fields=CustomerWriteSupport::fields($fields,self::accountFields()); $markets=self::markets($markets); $expected=CustomerWriteSupport::id($expected);
        return CustomerWriteSupport::mutate('account_update',$key,array($id,$fields,$markets,$expected,(bool)$allowDuplicate,$reason),function()use($id,$fields,$markets,$expected,$allowDuplicate,$reason){
            CustomerWriteSupport::lockHotels(CustomerWriteSupport::accountHotels($id));
            $m=CustomerWriteSupport::current('Account','crm_account','id=:id',array(':id'=>$id)); CustomerPolicy::assertRecord('account','account','update',$m);
            self::active($m); CustomerWriteSupport::version($m,$expected); $before=$m->attributes;
            if (isset($fields['name']) && LegacyImportSource::normalize($fields['name'])!==$m->normalized_name) { self::duplicate($fields['name'],$id,$allowDuplicate,$reason); }
            $beforeMarkets=Yii::app()->db->createCommand('SELECT market_code FROM crm_account_market WHERE account_id=:id ORDER BY market_code')->queryColumn(array(':id'=>$id));
            $m->attributes=$fields; CustomerWriteSupport::save($m); self::setMarkets($id,$markets);
            CustomerWriteSupport::audit('account',$id,'updated',array('profile'=>$before,'markets'=>$beforeMarkets),array('profile'=>$m->attributes,'markets'=>$markets),$reason);
            return array('account_id'=>$id,'version'=>(int)$m->lock_version);
        });
    }
    public static function person($accountId,$personId,array $fields,$expected,$key)
    {
        $action=$personId===null?'contactcreate':'contactupdate'; CustomerWriteSupport::authorize($action); $accountId=CustomerWriteSupport::id($accountId);
        CustomerPolicy::load('Account','account','account',$action,$accountId); $fields=CustomerWriteSupport::fields($fields,self::personFields());
        if ($personId!==null) { $personId=CustomerWriteSupport::id($personId); $expected=CustomerWriteSupport::id($expected); }
        return CustomerWriteSupport::mutate($action,$key,array($accountId,$personId,$fields,$expected),function()use($accountId,$personId,$fields,$expected,$action){
            CustomerWriteSupport::lockHotels(CustomerWriteSupport::accountHotels($accountId));
            $account=CustomerWriteSupport::current('Account','crm_account','id=:id',array(':id'=>$accountId)); CustomerPolicy::assertRecord('account','account',$action,$account); self::active($account);
            if ($personId===null) { $p=new ContactPerson; $p->account_id=$accountId; $before=null; }
            else { $p=CustomerWriteSupport::current('ContactPerson','crm_contact_person','id=:id AND account_id=:account',array(':id'=>$personId,':account'=>$accountId)); CustomerWriteSupport::version($p,$expected); if ($p->status!=='active') { throw new CHttpException(409,'Người liên hệ đã lưu trữ.'); } $before=$p->attributes; }
            $p->attributes=$fields; $name=LegacyImportSource::normalize($p->first_name.' '.$p->last_name);
            foreach (($before && $name===LegacyImportSource::normalize($before['first_name'].' '.$before['last_name']) ? array() : ContactPerson::model()->findAll('account_id=:id',array(':id'=>$accountId))) as $other) {
                if ((string)$other->id!==(string)$personId && $name!=='' && $name===LegacyImportSource::normalize($other->first_name.' '.$other->last_name)) { throw new CHttpException(409,'Có người liên hệ cùng họ tên. Hãy đối chiếu trước khi tạo thêm.'); }
            }
            CustomerWriteSupport::save($p); CustomerWriteSupport::audit('contact_person',$p->id,$personId===null?'created':'updated',$before,$p->attributes);
            return array('account_id'=>$accountId,'contact_id'=>$p->id,'version'=>(int)$p->lock_version);
        });
    }
    public static function archive($id,$personId,$expected,$reason,$key)
    {
        $action=$personId===null?'archive':'contactarchive'; CustomerWriteSupport::authorize($action); $id=CustomerWriteSupport::id($id); $reason=CustomerWriteSupport::reason($reason); $expected=CustomerWriteSupport::id($expected);
        $account=CustomerPolicy::load('Account','account','account',$action,$id,true);
        // Contact information is shared too: archiving requires control of all active Hotels.
        if ($personId!==null) { $personId=CustomerWriteSupport::id($personId); CustomerPolicy::assertRecord('account','account','archive',$account,true); }
        return CustomerWriteSupport::mutate($action,$key,array($id,$personId,$expected,$reason),function()use($id,$personId,$expected,$reason,$action){
            CustomerWriteSupport::lockHotels(CustomerWriteSupport::accountHotels($id)); $a=CustomerWriteSupport::current('Account','crm_account','id=:id',array(':id'=>$id));
            CustomerPolicy::assertRecord('account','account',$action,$a,true); if ($personId!==null) { CustomerPolicy::assertRecord('account','account','archive',$a,true); }
            $m=$personId===null?$a:CustomerWriteSupport::current('ContactPerson','crm_contact_person','id=:id AND account_id=:account',array(':id'=>$personId,':account'=>$id));
            CustomerWriteSupport::version($m,$expected);
            $c=new CDbCriteria; $c->compare('account_id',$id); $c->addInCondition('status',array('open','in_progress')); if ($personId!==null) { $c->compare('contact_person_id',$personId); }
            if (Task::model()->exists($c)) { throw new CHttpException(409,'Còn công việc đang mở, cần xử lý trước khi lưu trữ.'); }
            if ($m->status!=='active') { throw new CHttpException(409,'Bản ghi đã lưu trữ.'); }
            $before=$m->attributes; $m->status='archived'; CustomerWriteSupport::save($m); CustomerWriteSupport::audit($personId===null?'account':'contact_person',$personId===null?$id:$personId,'archived',$before,$m->attributes,$reason);
            return array('account_id'=>$id,'contact_id'=>$personId,'version'=>(int)$m->lock_version);
        });
    }
}
