<?php

/** Scheduling only. Delivery stays disabled until WP07. */
class TaskReminderPlanner
{
    public static function plan(Task $task,$nowUtc=null)
    {
        if (!in_array($task->status,array('open','in_progress'),true)) { return array(); }
        $utc=new DateTimeZone('UTC'); $tz=new DateTimeZone($task->timezone);
        $now=new DateTimeImmutable($nowUtc ?: 'now',$utc); $deadline=new DateTimeImmutable($task->deadline_at_utc,$utc);
        if ($now >= $deadline) { return array('overdue'=>$now->format('Y-m-d H:i:s')); }
        if ($task->due_mode==='date') { $before=new DateTimeImmutable($task->due_date.' 09:00:00',$tz); $kind='due_day'; }
        else { $before=(new DateTimeImmutable($task->kind==='appointment'?$task->starts_at_utc:$task->due_at_utc,$utc))->modify('-30 minutes'); $kind='before'; }
        $overdue=$deadline->setTimezone($tz)->setTime(9,0);
        if ($overdue <= $deadline) { $overdue=$overdue->modify('+1 day'); }
        return array($kind=>($before<$now?$now:$before)->setTimezone($utc)->format('Y-m-d H:i:s'),'overdue'=>$overdue->setTimezone($utc)->format('Y-m-d H:i:s'));
    }
}
