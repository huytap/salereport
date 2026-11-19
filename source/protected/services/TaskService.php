<?php

/** Every task write locks its Hotel/Account/assignment before the Task/reminders. */
class TaskService
{
    public static function contentFields() { return array('title','description','action_type','priority','location','contact_person_id'); }
    public static function scheduleFields() { return array('kind','due_mode','due_date','due_at_utc','timezone','starts_at_utc','ends_at_utc'); }
    public static function authorize($action)
    {
        ActionAccess::requireAction('task',$action);
        $db=Yii::app()->db;
        if (empty(Yii::app()->params['mvp']['taskWrite']) || !($db instanceof IsolatedTestDbConnection)
            || $db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CHttpException(503,'Ghi Task chỉ được bật trên DB test.'); }
        AccountReadService::ready();
    }
    private static function mutate($operation,$key,array $payload,$callback,$guard)
    {
        $outer=Yii::app()->db->getCurrentTransaction();
        for ($attempt=0; ; $attempt++) {
            try { return CustomerWriteSupport::mutate($operation,$key,$payload,$callback,$guard); }
            catch (CDbException $e) {
                $vendor=isset($e->errorInfo[1])?(int)$e->errorInfo[1]:0;
                if ($outer || $attempt>=2 || !in_array($vendor,array(1205,1213),true)) { throw $e; }
            }
        }
    }
    private static function access($id,$action)
    {
        $m=CustomerPolicy::load('Task','task','task',$action,$id);
        CustomerPolicy::assertRecord('task','task',$action,$m,$action==='reopen' || (string)$m->assignee_user_id!==(string)Yii::app()->user->id);
        return $m;
    }
    private static function context($accountId,$hotelId,$action,$lock=false)
    {
        if ($lock) {
            CustomerWriteSupport::lockHotels(array($hotelId));
            $hotel=CustomerWriteSupport::current('Hotel','crm_hotel','id=:id',array(':id'=>$hotelId));
            $account=CustomerWriteSupport::current('Account','crm_account','id=:id',array(':id'=>$accountId));
            if ($hotel->status!=='active') { throw new CHttpException(409,'Khách sạn không còn hoạt động.'); }
            if (!in_array($action,array('complete','cancel'),true) && $account->status!=='active') { throw new CHttpException(409,'Hồ sơ khách hàng đã lưu trữ.'); }
            $a=CustomerWriteSupport::current('AccountHotel','crm_account_hotel','account_id=:account AND hotel_id=:hotel',array(':account'=>$accountId,':hotel'=>$hotelId));
        } else { $a=AccountHotel::model()->findByPk(array('account_id'=>$accountId,'hotel_id'=>$hotelId)); }
        if ($lock && !ActionAccess::isAll()) {
            // Locking reads bypass an older REPEATABLE READ snapshot in a caller's transaction.
            $grant=Yii::app()->db->createCommand('SELECT scope_level FROM crm_user_hotel_access WHERE user_id=:user AND hotel_id=:hotel FOR UPDATE')->queryScalar(array(':user'=>Yii::app()->user->id,':hotel'=>$hotelId));
            if (!$grant || $a->status!=='active' || ($grant==='own' && (string)$a->owner_user_id!==(string)Yii::app()->user->id)) { throw new CHttpException(404,'Không còn quyền tại hồ sơ/khách sạn này.'); }
            if ($action==='reopen' && $grant!=='hotel') { throw new CHttpException(403,'Cần quyền quản lý để mở lại.'); }
        }
        CustomerWriteSupport::hotel($hotelId); CustomerPolicy::assertAccountHotel($accountId,$hotelId,'task',$action,$action==='reopen');
        return $a;
    }
    private static function owner($id,$hotelId)
    {
        $id=CustomerWriteSupport::id($id);
        if (!ActionAccess::user($id)) { throw new CustomerValidationException(array('assignee_user_id'=>array('Owner phải đang hoạt động.'))); }
        $grant=Yii::app()->db->createCommand('SELECT scope_level FROM crm_user_hotel_access WHERE user_id=:user AND hotel_id=:hotel FOR UPDATE')->queryScalar(array(':user'=>$id,':hotel'=>$hotelId));
        if (!$grant && !(ActionAccess::isAll() && ActionAccess::isAll($id))) { throw new CustomerValidationException(array('assignee_user_id'=>array('Owner không còn quyền tại khách sạn này.'))); }
    }
    private static function contact(Task $task)
    {
        if ($task->contact_person_id===null || $task->contact_person_id==='') { return; }
        $id=CustomerWriteSupport::id($task->contact_person_id);
        $row=Yii::app()->db->createCommand('SELECT account_id,status FROM crm_contact_person WHERE id=:id FOR UPDATE')->queryRow(true,array(':id'=>$id));
        if (!$row || (string)$row['account_id']!==(string)$task->account_id || $row['status']!=='active') { throw new CustomerValidationException(array('contact_person_id'=>array('Người liên hệ phải đang hoạt động và thuộc hồ sơ này.'))); }
    }
    private static function open(Task $task)
    {
        if (!in_array($task->status,array('open','in_progress'),true)) { throw new CHttpException(409,'Việc đã đóng; chỉ quản lý được mở lại bằng luồng riêng.'); }
    }
    private static function validate(Task $m,$allowOverdue,$reason)
    {
        if (!$m->validate()) { throw new CustomerValidationException($m->errors); }
        if ($m->isOverdue()) {
            if ($allowOverdue!==true) { throw new CustomerValidationException(array('allow_overdue'=>array('Hạn đã qua. Cần xác nhận tạo/đổi sang việc quá hạn và ghi lý do.'))); }
            CustomerWriteSupport::reason($reason);
        }
    }
    private static function reminder(Task $task,$renew)
    {
        $db=Yii::app()->db;
        if ($renew) { $db->createCommand()->update('crm_task_reminder',array('state'=>'cancelled'),"task_id=:id AND state='pending'",array(':id'=>$task->id)); }
        foreach (TaskReminderPlanner::plan($task) as $kind=>$at) {
            $r=new TaskReminder; $r->task_id=$task->id; $r->recipient_user_id=$task->assignee_user_id; $r->schedule_version=$task->schedule_version; $r->reminder_kind=$kind; $r->notify_at_utc=$at; CustomerWriteSupport::save($r);
        }
    }
    private static function result(Task $m)
    {
        return array('account_id'=>$m->account_id,'task_id'=>$m->id,'version'=>(int)$m->lock_version,'schedule_version'=>(int)$m->schedule_version,'status'=>$m->status);
    }
    public static function create($accountId,$hotelId,array $fields,$key,$allowOverdue=false,$reason='',$assigneeId=null,$reportId=null)
    {
        self::authorize('create'); $accountId=CustomerWriteSupport::id($accountId); $hotelId=CustomerWriteSupport::id($hotelId);
        $fields=CustomerWriteSupport::fields(TaskSchedule::input($fields),array_merge(self::contentFields(),self::scheduleFields()));
        if (isset($fields['contact_person_id']) && $fields['contact_person_id']==='') { $fields['contact_person_id']=null; }
        $assigneeId=$assigneeId===null || $assigneeId===''?null:CustomerWriteSupport::id($assigneeId);
        $reportId=$reportId===null || $reportId===''?null:CustomerWriteSupport::id($reportId);
        self::context($accountId,$hotelId,'create');
        return self::mutate('task_create',$key,array($accountId,$hotelId,$fields,$allowOverdue,$reason,$assigneeId,$reportId),function()use($accountId,$hotelId,$fields,$key,$allowOverdue,$reason,$assigneeId,$reportId){
            self::authorize('create'); $assignment=self::context($accountId,$hotelId,'create',true);
            if (!$assignment->owner_user_id) { throw new CHttpException(409,'Cần phân công owner trước khi tạo việc.'); }
            self::owner($assignment->owner_user_id,$hotelId);
            if ($assigneeId!==null && (string)$assigneeId!==(string)$assignment->owner_user_id) { throw new CHttpException(409,'Người nhận phải là owner hiện tại; hãy bàn giao khách hàng nếu cần đổi người.'); }
            if ($reportId!==null) {
                ActionAccess::requireAction('report','view'); $report=CustomerWriteSupport::current('Report','reports','id=:id',array(':id'=>$reportId));
                if ($report->identity_state!=='linked' || (string)$report->account_id!==(string)$accountId || (string)$report->hotel_id!==(string)$hotelId) { throw new CHttpException(404,'Báo cáo không thuộc hồ sơ/khách sạn này.'); }
            }
            $m=new Task; $m->setAttributes($fields,false); $m->account_id=$accountId; $m->hotel_id=$hotelId; $m->report_id=$reportId; $m->assignee_user_id=$assignment->owner_user_id;
            $m->code='TSK-'.strtoupper(bin2hex(random_bytes(10))); $m->client_request_key=$key;
            self::contact($m); self::validate($m,$allowOverdue,$reason); CustomerWriteSupport::save($m); self::reminder($m,false); CustomerWriteSupport::audit('task',$m->id,'created',null,$m->attributes,$reason);
            return self::result($m);
        },function($result){ self::access($result['task_id'],'create'); });
    }
    private static function change($action,$id,$expected,$key,array $fields,$note,$allowOverdue)
    {
        self::authorize($action); $id=CustomerWriteSupport::id($id); $expected=CustomerWriteSupport::id($expected); $initial=self::access($id,$action);
        if (!is_string($note) || mb_strlen($note)>4000) { throw new CustomerValidationException(array('reason'=>array('Nội dung/lý do tối đa 4.000 ký tự.'))); }
        if (in_array($action,array('complete','cancel','reschedule','reopen'),true)) { $note=CustomerWriteSupport::reason($note); }
        return self::mutate('task_'.$action,$key,array($id,$expected,$fields,$note,$allowOverdue),function()use($action,$id,$expected,$fields,$note,$allowOverdue,$initial){
            self::authorize($action); $assignment=self::context($initial->account_id,$initial->hotel_id,$action,true);
            $m=CustomerWriteSupport::current('Task','crm_task','id=:id',array(':id'=>$id)); self::access($id,$action); CustomerWriteSupport::version($m,$expected); $before=$m->attributes;
            if ((string)$m->account_id!==(string)$initial->account_id || (string)$m->hotel_id!==(string)$initial->hotel_id) { throw new CHttpException(409,'Ngữ cảnh công việc đã thay đổi.'); }
            $renew=in_array($action,array('reschedule','reopen','complete','cancel'),true);
            if (in_array($action,array('update','reschedule','start'),true)) { self::owner($m->assignee_user_id,$m->hotel_id); }
            if ($action==='reopen') {
                if (!in_array($m->status,array('completed','cancelled'),true)) { throw new CHttpException(409,'Chỉ mở lại việc đã hoàn thành hoặc hủy.'); }
                if (!$assignment->owner_user_id) { throw new CHttpException(409,'Cần owner hiện tại để mở lại.'); }
                self::owner($assignment->owner_user_id,$m->hotel_id); $m->assignee_user_id=$assignment->owner_user_id;
                $m->status='open'; $m->completed_by=null; $m->completed_at_utc=null; $m->completion_note=null; $m->cancelled_at_utc=null; $m->cancel_reason=null;
            } else { self::open($m); }
            if ($action==='start') { if ($m->status!=='open') { throw new CHttpException(409,'Việc đã được bắt đầu.'); } $m->status='in_progress'; }
            elseif ($action==='complete') { $m->status='completed'; $m->completed_by=Yii::app()->user->id; $m->completed_at_utc=gmdate('Y-m-d H:i:s'); $m->completion_note=$note; }
            elseif ($action==='cancel') { $m->status='cancelled'; $m->cancelled_at_utc=gmdate('Y-m-d H:i:s'); $m->cancel_reason=$note; }
            $m->setAttributes($fields,false);
            if (!in_array($action,array('complete','cancel'),true)) { self::contact($m); }
            if (in_array($action,array('reschedule','reopen'),true)) { self::validate($m,$allowOverdue,$note); }
            if ($renew) { $m->schedule_version=(int)$m->schedule_version+1; }
            CustomerWriteSupport::save($m); if ($renew) { self::reminder($m,true); }
            CustomerWriteSupport::audit('task',$m->id,$action,$before,$m->attributes,$note); return self::result($m);
        },function($result)use($action){ self::access($result['task_id'],$action); });
    }
    public static function update($id,array $fields,$expected,$key)
    {
        $fields=CustomerWriteSupport::fields($fields,self::contentFields()); if (isset($fields['contact_person_id']) && $fields['contact_person_id']==='') { $fields['contact_person_id']=null; }
        return self::change('update',$id,$expected,$key,$fields,'',false);
    }
    public static function reschedule($id,array $fields,$expected,$reason,$key,$allowOverdue=false)
    {
        $fields=CustomerWriteSupport::fields(TaskSchedule::input($fields),self::scheduleFields());
        self::requireSchedule($fields); return self::change('reschedule',$id,$expected,$key,$fields,$reason,$allowOverdue);
    }
    public static function reopen($id,array $fields,$expected,$reason,$key,$allowOverdue=false)
    {
        $fields=CustomerWriteSupport::fields(TaskSchedule::input($fields),self::scheduleFields());
        self::requireSchedule($fields); return self::change('reopen',$id,$expected,$key,$fields,$reason,$allowOverdue);
    }
    private static function requireSchedule(array $fields)
    {
        foreach (array('kind','due_mode','timezone','due_date','due_at_utc','starts_at_utc','ends_at_utc') as $key) { if (!array_key_exists($key,$fields)) { throw new CustomerValidationException(array($key=>array('Cần gửi đầy đủ lịch mới, gồm giá trị rỗng cho mốc không dùng.'))); } }
    }
    public static function start($id,$expected,$key) { return self::change('start',$id,$expected,$key,array(),'',false); }
    public static function complete($id,$expected,$note,$key) { return self::change('complete',$id,$expected,$key,array(),$note,false); }
    public static function cancel($id,$expected,$reason,$key) { return self::change('cancel',$id,$expected,$key,array(),$reason,false); }
}
