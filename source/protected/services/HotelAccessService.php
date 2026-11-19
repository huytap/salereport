<?php

class HotelAccessService
{
    public static function replace($userId, array $grants, $reason, $expectedHash=null)
    {
        ActionAccess::requireAction('users','hotelaccess');
        if (!ActionAccess::isAll()) { throw new CHttpException(403,'All scope is required to manage Hotel grants.'); }
        if (!is_scalar($userId) || !ctype_digit((string)$userId) || !($user=ActionAccess::user($userId))) { throw new CHttpException(404,'Active user not found.'); }
        if (!is_string($reason) || !trim($reason)) { throw new CHttpException(400,'A reason is required.'); }
        $desired=array();
        foreach ($grants as $grant) {
            if (!is_array($grant) || !isset($grant['hotel_id'],$grant['scope_level']) || !is_scalar($grant['hotel_id']) || !ctype_digit((string)$grant['hotel_id'])
                || !in_array($grant['scope_level'],array('own','hotel'),true) || isset($desired[(int)$grant['hotel_id']])) { throw new CHttpException(400,'Invalid or duplicate Hotel grant.'); }
            $hotel=Hotel::model()->findByPk($grant['hotel_id']);
            $existing=UserHotelAccess::model()->findByPk(array('user_id'=>$user->id,'hotel_id'=>$grant['hotel_id']));
            if (!$hotel || ($hotel->status!=='active' && (!$existing || $existing->scope_level!==$grant['scope_level']))) { throw new CHttpException(400,'Active Hotel is required for a new or changed grant.'); }
            $desired[(int)$hotel->id]=$grant['scope_level'];
        }
        $db=Yii::app()->db;
        $tx=$db->getCurrentTransaction();
        $ownsTransaction=!$tx || !$tx->active;
        $savepoint='hotel_access_'.bin2hex(random_bytes(8));
        if ($ownsTransaction) { $tx=$db->beginTransaction(); }
        else { $db->createCommand('SAVEPOINT '.$savepoint)->execute(); }
        try {
            // Stable Hotel lock ordering serializes concurrent full replacement on the same target.
            $db->createCommand('SELECT id FROM crm_hotel ORDER BY id FOR UPDATE')->queryAll();
            $before=$db->createCommand('SELECT hotel_id,scope_level FROM crm_user_hotel_access WHERE user_id=:id ORDER BY hotel_id FOR UPDATE')->queryAll(true,array(':id'=>$user->id));
            if ($expectedHash!==null && (!is_string($expectedHash) || !preg_match('/^[a-f0-9]{64}$/D',$expectedHash) || !hash_equals(hash('sha256',CJSON::encode($before)),$expectedHash))) { throw new CHttpException(409,'Hotel grants changed; reload before saving.'); }
            foreach ($before as $old) {
                if (isset($desired[(int)$old['hotel_id']])) { continue; }
                $owned=$db->createCommand("SELECT COUNT(*) FROM crm_account_hotel WHERE owner_user_id=:user AND hotel_id=:hotel AND status='active'")->queryScalar(array(':user'=>$user->id,':hotel'=>$old['hotel_id']));
                $open=$db->createCommand("SELECT COUNT(*) FROM crm_task WHERE assignee_user_id=:user AND hotel_id=:hotel AND status IN ('open','in_progress')")->queryScalar(array(':user'=>$user->id,':hotel'=>$old['hotel_id']));
                if ($owned || $open) { throw new CHttpException(409,'Hand over accounts/open tasks before revoking this Hotel.'); }
            }
            $db->createCommand()->delete('crm_user_hotel_access','user_id=:id',array(':id'=>$user->id));
            foreach ($desired as $hotelId=>$scope) {
                $db->createCommand()->insert('crm_user_hotel_access',array('user_id'=>$user->id,'hotel_id'=>$hotelId,'scope_level'=>$scope,'updated_by'=>Yii::app()->user->id,'updated_at_utc'=>gmdate('Y-m-d H:i:s')));
            }
            $event=new AuditEvent;
            $event->entity_type='user_hotel_access'; $event->entity_id=(string)$user->id; $event->event_type='replaced'; $event->actor_user_id=Yii::app()->user->id;
            $event->before_json=CJSON::encode($before); $event->after_json=CJSON::encode($desired); $event->reason=trim($reason); $event->request_id=bin2hex(random_bytes(16));
            if (!$event->save()) { throw new CDbException('Hotel grant audit could not be saved.'); }
            if ($ownsTransaction) { $tx->commit(); }
            else { $db->createCommand('RELEASE SAVEPOINT '.$savepoint)->execute(); }
        } catch (Exception $e) {
            if ($ownsTransaction) { $tx->rollback(); }
            else {
                $db->createCommand('ROLLBACK TO SAVEPOINT '.$savepoint)->execute();
                $db->createCommand('RELEASE SAVEPOINT '.$savepoint)->execute();
            }
            throw $e;
        }
    }
}
