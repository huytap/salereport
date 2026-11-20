<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
$app=Yii::createConsoleApplication(require __DIR__.'/../config/console.php');
class ReminderDurableActor extends CApplicationComponent { public $id=1; public function getIsGuest() { return false; } }
$app->setComponent('user',new ReminderDurableActor); $flags=$app->params['mvp']; $flags['customerWrite']=true; $flags['taskWrite']=true; $flags['reminders']=true; $app->params['mvp']=$flags;
$db=$app->db; $db->setActive(true); require_once __DIR__.'/support/ReminderTestFixture.php'; $f=new ReminderTestFixture; $peer=null; $trigger=null; $checks=0; $children=array();
function dc($ok,$message) { global $checks; if (!$ok) { throw new Exception($message); } $checks++; }
function spawnReminder(array $args) {
    $cmd=array_merge(array(PHP_BINARY,realpath(__DIR__.'/../yiic.php'),'reminder'),$args); $pipes=array(); $process=proc_open($cmd,array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    if (!is_resource($process)) { throw new Exception('Cannot start test worker.'); } fclose($pipes[0]); stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false); return array('process'=>$process,'out'=>$pipes[1],'err'=>$pipes[2]);
}
function finishReminder(array $child) {
    $out=''; $err=''; $deadline=microtime(true)+12; $exit=null;
    while (true) {
        $out.=stream_get_contents($child['out']); $err.=stream_get_contents($child['err']); $status=proc_get_status($child['process']);
        if (!$status['running']) { $exit=$status['exitcode']; break; }
        if (microtime(true)>$deadline) { proc_terminate($child['process']); throw new Exception('Owned test worker timed out.'); } usleep(10000);
    }
    $out.=stream_get_contents($child['out']); $err.=stream_get_contents($child['err']); fclose($child['out']); fclose($child['err']); $closed=proc_close($child['process']); if ($exit===-1) { $exit=$closed; }
    $data=json_decode(trim($out),true); if (!is_array($data)) { throw new Exception('Invalid test worker JSON: '.$out.' '.$err); }
    return array('exit'=>$exit,'data'=>$data,'stderr'=>$err);
}
function waitRunning($after,$count) {
    $db=Yii::app()->db; $deadline=microtime(true)+1.5;
    do { $ids=$db->createCommand("SELECT id FROM crm_reminder_dispatch_run WHERE id>:after AND status='running'")->queryColumn(array(':after'=>$after)); if (count($ids)>=$count) { return $ids; } usleep(10000); } while (microtime(true)<$deadline);
    return $ids;
}
function runChildTracked(array $args) { global $f; $result=finishReminder(spawnReminder($args)); if (isset($result['data']['run_id'])) { $f->runs[]=$result['data']['run_id']; } return $result; }
try {
    $baseUsers=$db->createCommand('SELECT * FROM users ORDER BY id')->queryAll(); $baseReports=$db->createCommand('SELECT * FROM reports ORDER BY id')->queryAll();
    $h=$f->hotel('durable'); $aid=$f->account($h->id,1,'durable account'); $task=$f->task($aid,$h->id,'late transaction failure'); $r=$f->due($task);
    $peer=Yii::createComponent(require __DIR__.'/../config/db-test.php'); $peer->setActive(true); $taskBefore=$peer->createCommand('SELECT * FROM crm_task WHERE id=:id')->queryRow(true,array(':id'=>$task->id));
    $trigger='wp07_fail_'.bin2hex(random_bytes(6));
    $db->createCommand('CREATE TRIGGER '.$trigger.' BEFORE UPDATE ON crm_task_reminder FOR EACH ROW BEGIN IF NEW.id='.(int)$r->id.' AND NEW.state=\'delivered\' AND COALESCE(@wp07_mode,0)>0 THEN SET @wp07_attempt=COALESCE(@wp07_attempt,0)+1; IF @wp07_mode=1 THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Synthetic after-notification failure\'; ELSEIF @wp07_mode=2 OR (@wp07_mode=3 AND @wp07_attempt=1) THEN SIGNAL SQLSTATE \'40001\' SET MYSQL_ERRNO=1213,MESSAGE_TEXT=\'Synthetic dispatch deadlock\'; END IF; END IF; END')->execute();
    $originalTimeout=$db->createCommand('SELECT @@SESSION.innodb_lock_wait_timeout')->queryScalar(); $db->createCommand('SET @wp07_mode=1,@wp07_attempt=0')->execute(); $failed=$f->dispatch();
    dc($failed['status']==='partial' && $failed['error_count']===1 && $failed['created_count']===0,'Late failure dispatch result');
    dc((int)$peer->createCommand('SELECT COUNT(*) FROM crm_notification WHERE reminder_id=:id')->queryScalar(array(':id'=>$r->id))===0,'Notification insert did not roll back');
    $row=$peer->createCommand('SELECT * FROM crm_task_reminder WHERE id=:id')->queryRow(true,array(':id'=>$r->id)); dc($row['state']==='pending' && $row['last_error_code']==='DB_WRITE_FAILED' && (int)$row['attempt_count']===1,'Failure remains retryable with error code');
    dc($peer->createCommand('SELECT * FROM crm_task WHERE id=:id')->queryRow(true,array(':id'=>$task->id))===$taskBefore,'Worker mutated task');
    $health=ReminderDispatchService::check(); dc(in_array('ERRORS',$health['alerts'],true) && $health['last_success_at_utc']===null && $health['queue']['failed_pending_count']===1,'Failure monitoring');
    $db->createCommand('SET @wp07_mode=2,@wp07_attempt=0')->execute(); $again=$f->dispatch(); dc($again['error_count']===1 && (int)$db->createCommand('SELECT @wp07_attempt')->queryScalar()===3,'Deadlock attempts bounded at three');
    dc((int)$peer->createCommand('SELECT COUNT(*) FROM crm_notification WHERE reminder_id=:id')->queryScalar(array(':id'=>$r->id))===0,'Deadlock left notification');
    $db->createCommand('SET @wp07_mode=3,@wp07_attempt=0')->execute(); $ok=$f->dispatch(); dc($ok['created_count']===1 && $ok['error_count']===0 && (int)$db->createCommand('SELECT @wp07_attempt')->queryScalar()===2,'One deadlock then successful dispatch');
    $row=$peer->createCommand('SELECT * FROM crm_task_reminder WHERE id=:id')->queryRow(true,array(':id'=>$r->id)); dc($row['state']==='delivered' && $row['last_error_code']===null && (int)$row['attempt_count']===3,'Success clears last error and records processing cycles');
    dc((int)$peer->createCommand('SELECT COUNT(*) FROM crm_notification WHERE reminder_id=:id')->queryScalar(array(':id'=>$r->id))===1,'Successful retry exactly once');
    dc($db->createCommand('SELECT @@SESSION.innodb_lock_wait_timeout')->queryScalar()===$originalTimeout,'Worker changed connection lock timeout permanently');
    dc(ReminderDispatchService::check()['last_success_at_utc']!==null,'Successful heartbeat');
    $db->createCommand('DROP TRIGGER '.$trigger)->execute(); $trigger=null;
    $db->setActive(false); $db->setActive(true); $peer->setActive(false); $peer->setActive(true); $reconnect=$f->dispatch(); dc($reconnect['selected_count']===0 && $reconnect['created_count']===0,'Reconnect redelivered slot');
    // Repair an existing committed slot without replacing read history or duplicating notification.
    $db->createCommand()->update('crm_notification',array('read_at_utc'=>'2020-01-01 00:00:00'),'reminder_id=:id',array(':id'=>$r->id)); $db->createCommand()->update('crm_task_reminder',array('state'=>'pending','delivered_at_utc'=>null),'id=:id',array(':id'=>$r->id));
    $recovered=$f->dispatch(); dc($recovered['recovered_count']===1 && $recovered['created_count']===0,'Recover existing notification'); dc($peer->createCommand('SELECT read_at_utc FROM crm_notification WHERE reminder_id=:id')->queryScalar(array(':id'=>$r->id))==='2020-01-01 00:00:00','Recovery overwrote read history');
    // Both processes select the same due slot before either may lock its Task.
    $race=$f->task($aid,$h->id,'two workers'); $slot=$f->due($race); $barrier=$peer->beginTransaction(); $peer->createCommand('SELECT id FROM crm_task WHERE id=:id FOR UPDATE')->queryScalar(array(':id'=>$race->id));
    $before=(int)$db->createCommand('SELECT COALESCE(MAX(id),0) FROM crm_reminder_dispatch_run')->queryScalar();
    $children[]=spawnReminder(array('dispatch','--enableTest=1','--limit=50')); $children[]=spawnReminder(array('dispatch','--enableTest=1','--limit=50'));
    try { $waiting=waitRunning($before,2); foreach ($waiting as $id) { $f->runs[]=$id; } dc(count($waiting)===2,'Both worker processes reached the Task barrier'); }
    finally { if ($barrier->active) { $barrier->rollback(); } }
    $results=array(); foreach ($children as $child) { $result=finishReminder($child); $results[]=$result; if (isset($result['data']['run_id'])) { $f->runs[]=$result['data']['run_id']; } } $children=array();
    dc($results[0]['exit']===0 && $results[1]['exit']===0,'Parallel worker exit codes');
    dc($results[0]['data']['selected_count']===1 && $results[1]['data']['selected_count']===1,'Parallel workers selected same candidate');
    dc($results[0]['data']['created_count']+$results[1]['data']['created_count']===1 && $results[0]['data']['skipped_count']+$results[1]['data']['skipped_count']===1,'Parallel workers must create once and skip the delivered slot');
    dc((int)$peer->createCommand('SELECT COUNT(*) FROM crm_notification WHERE reminder_id=:id')->queryScalar(array(':id'=>$slot->id))===1,'Parallel duplicate notification');
    dc((int)$peer->createCommand('SELECT attempt_count FROM crm_task_reminder WHERE id=:id')->queryScalar(array(':id'=>$slot->id))===1,'Second worker changed delivered attempt count');
    // A concurrent close commits before the waiting worker gets Task/Reminder locks.
    $closing=$f->task($aid,$h->id,'close before worker'); $closingSlot=$f->due($closing); $barrier=$peer->beginTransaction(); $peer->createCommand('SELECT id FROM crm_task WHERE id=:id FOR UPDATE')->queryScalar(array(':id'=>$closing->id));
    $before=(int)$db->createCommand('SELECT MAX(id) FROM crm_reminder_dispatch_run')->queryScalar(); $children[]=spawnReminder(array('dispatch','--enableTest=1'));
    try {
        $waiting=waitRunning($before,1); foreach ($waiting as $id) { $f->runs[]=$id; } dc(count($waiting)===1,'Waiting worker for close race');
        $peer->createCommand()->update('crm_task',array('status'=>'cancelled','cancelled_at_utc'=>gmdate('Y-m-d H:i:s'),'cancel_reason'=>'Synthetic concurrent close','schedule_version'=>2,'lock_version'=>2),'id=:id',array(':id'=>$closing->id));
        $peer->createCommand()->update('crm_task_reminder',array('state'=>'cancelled'),'task_id=:id',array(':id'=>$closing->id)); $barrier->commit();
    } finally { if ($barrier->active) { $barrier->rollback(); } }
    $result=finishReminder(array_shift($children)); $f->runs[]=$result['data']['run_id']; dc($result['data']['created_count']===0 && $result['data']['skipped_count']===1,'Worker ignored concurrent close');
    dc((int)$peer->createCommand('SELECT COUNT(*) FROM crm_notification WHERE reminder_id=:id')->queryScalar(array(':id'=>$closingSlot->id))===0,'Closed task race produced notification');
    // Real recipient visible to child process; revoke before its first non-locking context read.
    $u=new Users('create'); $u->attributes=array('username'=>'wp07_'.bin2hex(random_bytes(6)),'fullname'=>'Synthetic worker recipient','password'=>'fake','roles'=>'users','status'=>'A','last_login_date'=>'2026-11-19 00:00:00'); dc($u->save(),'Real synthetic recipient fixture'); $f->users[]=$u->id;
    $db->createCommand()->insert('crm_user_hotel_access',array('user_id'=>$u->id,'hotel_id'=>$h->id,'scope_level'=>'own','updated_by'=>1,'updated_at_utc'=>gmdate('Y-m-d H:i:s')));
    $a2=$f->account($h->id,$u->id,'non-All recipient'); $revoked=$f->task($a2,$h->id,'revoke before dispatch'); $revokeSlot=$f->due($revoked); $barrier=$peer->beginTransaction(); $peer->createCommand('SELECT id FROM crm_task WHERE id=:id FOR UPDATE')->queryScalar(array(':id'=>$revoked->id));
    $before=(int)$db->createCommand('SELECT MAX(id) FROM crm_reminder_dispatch_run')->queryScalar(); $children[]=spawnReminder(array('dispatch','--enableTest=1'));
    try { $waiting=waitRunning($before,1); foreach ($waiting as $id) { $f->runs[]=$id; } dc(count($waiting)===1,'Worker waiting before revoke'); $peer->createCommand()->delete('crm_user_hotel_access','user_id=:user AND hotel_id=:hotel',array(':user'=>$u->id,':hotel'=>$h->id)); $barrier->commit(); }
    finally { if ($barrier->active) { $barrier->rollback(); } }
    $result=finishReminder(array_shift($children)); $f->runs[]=$result['data']['run_id']; dc($result['data']['cancelled_count']===1 && $result['data']['created_count']===0,'Worker used pre-lock Hotel grant'); dc($peer->createCommand('SELECT last_error_code FROM crm_task_reminder WHERE id=:id')->queryScalar(array(':id'=>$revokeSlot->id))==='HOTEL_ACCESS_REVOKED','Revoke reason persisted');
    // CLI check/dry-run are truly read-only; normal dispatch stays disabled without process opt-in.
    $rowsBefore=array(); foreach (array('crm_task_reminder','crm_notification','crm_reminder_dispatch_run') as $table) { $rowsBefore[$table]=$db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll(); }
    $disabled=runChildTracked(array('dispatch')); dc($disabled['exit']===2 && $disabled['data']['error_code']==='REQUEST_REJECTED','CLI dispatch enabled by default');
    $dry=runChildTracked(array('dispatch','--dryRun=1')); dc($dry['exit']===0 && $dry['data']['dry_run']===true,'CLI dry-run'); $check=runChildTracked(array('check')); dc($check['exit']===0 && $check['data']['worker_enabled']===false,'CLI check observes default disabled configuration');
    foreach ($rowsBefore as $table=>$rows) { dc($db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll()===$rows,'CLI read-only command wrote '.$table); }
    dc($db->createCommand('SELECT * FROM reports ORDER BY id')->queryAll()===$baseReports,'Worker changed Reports');
} finally {
    foreach ($children as $child) { if (is_resource($child['process'])) { proc_terminate($child['process']); fclose($child['out']); fclose($child['err']); proc_close($child['process']); } }
    if ($peer && ($tx=$peer->getCurrentTransaction()) && $tx->active) { $tx->rollback(); }
    if ($trigger) { $db->createCommand('DROP TRIGGER IF EXISTS '.$trigger)->execute(); }
    $f->cleanup(); if ($peer) { $peer->setActive(false); }
}
dc($db->createCommand('SELECT * FROM users ORDER BY id')->queryAll()===$baseUsers,'Durability changed fixture users');
echo "PASS: $checks reminder durability assertions; rollback after notification insert, bounded retries, reconnect/recovery, two real worker processes, close/revoke races, CLI read-only checks and cleanup.\n";
