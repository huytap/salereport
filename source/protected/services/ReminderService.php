<?php
class ReminderService
{
    public static function available()
    {
        return CustomerPolicy::ready() && Yii::app()->db->schema->getTable('crm_reminder_dispatch_run')!==null;
    }
    private static function authorize($action)
    {
        ActionAccess::requireAction('reminder',$action); ReminderDispatchService::ready();
    }
    private static function criteria($filter)
    {
        if (!in_array($filter,array('unread','read','all'),true)) { throw new CHttpException(400,'Bộ lọc thông báo không hợp lệ.'); }
        $c=CustomerPolicy::notificationCriteria();
        if ($filter!=='all') { $c->addCondition($filter==='unread'?'t.read_at_utc IS NULL':'t.read_at_utc IS NOT NULL'); } return $c;
    }
    private static function items($criteria,$limit,$offset=0)
    {
        $criteria->limit=$limit; $criteria->offset=$offset; $criteria->order='t.id DESC'; $criteria->with=array('task.account','task.hotel'); $data=array();
        foreach (Notification::model()->findAll($criteria) as $n) {
            $t=$n->task; $data[]=array('id'=>(int)$n->id,'task_id'=>(int)$t->id,'kind'=>$n->kind,'title'=>$t->title,'account_name'=>$t->account->name,'hotel_name'=>$t->hotel->name,'created_at_utc'=>$n->created_at_utc,'read_at_utc'=>$n->read_at_utc,
                'due_label'=>$t->due_mode==='date'?$t->due_date.' ('.$t->timezone.')':str_replace('T',' ',TaskSchedule::local($t->due_at_utc,$t->timezone)).' ('.$t->timezone.')',
                'overdue'=>$t->isOverdue(),'task_url'=>Yii::app()->createUrl('task/view',array('id'=>$t->id)));
        }
        return $data;
    }
    public static function inbox($filter='unread',$page=1)
    {
        self::authorize('admin'); $page=CustomerWriteSupport::id($page); $c=self::criteria($filter); $total=(int)Notification::model()->count($c);
        return array('items'=>self::items($c,25,($page-1)*25),'filter'=>$filter,'page'=>$page,'total'=>$total);
    }
    public static function summary()
    {
        self::authorize('summary'); $c=self::criteria('unread'); $count=(int)Notification::model()->count($c);
        $task=CustomerPolicy::scopeCriteria('task'); $task->compare('t.assignee_user_id',Yii::app()->user->id); $task->addInCondition('t.status',array('open','in_progress')); $task->addCondition('t.deadline_at_utc<=:now'); $task->params[':now']=gmdate('Y-m-d H:i:s');
        $overdue=ActionAccess::can('task','view')?(int)Task::model()->count($task):0;
        $result=array('unread_count'=>$count,'overdue_task_count'=>$overdue,'items'=>self::items($c,5),'inbox_url'=>Yii::app()->createUrl('reminder/admin'),'overdue_url'=>Yii::app()->createUrl('task/admin',array('tab'=>'overdue','assignee_user_id'=>Yii::app()->user->id)));
        if (ActionAccess::isAll() && ActionAccess::can('reminder','monitor')) { $result['monitor']=self::monitor(false); } return $result;
    }
    public static function read($id)
    {
        self::authorize('read'); $db=ReminderDispatchService::testDb(); $id=CustomerWriteSupport::id($id);
        if ($db->getCurrentTransaction()) { throw new CHttpException(409,'Đánh dấu đã đọc cần giao dịch độc lập.'); }
        $n=Notification::model()->findByPk($id,CustomerPolicy::notificationCriteria()); if (!$n) { throw new CHttpException(404,'Không có thông báo hợp lệ.'); }
        $tx=$db->beginTransaction();
        try {
            // Same ordering as dispatch. Do not lock Notification before Task/Reminder.
            $t=$db->createCommand('SELECT * FROM crm_task WHERE id=:id FOR UPDATE')->queryRow(true,array(':id'=>$n->task_id));
            $r=$db->createCommand('SELECT * FROM crm_task_reminder WHERE id=:id FOR UPDATE')->queryRow(true,array(':id'=>$n->reminder_id));
            $row=$db->createCommand('SELECT * FROM crm_notification WHERE id=:id FOR UPDATE')->queryRow(true,array(':id'=>$id));
            if (!$row || (int)$row['recipient_user_id']!==(int)Yii::app()->user->id || !$t || !$r || $r['state']!=='delivered' || !ReminderEligibility::notificationMatches($r,$row) || ReminderEligibility::reason($t,$r,gmdate('Y-m-d H:i:s'),false)!==null) { throw new CHttpException(404,'Thông báo không còn hợp lệ.'); }
            // Role/action may have changed after the pre-load.
            ActionAccess::requireAction('reminder','read');
            if ($row['read_at_utc']===null) { $row['read_at_utc']=gmdate('Y-m-d H:i:s'); $db->createCommand()->update('crm_notification',array('read_at_utc'=>$row['read_at_utc']),'id=:id AND read_at_utc IS NULL',array(':id'=>$id)); }
            $tx->commit(); return array('id'=>$id,'read_at_utc'=>$row['read_at_utc']);
        } catch (Exception $e) { if ($tx->active) { $tx->rollback(); } throw $e; }
    }
    public static function monitor($history=true)
    {
        self::authorize('monitor'); if (!ActionAccess::isAll()) { throw new CHttpException(403,'Chỉ All được xem giám sát toàn hệ thống.'); }
        $data=ReminderDispatchService::check(); $messages=array('DISABLED'=>'Dispatch đang tắt; bản test chưa bật cron.','NEVER_COMPLETED'=>'Worker chưa có lần chạy hoàn tất thành công.','STALE_HEARTBEAT'=>'Worker không hoàn tất trong hơn 3 phút.','STUCK_RUN'=>'Có lượt dispatch không cập nhật heartbeat trong hơn 3 phút.','BACKLOG'=>'Có reminder chờ quá mốc nhắc hơn 5 phút.','ERRORS'=>'Dispatch có lỗi cần kiểm tra.');
        $data['messages']=array(); foreach ($data['alerts'] as $code) { $data['messages'][]=$messages[$code]; }
        if ($history) { $data['recent_runs']=Yii::app()->db->createCommand('SELECT id,status,started_at_utc,finished_at_utc,duration_ms,created_count,recovered_count,cancelled_count,error_count,error_code FROM crm_reminder_dispatch_run ORDER BY id DESC LIMIT 20')->queryAll(); }
        return $data;
    }
}
