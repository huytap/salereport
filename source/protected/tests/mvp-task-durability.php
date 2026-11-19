<?php
/** Real commit/rollback, injected schedule failure and bounded MySQL retry, isolated schema only. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
$app=Yii::createConsoleApplication(require __DIR__.'/../config/console.php');
class TaskDurableActor extends CApplicationComponent { public $id=1; public function getIsGuest() { return false; } }
$app->setComponent('user',new TaskDurableActor); $flags=$app->params['mvp']; $flags['customerWrite']=true; $flags['taskWrite']=true; $app->params['mvp']=$flags;
$db=$app->db; $db->setActive(true); if ($db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Synthetic test fixture required.'); }
$checks=0; $hid=null; $aid=null; $tid=null; $peer=null; $trigger=null; $label='WP05 durable '.bin2hex(random_bytes(6));
function tdCheck($ok,$message) { global $checks; if (!$ok) { throw new Exception($message); } $checks++; }
function tdSchedule($date) { return array('kind'=>'task','due_mode'=>'date','due_date'=>$date,'due_at_utc'=>null,'starts_at_utc'=>null,'ends_at_utc'=>null,'timezone'=>'Asia/Ho_Chi_Minh'); }
try {
    $h=new Hotel; $h->code='D5-'.bin2hex(random_bytes(6)); $h->name=$label; tdCheck($h->save(),'Hotel fixture'); $hid=$h->id;
    $account=AccountService::create(array('name'=>$label),$hid,1,null,array(),bin2hex(random_bytes(16))); $aid=$account['account_id'];
    $key=bin2hex(random_bytes(16)); $fields=array_merge(tdSchedule('2030-01-08'),array('title'=>$label)); $result=TaskService::create($aid,$hid,$fields,$key); $tid=$result['task_id'];
    $peer=Yii::createComponent(require __DIR__.'/../config/db-test.php'); $peer->setActive(true);
    tdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_task WHERE id=:id')->queryScalar(array(':id'=>$tid))===1,'Peer cannot see task commit');
    tdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_task_reminder WHERE task_id=:id')->queryScalar(array(':id'=>$tid))===2,'Peer cannot see initial reminders');
    tdCheck((int)$peer->createCommand("SELECT COUNT(*) FROM crm_customer_request WHERE account_id=:id AND operation='task_create'")->queryScalar(array(':id'=>$aid))===1,'Peer cannot see task journal');
    $trigger='wp05_fail_'.bin2hex(random_bytes(6));
    $db->createCommand('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON crm_task_reminder FOR EACH ROW BEGIN IF NEW.task_id='.(int)$tid.' AND NEW.schedule_version=2 AND COALESCE(@wp05_mode,0)>0 THEN SET @wp05_attempt=COALESCE(@wp05_attempt,0)+1; IF @wp05_mode=1 THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Synthetic reminder failure\'; ELSEIF @wp05_mode=2 OR (@wp05_mode=3 AND @wp05_attempt=1) THEN SIGNAL SQLSTATE \'40001\' SET MYSQL_ERRNO=1213,MESSAGE_TEXT=\'Synthetic deadlock\'; END IF; END IF; END')->execute();
    $changeKey=bin2hex(random_bytes(16)); $schedule=tdSchedule('2030-01-09');
    $db->createCommand('SET @wp05_mode=1,@wp05_attempt=0')->execute();
    try { TaskService::reschedule($tid,$schedule,1,'Atomic schedule test',$changeKey); throw new Exception('Injected reminder failure accepted'); }
    catch (CDbException $e) { tdCheck(strpos($e->getMessage(),'Synthetic reminder failure')!==false,'Wrong injected failure'); }
    tdCheck((int)$peer->createCommand('SELECT lock_version FROM crm_task WHERE id=:id')->queryScalar(array(':id'=>$tid))===1,'Partial task update committed');
    tdCheck($peer->createCommand('SELECT due_date FROM crm_task WHERE id=:id')->queryScalar(array(':id'=>$tid))==='2030-01-08','Partial deadline committed');
    tdCheck((int)$peer->createCommand("SELECT COUNT(*) FROM crm_task_reminder WHERE task_id=:id AND state='pending' AND schedule_version=1")->queryScalar(array(':id'=>$tid))===2,'Partial cancellation committed');
    tdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_customer_request WHERE request_key=:key')->queryScalar(array(':key'=>$changeKey))===0,'Failed request journal committed');
    tdCheck((int)$peer->createCommand("SELECT COUNT(*) FROM crm_audit_event WHERE entity_type='task' AND entity_id=:id")->queryScalar(array(':id'=>(string)$tid))===1,'Partial audit committed');
    $db->createCommand('SET @wp05_mode=2,@wp05_attempt=0')->execute();
    try { TaskService::reschedule($tid,$schedule,1,'Atomic schedule test',$changeKey); throw new Exception('Persistent deadlock accepted'); }
    catch (CDbException $e) { tdCheck(isset($e->errorInfo[1]) && (int)$e->errorInfo[1]===1213,'Expected deadlock vendor code'); }
    tdCheck((int)$db->createCommand('SELECT @wp05_attempt')->queryScalar()===3,'Deadlock retry must stop after 3 attempts');
    tdCheck((int)$peer->createCommand('SELECT lock_version FROM crm_task WHERE id=:id')->queryScalar(array(':id'=>$tid))===1,'Persistent retry left changes');
    $db->createCommand('SET @wp05_mode=3,@wp05_attempt=0')->execute();
    $scheduled=TaskService::reschedule($tid,$schedule,1,'Atomic schedule test',$changeKey);
    tdCheck((int)$db->createCommand('SELECT @wp05_attempt')->queryScalar()===3,'One failure then two successful reminder inserts');
    tdCheck($scheduled['version']===2 && $scheduled['schedule_version']===2,'Retry schedule versions');
    tdCheck((int)$peer->createCommand("SELECT COUNT(*) FROM crm_task_reminder WHERE task_id=:id AND schedule_version=2 AND state='pending'")->queryScalar(array(':id'=>$tid))===2,'Retry duplicated/lost reminders');
    tdCheck((int)$peer->createCommand("SELECT COUNT(*) FROM crm_audit_event WHERE entity_type='task' AND entity_id=:id AND event_type='reschedule'")->queryScalar(array(':id'=>(string)$tid))===1,'Retry audit not exactly once');
    $db->createCommand('DROP TRIGGER '.$trigger)->execute(); $trigger=null;
    $db->setActive(false); $db->setActive(true); $peer->setActive(false); $peer->setActive(true);
    tdCheck(TaskService::create($aid,$hid,$fields,$key)['replayed'],'Create replay after reconnect');
    tdCheck(TaskService::reschedule($tid,$schedule,1,'Atomic schedule test',$changeKey)['replayed'],'Reschedule replay after reconnect');
    // A second transaction holds the Task, like a reminder worker. Lock timeout is retried finitely.
    $peerTx=$peer->beginTransaction(); $peer->createCommand('SELECT id FROM crm_task WHERE id=:id FOR UPDATE')->queryScalar(array(':id'=>$tid));
    $timeout=(int)$db->createCommand('SELECT @@SESSION.innodb_lock_wait_timeout')->queryScalar(); $blockedKey=bin2hex(random_bytes(16));
    try {
        $db->createCommand('SET SESSION innodb_lock_wait_timeout=1')->execute();
        try { TaskService::complete($tid,2,'Durable completion',$blockedKey); throw new Exception('Peer lock ignored'); }
        catch (CDbException $e) { tdCheck(isset($e->errorInfo[1]) && (int)$e->errorInfo[1]===1205,'Expected real lock timeout'); }
        tdCheck($peer->createCommand('SELECT status FROM crm_task WHERE id=:id')->queryScalar(array(':id'=>$tid))==='open','Blocked close changed status');
        tdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_customer_request WHERE request_key=:key')->queryScalar(array(':key'=>$blockedKey))===0,'Blocked close left journal');
    } finally { if ($peerTx->active) { $peerTx->rollback(); } $db->createCommand('SET SESSION innodb_lock_wait_timeout='.$timeout)->execute(); }
    $completeKey=$blockedKey; $done=TaskService::complete($tid,2,'Durable completion',$completeKey);
    tdCheck($peer->createCommand('SELECT status FROM crm_task WHERE id=:id')->queryScalar(array(':id'=>$tid))==='completed','Peer cannot see completion');
    tdCheck((int)$peer->createCommand("SELECT COUNT(*) FROM crm_task_reminder WHERE task_id=:id AND state='pending'")->queryScalar(array(':id'=>$tid))===0,'Completion did not cancel reminders');
    tdCheck(TaskService::complete($tid,2,'Durable completion',$completeKey)['replayed'],'Complete replay');
    $audit=$peer->createCommand("SELECT COUNT(*) FROM crm_audit_event WHERE entity_type='task' AND entity_id=:id")->queryScalar(array(':id'=>(string)$tid));
    try { TaskService::reschedule($tid,tdSchedule('2030-02-01'),2,'Other browser after completion',bin2hex(random_bytes(16))); throw new Exception('Stale reschedule accepted'); }
    catch (CHttpException $e) { tdCheck($e->statusCode===409,'Expected stale version'); }
    tdCheck($peer->createCommand("SELECT COUNT(*) FROM crm_audit_event WHERE entity_type='task' AND entity_id=:id")->queryScalar(array(':id'=>(string)$tid))===$audit,'Stale request left audit');
    // A compound caller may already have a consistent snapshot. Current scope must win.
    require_once __DIR__.'/support/TemporaryTableFixture.php';
    $realUsers=$db->createCommand('SELECT * FROM users')->queryAll(); $ddl=$db->createCommand('SHOW CREATE TABLE users')->queryRow(); $db->createCommand(isolatedTemporaryTableDdl($ddl['Create Table']))->execute(); foreach ($realUsers as $row) { $db->createCommand()->insert('users',$row); }
    $synthetic=new Users('create'); $synthetic->attributes=array('username'=>'wp05_snapshot_owner','fullname'=>'Snapshot owner','password'=>'fake','roles'=>'users','status'=>'A','last_login_date'=>'2026-11-19 00:00:00'); tdCheck($synthetic->save(),'Temporary snapshot user');
    foreach (array('revoke','handover') as $race) {
        $db->createCommand()->insert('crm_user_hotel_access',array('user_id'=>$synthetic->id,'hotel_id'=>$hid,'scope_level'=>'own','updated_by'=>1,'updated_at_utc'=>gmdate('Y-m-d H:i:s')));
        $db->createCommand()->update('crm_account_hotel',array('owner_user_id'=>$synthetic->id),'account_id=:id',array(':id'=>$aid));
        $app->user->id=$synthetic->id; $outer=$db->beginTransaction(); $raceKey=bin2hex(random_bytes(16));
        try {
            $db->createCommand('SELECT owner_user_id FROM crm_account_hotel WHERE account_id=:id')->queryScalar(array(':id'=>$aid));
            if ($race==='revoke') { $peer->createCommand()->delete('crm_user_hotel_access','user_id=:user AND hotel_id=:hotel',array(':user'=>$synthetic->id,':hotel'=>$hid)); }
            else { $peer->createCommand()->update('crm_account_hotel',array('owner_user_id'=>1),'account_id=:id',array(':id'=>$aid)); }
            try { TaskService::create($aid,$hid,$fields,$raceKey); throw new Exception('Stale snapshot scope accepted'); }
            catch (CHttpException $e) { tdCheck($e->statusCode===404,'Current scope must override old snapshot: '.$race); }
            tdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_customer_request WHERE request_key=:key')->queryScalar(array(':key'=>$raceKey))===0,'Denied snapshot request left journal');
        } finally { if ($outer->active) { $outer->rollback(); } $app->user->id=1; }
        $db->createCommand()->delete('crm_user_hotel_access','user_id=:user AND hotel_id=:hotel',array(':user'=>$synthetic->id,':hotel'=>$hid));
    }
    $db->createCommand('DROP TEMPORARY TABLE users')->execute(); tdCheck($db->createCommand('SELECT * FROM users')->queryAll()===$realUsers,'Snapshot test changed real users');
} finally {
    if ($trigger) { $db->createCommand('DROP TRIGGER IF EXISTS '.$trigger)->execute(); }
    $cleanup=$db->beginTransaction();
    try {
        if ($tid) { foreach (array('crm_notification','crm_task_reminder') as $table) { $db->createCommand()->delete($table,'task_id=:id',array(':id'=>$tid)); } $db->createCommand()->delete('crm_task','id=:id',array(':id'=>$tid)); $db->createCommand()->delete('crm_audit_event',"entity_type='task' AND entity_id=:id",array(':id'=>(string)$tid)); }
        if ($aid) { $db->createCommand()->delete('crm_audit_event',"(entity_type='account' AND entity_id=:id) OR (entity_type='account_hotel' AND entity_id=:pair)",array(':id'=>(string)$aid,':pair'=>$aid.':'.$hid)); foreach (array('crm_customer_request','crm_account_market','crm_account_hotel') as $table) { $db->createCommand()->delete($table,'account_id=:id',array(':id'=>$aid)); } $db->createCommand()->delete('crm_account','id=:id',array(':id'=>$aid)); }
        if ($hid) { $db->createCommand()->delete('crm_user_hotel_access','hotel_id=:id',array(':id'=>$hid)); $db->createCommand()->delete('crm_hotel','id=:id',array(':id'=>$hid)); } $cleanup->commit();
    } catch (Exception $e) { $cleanup->rollback(); throw $e; }
    $db->createCommand('DROP TEMPORARY TABLE IF EXISTS users')->execute(); if ($peer) { $peer->setActive(false); }
}
echo "PASS: $checks task durability assertions; independent commits, atomic reminder failure, bounded retry, reconnect/replay, close vs stale reschedule and run-owned cleanup.\n";
