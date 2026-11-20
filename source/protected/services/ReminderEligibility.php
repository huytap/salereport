<?php
/** Read-only checks. Never acquire AccountHotel/Hotel/grant locks after a Task lock. */
class ReminderEligibility
{
    public static function notificationMatches(array $r,array $n)
    {
        return (int)$n['reminder_id']===(int)$r['id'] && $n['kind']===$r['reminder_kind'] && (int)$n['task_id']===(int)$r['task_id'] && (int)$n['recipient_user_id']===(int)$r['recipient_user_id'] && (int)$n['schedule_version']===(int)$r['schedule_version'];
    }
    public static function reason(array $task,array $reminder,$nowUtc,$checkTiming=true)
    {
        if (!in_array($task['status'],array('open','in_progress'),true)) { return 'TASK_CLOSED'; }
        if ((int)$task['schedule_version']!==(int)$reminder['schedule_version']) { return 'STALE_SCHEDULE'; }
        if ((int)$task['assignee_user_id']!==(int)$reminder['recipient_user_id']) { return 'STALE_RECIPIENT'; }
        if (!in_array($reminder['reminder_kind'],array('before','due_day','overdue'),true)) { return 'INVALID_KIND'; }
        $userId=(int)$reminder['recipient_user_id'];
        if (!ActionAccess::user($userId)) { return 'INACTIVE_USER'; }
        if (!ActionAccess::can('task','view',$userId) || !ActionAccess::can('reminder','admin',$userId)) { return 'ACTION_REVOKED'; }
        $db=Yii::app()->db;
        $context=$db->createCommand('SELECT ah.status AS assignment_status,ah.owner_user_id,a.status AS account_status,h.status AS hotel_status FROM crm_account_hotel ah INNER JOIN crm_account a ON a.id=ah.account_id INNER JOIN crm_hotel h ON h.id=ah.hotel_id WHERE ah.account_id=:account AND ah.hotel_id=:hotel')->queryRow(true,array(':account'=>$task['account_id'],':hotel'=>$task['hotel_id']));
        if (!$context || $context['assignment_status']!=='active' || $context['account_status']!=='active' || $context['hotel_status']!=='active') { return 'INACTIVE_CONTEXT'; }
        if ((int)$context['owner_user_id']!==$userId) { return 'OWNER_CHANGED'; }
        if (!ActionAccess::isAll($userId)) {
            $grant=$db->createCommand('SELECT scope_level FROM crm_user_hotel_access WHERE user_id=:user AND hotel_id=:hotel')->queryScalar(array(':user'=>$userId,':hotel'=>$task['hotel_id']));
            if (!in_array($grant,array('own','hotel'),true)) { return 'HOTEL_ACCESS_REVOKED'; }
        }
        if ($task['contact_person_id']) {
            $active=$db->createCommand("SELECT COUNT(*) FROM crm_contact_person WHERE id=:id AND account_id=:account AND status='active'")->queryScalar(array(':id'=>$task['contact_person_id'],':account'=>$task['account_id']));
            if (!$active) { return 'INACTIVE_CONTACT'; }
        }
        if ($checkTiming) {
            if ($reminder['reminder_kind']==='overdue' && $nowUtc<$task['deadline_at_utc']) { return 'INVALID_OVERDUE_SLOT'; }
            if ($reminder['reminder_kind']!=='overdue' && $nowUtc>=$task['deadline_at_utc']) { return 'MISSED_DEADLINE'; }
        }
        return null;
    }
}
