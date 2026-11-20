<?php
class TaskReadService
{
    public static function labels() { return array('open'=>'Chưa bắt đầu','in_progress'=>'Đang thực hiện','completed'=>'Hoàn thành','cancelled'=>'Đã hủy'); }
    private static function filters(array $input,$action)
    {
        AccountReadService::ready(); ActionAccess::requireAction('task',$action);
        $f=array_merge(array('q'=>'','hotel_id'=>'','assignee_user_id'=>'','account_id'=>'','status'=>'','priority'=>''),$input);
        foreach ($f as $key=>$v) { $f[$key]=AccountReadService::text($v); }
        if (!in_array($f['status'],array('','open','in_progress','completed','cancelled'),true) || !in_array($f['priority'],array('','normal','high'),true)) { throw new CHttpException(400,'Bộ lọc trạng thái/ưu tiên không hợp lệ.'); }
        $c=CustomerPolicy::scopeCriteria('task');
        foreach (array('hotel_id','assignee_user_id','account_id') as $key) { if ($f[$key]!=='') { $c->compare('t.'.$key,CustomerWriteSupport::id($f[$key])); } }
        $c->compare('t.status',$f['status']); $c->compare('t.priority',$f['priority']);
        if ($f['q']!=='') { $search=new CDbCriteria; $search->compare('t.title',$f['q'],true,'OR'); $search->compare('t.code',$f['q'],true,'OR'); $c->mergeWith($search); }
        return array($c,$f);
    }
    public static function date($value)
    {
        if (!MvpActiveRecord::validDate($value,'Y-m-d')) { throw new CHttpException(400,'Ngày phải hợp lệ theo YYYY-MM-DD.'); }
        return $value;
    }
    public static function listing(array $filters,$tab,$page,$timezone='Asia/Ho_Chi_Minh')
    {
        list($c,$f)=self::filters($filters,'admin'); $page=CustomerWriteSupport::id($page); $tz=TaskSchedule::timezone($timezone);
        if (!in_array($tab,array('mine','today','upcoming','overdue','completed','all'),true)) { throw new CHttpException(400,'Tab công việc không hợp lệ.'); }
        $now=new DateTimeImmutable('now',new DateTimeZone('UTC')); $today=$now->setTimezone($tz)->setTime(0,0); $tomorrow=$today->modify('+1 day');
        if ($tab==='mine') { $c->compare('t.assignee_user_id',Yii::app()->user->id); if ($f['status']==='') { $c->addInCondition('t.status',array('open','in_progress')); } }
        elseif ($tab==='completed') { $c->compare('t.status','completed'); }
        elseif (in_array($tab,array('today','upcoming','overdue'),true)) {
            $c->addInCondition('t.status',array('open','in_progress'));
            if ($tab==='overdue') { $c->addCondition('t.deadline_at_utc<=:now'); $c->params[':now']=$now->format('Y-m-d H:i:s'); }
            elseif ($tab==='today') {
                $c->addCondition("((t.due_mode='date' AND t.due_date=:today) OR (t.due_mode='datetime' AND t.deadline_at_utc>=:start AND t.deadline_at_utc<:end))");
                $c->params+=array(':today'=>$today->format('Y-m-d'),':start'=>$today->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),':end'=>$tomorrow->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
            } else {
                $c->addCondition("((t.due_mode='date' AND t.due_date>:today) OR (t.due_mode='datetime' AND t.deadline_at_utc>=:end))");
                $c->params+=array(':today'=>$today->format('Y-m-d'),':end'=>$tomorrow->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
            }
        }
        $total=(int)Task::model()->count($c); $c->limit=25; $c->offset=($page-1)*25; $c->order='t.deadline_at_utc,t.id';
        $tasks=Task::model()->findAll($c); self::pageLabels($tasks);
        return array('tasks'=>$tasks,'filters'=>$f,'tab'=>$tab,'page'=>$page,'total'=>$total,'timezone'=>$timezone,'hotels'=>AccountReadService::hotels(),'owners'=>Users::model()->findAll(CustomerPolicy::visibleUsersCriteria()));
    }
    /** Batch only the IDs on this scoped page; keep LIMIT/ordering independent of label joins. */
    private static function pageLabels(array $tasks)
    {
        if (!$tasks) { return; }
        foreach (array('account'=>array('Account','account_id'),'hotel'=>array('Hotel','hotel_id'),'assignee'=>array('Users','assignee_user_id')) as $relation=>$definition) {
            list($class,$field)=$definition; $ids=array(); foreach ($tasks as $task) { if ($task->$field) { $ids[]=$task->$field; } }
            $labels=array(); foreach ($class::model()->findAllByPk(array_values(array_unique($ids))) as $label) { $labels[(string)$label->id]=$label; }
            foreach ($tasks as $task) { $task->addRelatedRecord($relation,isset($labels[(string)$task->$field])?$labels[(string)$task->$field]:null,false); }
        }
    }
    public static function detail($id,$page=1)
    {
        AccountReadService::ready(); $task=CustomerPolicy::load('Task','task','task','view',$id);
        $c=new CDbCriteria; $c->compare('entity_type','task'); $c->compare('entity_id',(string)$task->id); $page=CustomerWriteSupport::id($page); $total=(int)AuditEvent::model()->count($c); $c->limit=25; $c->offset=($page-1)*25; $c->order='t.id DESC';
        return array('task'=>$task,'audit'=>AuditEvent::model()->findAll($c),'auditPage'=>$page,'auditTotal'=>$total,'timezone'=>$task->timezone);
    }
    public static function calendar($from,$to,$timezone,array $filters=array())
    {
        list($criteria,$f)=self::filters($filters,'calendar'); $tz=TaskSchedule::timezone($timezone);
        $from=self::date($from); $to=self::date($to); $start=new DateTimeImmutable($from.' 00:00:00',$tz); $end=new DateTimeImmutable($to.' 00:00:00',$tz);
        if ($end<=$start || $start->diff($end)->days>7) { throw new CHttpException(400,'Lịch chỉ nhận khoảng 1–7 ngày, ngày cuối không bao gồm.'); }
        if ($f['status']==='') { $criteria->addInCondition('t.status',array('open','in_progress')); }
        $appointments=clone $criteria; $appointments->compare('t.kind','appointment'); $appointments->addCondition('t.starts_at_utc<:end AND t.ends_at_utc>:start');
        $appointments->params+=array(':end'=>$end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),':start'=>$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        $appointments->order='t.starts_at_utc,t.id'; $appointments->limit=501; $appointments->with=array('account'); $events=Task::model()->findAll($appointments);
        $dates=clone $criteria; $dates->compare('t.kind','task'); $dates->compare('t.due_mode','date'); $dates->addCondition('t.due_date>=:from AND t.due_date<:to'); $dates->params+=array(':from'=>$from,':to'=>$to); $dates->order='t.due_date,t.id'; $dates->limit=501; $dates->with=array('account'); $dayTasks=Task::model()->findAll($dates);
        if (count($events)>500 || count($dayTasks)>500) { throw new CHttpException(422,'Có quá nhiều công việc. Hãy lọc thêm khách sạn hoặc sales.'); }
        $items=array(); $dateItems=array();
        foreach ($events as $m) { $items[]=array_merge(self::item($m),array('start_utc'=>$m->starts_at_utc,'end_utc'=>$m->ends_at_utc,'start_local'=>TaskSchedule::local($m->starts_at_utc,$timezone),'end_local'=>TaskSchedule::local($m->ends_at_utc,$timezone))); }
        foreach ($dayTasks as $m) { $dateItems[]=array_merge(self::item($m),array('due_date'=>$m->due_date,'timezone'=>$m->timezone)); }
        return array('from'=>$from,'to'=>$to,'timezone'=>$timezone,'events'=>$items,'date_tasks'=>$dateItems,'filters'=>$f);
    }
    private static function item(Task $task)
    {
        return array('id'=>(int)$task->id,'title'=>$task->title,'account_id'=>(int)$task->account_id,'account_name'=>$task->account->name,'hotel_id'=>(int)$task->hotel_id,'assignee_user_id'=>(int)$task->assignee_user_id,'status'=>$task->status,'url'=>Yii::app()->createUrl('task/view',array('id'=>$task->id)));
    }
    public static function choices($hotelId,$q='')
    {
        $c=CustomerPolicy::scopeCriteria('account'); $c->compare('t.status','active'); $c->compare('t.name',AccountReadService::text($q),true);
        $c->addCondition("EXISTS (SELECT 1 FROM crm_account_hotel ac WHERE ac.account_id=t.id AND ac.hotel_id=:hotel AND ac.status='active'".(!ActionAccess::isAll()?" AND EXISTS (SELECT 1 FROM crm_user_hotel_access cg WHERE cg.hotel_id=ac.hotel_id AND cg.user_id=:uid AND (cg.scope_level='hotel' OR ac.owner_user_id=:owner))":'').')');
        $c->params[':hotel']=$hotelId;
        if (!ActionAccess::isAll()) { $c->params+=array(':uid'=>Yii::app()->user->id,':owner'=>Yii::app()->user->id); }
        $c->order='t.name,t.id'; $c->limit=100; return Account::model()->findAll($c);
    }
}
