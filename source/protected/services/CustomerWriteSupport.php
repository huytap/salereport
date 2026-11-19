<?php

/** Shared lock, transaction, replay and audit rules for staged customer mutations. */
class CustomerWriteSupport
{
    public static function authorize($action)
    {
        ActionAccess::requireAction('account',$action);
        if (empty(Yii::app()->params['mvp']['customerWrite']) || !(Yii::app()->db instanceof IsolatedTestDbConnection)) {
            throw new CHttpException(503,'Ghi hồ sơ MVP hiện chỉ được bật trên DB test.');
        }
        $db=Yii::app()->db;
        if ($db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CHttpException(503,'Sai DB đích.'); }
    }
    public static function id($value,$zero=false)
    {
        if (!is_scalar($value) || is_bool($value) || !ctype_digit((string)$value) || (float)$value<($zero?0:1) || (float)$value>4294967295) { throw new CHttpException(400,'Mã hoặc phiên bản không hợp lệ.'); }
        return (int)$value;
    }
    public static function fields(array $values,array $allowed)
    {
        if (array_diff(array_keys($values),$allowed)) { throw new CHttpException(400,'Có trường không được phép sửa.'); }
        foreach ($values as &$value) {
            if ($value!==null && !is_scalar($value)) { throw new CHttpException(400,'Dữ liệu trường không hợp lệ.'); }
            if (is_string($value)) { $value=trim($value); }
        } unset($value);
        return $values;
    }
    public static function reason($value)
    {
        if (!is_string($value) || !trim($value) || mb_strlen($value)>4000) { throw new CustomerValidationException(array('reason'=>array('Cần lý do, tối đa 4.000 ký tự.'))); }
        return trim($value);
    }
    private static function canonical($value)
    {
        if (is_array($value)) { if (array_keys($value)!==range(0,count($value)-1)) { ksort($value); } foreach ($value as &$v) { $v=self::canonical($v); } unset($v); }
        return $value;
    }
    public static function mutate($operation,$key,array $payload,$callback,$replayGuard=null)
    {
        if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_-]{16,64}$/D',$key)) { throw new CHttpException(400,'Thiếu mã yêu cầu hợp lệ.'); }
        $db=Yii::app()->db; $actor=Yii::app()->user->id;
        $hash=hash('sha256',LegacyImportSource::json(self::canonical($payload)));
        $lock=CustomerImportService::lockName(); // Same mutex as the importer: name checks cannot race each other.
        if ((int)$db->createCommand('SELECT GET_LOCK(:key,0)')->queryScalar(array(':key'=>$lock))!==1) { throw new CHttpException(409,'Đang có thao tác hồ sơ khác, vui lòng thử lại.'); }
        $outer=$db->getCurrentTransaction(); $owns=!$outer || !$outer->active; $sp='customer_'.bin2hex(random_bytes(8));
        try {
            if ($owns) { $tx=$db->beginTransaction(); } else { $db->createCommand('SAVEPOINT '.$sp)->execute(); }
            try {
                $old=$db->createCommand('SELECT * FROM crm_customer_request WHERE actor_user_id=:actor AND request_key=:key FOR UPDATE')->queryRow(true,array(':actor'=>$actor,':key'=>$key));
                if ($old) {
                    if ($old['operation']!==$operation || !hash_equals($old['payload_hash'],$hash)) { throw new CHttpException(409,'Mã yêu cầu đã được dùng với nội dung khác.'); }
                    $result=json_decode($old['result_json'],true);
                    if (!Account::model()->findByPk($result['account_id'],CustomerPolicy::scopeCriteria('account'))) { throw new CHttpException(404,'Không tìm thấy hồ sơ.'); }
                    if ($replayGuard!==null) { $replayGuard($result); }
                    $result['replayed']=true;
                } else {
                    $result=$callback();
                    $db->createCommand()->insert('crm_customer_request',array('actor_user_id'=>$actor,'request_key'=>$key,'operation'=>$operation,'payload_hash'=>$hash,
                        'account_id'=>$result['account_id'],'result_json'=>LegacyImportSource::json($result),'created_at_utc'=>gmdate('Y-m-d H:i:s')));
                }
                if ($owns) { $tx->commit(); } else { $db->createCommand('RELEASE SAVEPOINT '.$sp)->execute(); }
                return $result;
            } catch (Exception $e) {
                if ($owns) { $tx->rollback(); } elseif ($db->getPdoInstance()->inTransaction()) { $db->createCommand('ROLLBACK TO SAVEPOINT '.$sp)->execute(); $db->createCommand('RELEASE SAVEPOINT '.$sp)->execute(); }
                throw $e;
            }
        } finally { $db->createCommand('SELECT RELEASE_LOCK(:key)')->queryScalar(array(':key'=>$lock)); }
    }
    public static function current($class,$table,$where,array $params)
    {
        $row=Yii::app()->db->createCommand('SELECT * FROM '.$table.' WHERE '.$where.' FOR UPDATE')->queryRow(true,$params);
        if (!$row) { throw new CHttpException(404,'Không tìm thấy bản ghi.'); }
        return $class::model()->populateRecord($row,true);
    }
    public static function hotel($id,$manage=false)
    {
        $id=self::id($id); $hotel=Hotel::model()->findByPk($id);
        if (!$hotel || $hotel->status!=='active') { throw new CHttpException(404,'Không tìm thấy khách sạn đang hoạt động.'); }
        if (!ActionAccess::isAll()) {
            $grant=UserHotelAccess::model()->findByPk(array('user_id'=>Yii::app()->user->id,'hotel_id'=>$id));
            if (!$grant) { throw new CHttpException(404,'Không tìm thấy khách sạn.'); }
            if ($manage && $grant->scope_level!=='hotel') { throw new CHttpException(403,'Cần quyền quản lý tại khách sạn này.'); }
        }
        return $hotel;
    }
    public static function lockHotels(array $ids)
    {
        $ids=array_values(array_unique(array_map(array(__CLASS__,'id'),$ids))); sort($ids,SORT_NUMERIC);
        foreach ($ids as $id) { self::current('Hotel','crm_hotel','id=:id',array(':id'=>$id)); }
    }
    public static function accountHotels($accountId)
    {
        return Yii::app()->db->createCommand('SELECT hotel_id FROM crm_account_hotel WHERE account_id=:id ORDER BY hotel_id')->queryColumn(array(':id'=>$accountId));
    }
    public static function owner($id,$hotelId)
    {
        if ($id===null) { return null; }
        $id=self::id($id);
        if (!ActionAccess::user($id)) { throw new CustomerValidationException(array('owner_user_id'=>array('Người phụ trách phải đang hoạt động.'))); }
        $member=UserHotelAccess::model()->exists('user_id=:user AND hotel_id=:hotel',array(':user'=>$id,':hotel'=>$hotelId));
        if (!$member && !(ActionAccess::isAll() && ActionAccess::isAll($id))) { throw new CustomerValidationException(array('owner_user_id'=>array('Người nhận chưa được cấp quyền tại khách sạn này.'))); }
        return $id;
    }
    public static function save(CActiveRecord $model)
    {
        if (!$model->save()) { throw new CustomerValidationException($model->errors); }
    }
    public static function version($model,$expected)
    {
        if ((int)$model->lock_version!==self::id($expected)) { throw new CHttpException(409,'Bản ghi đã thay đổi. Hãy tải lại trước khi lưu.'); }
    }
    public static function audit($type,$id,$event,$before,$after,$reason='')
    {
        $m=new AuditEvent; $m->entity_type=$type; $m->entity_id=(string)$id; $m->event_type=$event; $m->actor_user_id=Yii::app()->user->id;
        $m->before_json=$before===null?null:LegacyImportSource::json($before); $m->after_json=LegacyImportSource::json($after); $m->reason=$reason; $m->request_id=bin2hex(random_bytes(16)); self::save($m);
    }
}
