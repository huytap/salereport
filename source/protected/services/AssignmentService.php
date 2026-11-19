<?php

class AssignmentService
{
    private static function access($accountId,$hotelId,$action)
    {
        ActionAccess::requireAction('account',$action);
        $account=Account::model()->findByPk($accountId,CustomerPolicy::scopeCriteria('account'));
        if (!$account) { throw new CHttpException(404,'Không tìm thấy hồ sơ.'); }
        CustomerWriteSupport::hotel($hotelId,true);
        $assignment=AccountHotel::model()->findByPk(array('account_id'=>$accountId,'hotel_id'=>$hotelId));
        if ($assignment) { CustomerPolicy::assertAccountHotel($accountId,$hotelId,'account',$action,true); }
        elseif ($account->status!=='active') { throw new CHttpException(409,'Không thể thêm khách sạn cho hồ sơ đã lưu trữ.'); }
        return $assignment;
    }
    private static function tasks($accountId,$hotelId,$lock=false)
    {
        return Yii::app()->db->createCommand("SELECT id,title,assignee_user_id,lock_version,schedule_version FROM crm_task WHERE account_id=:account AND hotel_id=:hotel AND status IN ('open','in_progress') ORDER BY id".($lock?' FOR UPDATE':''))->queryAll(true,array(':account'=>$accountId,':hotel'=>$hotelId));
    }
    private static function hash($accountId,$hotelId,$owner,$version,array $tasks)
    {
        return hash('sha256',LegacyImportSource::json(array((string)$accountId,(string)$hotelId,$owner===null?null:(string)$owner,(string)$version,$tasks)));
    }
    public static function preview($accountId,$hotelId)
    {
        $accountId=CustomerWriteSupport::id($accountId); $hotelId=CustomerWriteSupport::id($hotelId);
        $a=self::access($accountId,$hotelId,'assignpreview'); $tasks=self::tasks($accountId,$hotelId);
        $owner=$a?$a->owner_user_id:null; $version=$a?(int)$a->lock_version:0;
        return array('account_id'=>$accountId,'hotel_id'=>$hotelId,'owner_user_id'=>$owner,'expected_version'=>$version,'tasks'=>$tasks,'plan_hash'=>self::hash($accountId,$hotelId,$owner,$version,$tasks));
    }
    public static function handover($accountId,$hotelId,$ownerId,$expected,$planHash,$reason,$key)
    {
        CustomerWriteSupport::authorize('assign'); $accountId=CustomerWriteSupport::id($accountId); $hotelId=CustomerWriteSupport::id($hotelId);
        $ownerId=$ownerId===null || $ownerId===''?null:CustomerWriteSupport::id($ownerId); $expected=CustomerWriteSupport::id($expected,true);
        if (!is_string($planHash) || !preg_match('/^[a-f0-9]{64}$/D',$planHash)) { throw new CHttpException(400,'Cần xem trước bàn giao.'); }
        $reason=CustomerWriteSupport::reason($reason); self::access($accountId,$hotelId,'assign');
        return CustomerWriteSupport::mutate('account_assign',$key,array($accountId,$hotelId,$ownerId,$expected,$planHash,$reason),function()use($accountId,$hotelId,$ownerId,$expected,$planHash,$reason){
            CustomerWriteSupport::lockHotels(array($hotelId));
            CustomerWriteSupport::current('Account','crm_account','id=:id',array(':id'=>$accountId));
            $a=self::access($accountId,$hotelId,'assign'); CustomerWriteSupport::owner($ownerId,$hotelId);
            if ($a) { $a=CustomerWriteSupport::current('AccountHotel','crm_account_hotel','account_id=:account AND hotel_id=:hotel',array(':account'=>$accountId,':hotel'=>$hotelId)); }
            $tasks=self::tasks($accountId,$hotelId,true); $version=$a?(int)$a->lock_version:0;
            if ($version!==$expected || !hash_equals(self::hash($accountId,$hotelId,$a?$a->owner_user_id:null,$version,$tasks),$planHash)) { throw new CHttpException(409,'Phân công hoặc danh sách việc đã thay đổi. Hãy xem trước lại.'); }
            if ($ownerId===null && $tasks) { throw new CHttpException(409,'Cần người nhận để bàn giao các việc đang mở.'); }
            $before=$a?$a->attributes:null;
            if ($a && (string)$a->owner_user_id===(string)$ownerId) { return array('account_id'=>$accountId,'hotel_id'=>$hotelId,'version'=>$version,'moved_tasks'=>0); }
            if (!$a) { $a=new AccountHotel; $a->account_id=$accountId; $a->hotel_id=$hotelId; }
            $a->owner_user_id=$ownerId; CustomerWriteSupport::save($a);
            $now=gmdate('Y-m-d H:i:s'); $db=Yii::app()->db;
            foreach ($tasks as $row) {
                $t=CustomerWriteSupport::current('Task','crm_task','id=:id',array(':id'=>$row['id'])); $old=$t->attributes;
                $t->assignee_user_id=$ownerId; $t->schedule_version=(int)$t->schedule_version+1; CustomerWriteSupport::save($t);
                $db->createCommand()->update('crm_task_reminder',array('state'=>'cancelled'),"task_id=:id AND state='pending'",array(':id'=>$t->id));
                foreach (TaskReminderPlanner::plan($t,$now) as $kind=>$at) {
                    $r=new TaskReminder; $r->task_id=$t->id; $r->recipient_user_id=$ownerId; $r->schedule_version=$t->schedule_version; $r->reminder_kind=$kind; $r->notify_at_utc=$at; CustomerWriteSupport::save($r);
                }
                CustomerWriteSupport::audit('task',$t->id,'handed_over',$old,$t->attributes,$reason);
            }
            CustomerWriteSupport::audit('account_hotel',$accountId.':'.$hotelId,'handed_over',$before,$a->attributes,$reason);
            return array('account_id'=>$accountId,'hotel_id'=>$hotelId,'version'=>(int)$a->lock_version,'moved_tasks'=>count($tasks));
        });
    }
}
