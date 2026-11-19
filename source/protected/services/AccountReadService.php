<?php

/** Build scoped page data without traversing unrestricted Account relations. */
class AccountReadService
{
    public static function ready()
    {
        if (!CustomerPolicy::ready() || !Yii::app()->db->schema->getTable('crm_customer_request')) { throw new CHttpException(503,'Chưa triển khai đầy đủ schema hồ sơ khách hàng trên môi trường này.'); }
    }
    public static function text($value,$max=255)
    {
        if (!is_string($value) || mb_strlen($value)>$max) { throw new CHttpException(400,'Bộ lọc không hợp lệ.'); }
        return trim($value);
    }
    public static function hotels($manage=false)
    {
        $c=new CDbCriteria; $c->compare('t.status','active'); $c->order='t.name,t.id';
        if (!ActionAccess::isAll()) {
            $c->addCondition("EXISTS (SELECT 1 FROM crm_user_hotel_access hg WHERE hg.hotel_id=t.id AND hg.user_id=:uid".($manage?" AND hg.scope_level='hotel'":'').')'); $c->params[':uid']=Yii::app()->user->id;
        }
        return Hotel::model()->findAll($c);
    }
    public static function owners($hotelId)
    {
        CustomerWriteSupport::hotel($hotelId);
        $grant=UserHotelAccess::model()->findByPk(array('user_id'=>Yii::app()->user->id,'hotel_id'=>$hotelId));
        if (!ActionAccess::isAll() && $grant->scope_level==='own') { return array(ActionAccess::user()); }
        $c=new CDbCriteria; $c->compare('t.status','A'); $c->order='t.fullname,t.id';
        $c->addCondition('EXISTS (SELECT 1 FROM crm_user_hotel_access og WHERE og.user_id=t.id AND og.hotel_id=:hotel)'); $c->params[':hotel']=$hotelId;
        $users=Users::model()->findAll($c);
        if (ActionAccess::isAll()) {
            foreach (Users::model()->findAll("status='A'") as $user) { if (ActionAccess::isAll($user->id) && !in_array($user->id,CHtml::listData($users,'id','id'))) { $users[]=$user; } }
        }
        return $users;
    }
    public static function listing(array $filters,$page)
    {
        self::ready(); ActionAccess::requireAction('account','admin'); $page=CustomerWriteSupport::id($page);
        $f=array_merge(array('q'=>'','hotel_id'=>'','owner_user_id'=>'','status'=>'active','segment'=>''),$filters);
        foreach ($f as $key=>$v) { $f[$key]=self::text($v); }
        if (!in_array($f['status'],array('','active','archived'),true) || !in_array($f['segment'],array('','LEISURE','CORPORATE','OTA'),true)) { throw new CHttpException(400,'Trạng thái/phân khúc không hợp lệ.'); }
        $c=CustomerPolicy::criteria('account','account','admin'); $c->compare('t.status',$f['status']); $c->compare('t.segment',$f['segment']);
        if ($f['q']!=='') {
            $search=new CDbCriteria; $search->compare('t.name',$f['q'],true,'OR'); $search->compare('t.code',$f['q'],true,'OR'); $search->compare('t.email',$f['q'],true,'OR'); $search->compare('t.phone',$f['q'],true,'OR');
            $search->addCondition("EXISTS (SELECT 1 FROM crm_contact_person cp WHERE cp.account_id=t.id AND (CONCAT(cp.first_name,' ',cp.last_name) LIKE :q OR cp.email LIKE :qEmail OR cp.phone LIKE :qPhone OR cp.mobile LIKE :qMobile))",'OR');
            $search->params[':q']=$search->params[':qEmail']=$search->params[':qPhone']=$search->params[':qMobile']='%'.strtr($f['q'],array('%'=>'\\%','_'=>'\\_','\\'=>'\\\\')).'%'; $c->mergeWith($search);
        }
        if ($f['hotel_id']!=='' || $f['owner_user_id']!=='') {
            $condition="EXISTS (SELECT 1 FROM crm_account_hotel af WHERE af.account_id=t.id AND af.status='active'";
            if ($f['hotel_id']!=='') { $c->params[':filterHotel']=CustomerWriteSupport::id($f['hotel_id']); $condition.=' AND af.hotel_id=:filterHotel'; }
            if ($f['owner_user_id']!=='') { $c->params[':filterOwner']=CustomerWriteSupport::id($f['owner_user_id']); $condition.=' AND af.owner_user_id=:filterOwner'; }
            // A profile shared with another Hotel must not reveal its hidden assignment through filters.
            if (!ActionAccess::isAll()) { $condition.=" AND EXISTS (SELECT 1 FROM crm_user_hotel_access fg WHERE fg.hotel_id=af.hotel_id AND fg.user_id=:filterActor AND (fg.scope_level='hotel' OR af.owner_user_id=:filterSelf))"; $c->params[':filterActor']=Yii::app()->user->id; $c->params[':filterSelf']=Yii::app()->user->id; }
            $c->addCondition($condition.')');
        }
        $total=(int)Account::model()->count($c); $c->limit=25; $c->offset=($page-1)*25; $c->order='t.name,t.id';
        return array('accounts'=>Account::model()->findAll($c),'filters'=>$f,'total'=>$total,'page'=>$page,'hotels'=>self::hotels(),'owners'=>Users::model()->findAll(CustomerPolicy::visibleUsersCriteria()));
    }
    public static function assignments($id)
    {
        $sql="SELECT ah.*,h.name AS hotel_name,u.fullname AS owner_name FROM crm_account_hotel ah INNER JOIN crm_hotel h ON h.id=ah.hotel_id LEFT JOIN users u ON u.id=ah.owner_user_id WHERE ah.account_id=:id AND ah.status='active'";
        $params=array(':id'=>$id);
        if (!ActionAccess::isAll()) { $sql.=" AND EXISTS (SELECT 1 FROM crm_user_hotel_access ag WHERE ag.hotel_id=ah.hotel_id AND ag.user_id=:actor AND (ag.scope_level='hotel' OR ah.owner_user_id=:owner))"; $params[':actor']=Yii::app()->user->id; $params[':owner']=Yii::app()->user->id; }
        return Yii::app()->db->createCommand($sql.' ORDER BY h.name,ah.hotel_id')->queryAll(true,$params);
    }
    public static function detail($id,$tab,$page)
    {
        self::ready(); $account=CustomerPolicy::load('Account','account','account','view',$id); $page=CustomerWriteSupport::id($page);
        if (!in_array($tab,array('overview','contacts','reports','tasks','sources','audit'),true)) { throw new CHttpException(400,'Tab không hợp lệ.'); }
        if (in_array($tab,array('sources','audit'),true) && !ActionAccess::isAll()) { throw new CHttpException(403,'Chỉ All được xem dữ liệu nguồn và audit tổng thể.'); }
        $data=array('account'=>$account,'tab'=>$tab,'page'=>$page,'rows'=>array(),'total'=>0,'assignments'=>self::assignments($id));
        $data['markets']=Yii::app()->db->createCommand('SELECT market_code FROM crm_account_market WHERE account_id=:id ORDER BY market_code')->queryColumn(array(':id'=>$id));
        if (in_array($tab,array('contacts','reports','tasks'),true)) {
            $map=array('contacts'=>array('ContactPerson','person'),'reports'=>array('Report','report'),'tasks'=>array('Task','task')); list($class,$type)=$map[$tab];
            if ($tab!=='contacts') { ActionAccess::requireAction($type,'admin'); }
            $c=CustomerPolicy::scopeCriteria($type); $c->compare('t.account_id',$id); $data['total']=(int)$class::model()->count($c); $c->order='t.id DESC'; $c->limit=25; $c->offset=($page-1)*25; $data['rows']=$class::model()->findAll($c);
        } elseif ($tab==='sources') {
            $c=new CDbCriteria; $c->compare('account_id',$id); $data['total']=(int)LegacyLink::model()->count($c); $c->limit=25; $c->offset=($page-1)*25; $c->order='source_type,source_id'; $data['rows']=LegacyLink::model()->findAll($c);
        } elseif ($tab==='audit') {
            $c=new CDbCriteria; $c->addCondition("(entity_type='account' AND entity_id=:id) OR (entity_type='account_hotel' AND entity_id LIKE :pair) OR (entity_type='contact_person' AND entity_id IN (SELECT CAST(id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci FROM crm_contact_person WHERE account_id=:account)) OR (entity_type='task' AND entity_id IN (SELECT CAST(id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci FROM crm_task WHERE account_id=:taskAccount))");
            $c->params=array(':id'=>(string)$id,':pair'=>$id.':%',':account'=>$id,':taskAccount'=>$id); $data['total']=(int)AuditEvent::model()->count($c); $c->limit=25; $c->offset=($page-1)*25; $c->order='t.id DESC'; $data['rows']=AuditEvent::model()->findAll($c);
        }
        return $data;
    }
}
