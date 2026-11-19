<?php
/** Real transactions, independent visibility, late task failure and whole-request retries. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
$app=Yii::createConsoleApplication(require __DIR__.'/../config/console.php');
class ReportDurableActor extends CApplicationComponent { public $id=1; public function getIsGuest() { return false; } }
$app->setComponent('user',new ReportDurableActor); $flags=$app->params['mvp']; $flags['customerWrite']=true; $flags['taskWrite']=true; $flags['reportWrite']=true; $app->params['mvp']=$flags;
$db=$app->db; $db->setActive(true); if ($db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Synthetic isolated fixture required.'); }
$checks=0; $aid=null; $hid=null; $trigger=null; $peer=null; $label='WP06 durable '.bin2hex(random_bytes(6));
function rdCheck($ok,$message) { global $checks; if (!$ok) { throw new Exception($message); } $checks++; }
function rdFields($label) { return array('date'=>'2026-11-19','report'=>$label,'sales_type'=>'Visit','objective'=>'Follow up','expected_result'=>'Groups'); }
function rdTask($title) { return array('fields'=>array('title'=>$title,'kind'=>'task','due_mode'=>'date','due_date'=>'2030-01-08','due_at_utc'=>null,'starts_at_utc'=>null,'ends_at_utc'=>null,'timezone'=>'Asia/Ho_Chi_Minh')); }
try {
    $h=new Hotel; $h->code='D6-'.bin2hex(random_bytes(6)); $h->name=$label; rdCheck($h->save(),'Hotel fixture'); $hid=$h->id;
    $a=AccountService::create(array('name'=>$label),$hid,1,null,array(),bin2hex(random_bytes(16))); $aid=$a['account_id'];
    $peer=Yii::createComponent(require __DIR__.'/../config/db-test.php'); $peer->setActive(true);
    $key=bin2hex(random_bytes(16)); $rows=array(rdTask($label.' first'),rdTask($label.' fail')); $contact=array('first_name'=>$label.' contact'); $fields=rdFields($label.' original');
    $trigger='wp06_fail_'.bin2hex(random_bytes(6)); $guardTitle=$db->quoteValue($label.' fail');
    $db->createCommand('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON crm_task_reminder FOR EACH ROW BEGIN IF COALESCE(@wp06_mode,0)>0 AND EXISTS (SELECT 1 FROM crm_task WHERE id=NEW.task_id AND title='.$guardTitle.') THEN SET @wp06_attempt=COALESCE(@wp06_attempt,0)+1; IF @wp06_mode=1 THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Synthetic last-task failure\'; ELSEIF @wp06_mode=2 OR (@wp06_mode=3 AND @wp06_attempt=1) THEN SIGNAL SQLSTATE \'40001\' SET MYSQL_ERRNO=1213,MESSAGE_TEXT=\'Synthetic Report deadlock\'; END IF; END IF; END')->execute();
    $auditBefore=(int)$db->createCommand('SELECT COUNT(*) FROM crm_audit_event')->queryScalar();
    $db->createCommand('SET @wp06_mode=1,@wp06_attempt=0')->execute();
    try { ReportService::create($aid,$hid,null,$fields,$rows,$key,$contact); throw new Exception('Last task failure accepted'); }
    catch (CDbException $e) { rdCheck(strpos($e->getMessage(),'Synthetic last-task failure')!==false,'Wrong injected failure'); }
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM reports WHERE account_id=:id')->queryScalar(array(':id'=>$aid))===0,'Report committed before last task');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_task WHERE account_id=:id')->queryScalar(array(':id'=>$aid))===0,'Early tasks left behind');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_contact_person WHERE account_id=:id')->queryScalar(array(':id'=>$aid))===0,'Quick contact orphan');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_customer_request WHERE account_id=:id AND operation<>\'account_create\'')->queryScalar(array(':id'=>$aid))===0,'Partial parent/child journal');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_audit_event')->queryScalar()===$auditBefore,'Partial audit committed');
    $db->createCommand('SET @wp06_mode=2,@wp06_attempt=0')->execute();
    try { ReportService::create($aid,$hid,null,$fields,$rows,$key,$contact); throw new Exception('Persistent deadlock accepted'); }
    catch (CDbException $e) { rdCheck(isset($e->errorInfo[1]) && (int)$e->errorInfo[1]===1213,'Expected deadlock vendor code'); }
    rdCheck((int)$db->createCommand('SELECT @wp06_attempt')->queryScalar()===3,'Whole Report retry exceeds three attempts');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM reports WHERE account_id=:id')->queryScalar(array(':id'=>$aid))===0,'Retry left orphan Report');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_contact_person WHERE account_id=:id')->queryScalar(array(':id'=>$aid))===0,'Retry left quick contacts');
    $db->createCommand('SET @wp06_mode=3,@wp06_attempt=0')->execute();
    $result=ReportService::create($aid,$hid,null,$fields,$rows,$key,$contact); $rid=$result['report_id'];
    rdCheck((int)$db->createCommand('SELECT @wp06_attempt')->queryScalar()===3,'Retry once, then both last-task reminders');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM reports WHERE account_id=:id')->queryScalar(array(':id'=>$aid))===1,'Report exactly once');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_task WHERE account_id=:id')->queryScalar(array(':id'=>$aid))===2,'Tasks exactly once');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_task_reminder r INNER JOIN crm_task t ON t.id=r.task_id WHERE t.account_id=:id')->queryScalar(array(':id'=>$aid))===4,'Reminder plans exactly once');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_contact_person WHERE account_id=:id')->queryScalar(array(':id'=>$aid))===1,'Quick contact exactly once');
    $peerReport=$peer->createCommand('SELECT * FROM reports WHERE id=:id')->queryRow(true,array(':id'=>$rid));
    rdCheck($peerReport['snapshot_json'] && $peerReport['created_at_utc'] && (int)$peerReport['contact_person_id']===(int)$result['contact_id'],'Peer snapshot/context/UTC');
    foreach ($result['tasks'] as $task) { $t=$peer->createCommand('SELECT report_id,contact_person_id FROM crm_task WHERE id=:id')->queryRow(true,array(':id'=>$task['task_id'])); rdCheck((int)$t['report_id']===(int)$rid && (int)$t['contact_person_id']===(int)$result['contact_id'],'Peer task references'); }
    $audit=(int)$peer->createCommand('SELECT COUNT(*) FROM crm_audit_event')->queryScalar(); $db->setActive(false); $db->setActive(true); $peer->setActive(false); $peer->setActive(true);
    rdCheck(ReportService::create($aid,$hid,null,$fields,$rows,$key,$contact)['replayed'],'Create replay after reconnect');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_audit_event')->queryScalar()===$audit,'Replay duplicated audit');
    $firstTask=$result['tasks'][0]['task_id']; TaskService::complete($firstTask,1,'Completed before Report edit',bin2hex(random_bytes(16))); $taskBefore=$peer->createCommand('SELECT * FROM crm_task WHERE id=:id')->queryRow(true,array(':id'=>$firstTask));
    $updateKey=bin2hex(random_bytes(16)); $db->createCommand('SET @wp06_mode=1,@wp06_attempt=0')->execute();
    try { ReportService::update($rid,array('report'=>'Must rollback'),$rows,1,$updateKey); throw new Exception('Update partial failure accepted'); }
    catch (CDbException $e) { rdCheck(strpos($e->getMessage(),'Synthetic last-task failure')!==false,'Expected update failure'); }
    $report=$peer->createCommand('SELECT * FROM reports WHERE id=:id')->queryRow(true,array(':id'=>$rid));
    rdCheck($report['report']===$fields['report'] && (int)$report['lock_version']===1 && $report['snapshot_json']===$peerReport['snapshot_json'],'Update rollback changed Report');
    rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_task WHERE account_id=:id')->queryScalar(array(':id'=>$aid))===2,'Update left early appended task');
    rdCheck($peer->createCommand('SELECT * FROM crm_task WHERE id=:id')->queryRow(true,array(':id'=>$firstTask))===$taskBefore,'Report changed existing completed task');
    $db->createCommand('SET @wp06_mode=0')->execute(); $updated=ReportService::update($rid,array('report'=>'Successful revision'),$rows,1,$updateKey);
    rdCheck($updated['version']===2 && count($updated['tasks'])===2,'Update append result');
    rdCheck($peer->createCommand('SELECT * FROM crm_task WHERE id=:id')->queryRow(true,array(':id'=>$firstTask))===$taskBefore,'Successful Report edit touched existing task');
    rdCheck($peer->createCommand('SELECT snapshot_json FROM reports WHERE id=:id')->queryScalar(array(':id'=>$rid))===$peerReport['snapshot_json'],'Successful edit rewrote snapshot');
    rdCheck(ReportService::update($rid,array('report'=>'Successful revision'),$rows,1,$updateKey)['replayed'],'Update replay');
    // A browser holds Report FOR UPDATE. Timeout must leave no content/task/journal change.
    $peerTx=$peer->beginTransaction(); $peer->createCommand('SELECT id FROM reports WHERE id=:id FOR UPDATE')->queryScalar(array(':id'=>$rid));
    $timeout=(int)$db->createCommand('SELECT @@SESSION.innodb_lock_wait_timeout')->queryScalar(); $lockKey=bin2hex(random_bytes(16));
    try {
        $db->createCommand('SET SESSION innodb_lock_wait_timeout=1')->execute();
        try { ReportService::update($rid,array('comments'=>'After browser lock'),array(),2,$lockKey); throw new Exception('Report lock ignored'); }
        catch (CDbException $e) { rdCheck(isset($e->errorInfo[1]) && (int)$e->errorInfo[1]===1205,'Expected lock timeout'); }
        rdCheck((int)$peer->createCommand('SELECT lock_version FROM reports WHERE id=:id')->queryScalar(array(':id'=>$rid))===2,'Blocked write changed Report');
        rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_customer_request WHERE request_key=:key')->queryScalar(array(':key'=>$lockKey))===0,'Blocked write left journal');
    } finally { if ($peerTx->active) { $peerTx->rollback(); } $db->createCommand('SET SESSION innodb_lock_wait_timeout='.$timeout)->execute(); }
    rdCheck(ReportService::update($rid,array('comments'=>'After browser lock'),array(),2,$lockKey)['version']===3,'Retry after lock release');
    // Current scope must override an older snapshot in a compound caller's transaction.
    require_once __DIR__.'/support/TemporaryTableFixture.php'; $realUsers=$db->createCommand('SELECT * FROM users')->queryAll(); $ddl=$db->createCommand('SHOW CREATE TABLE users')->queryRow(); $db->createCommand(isolatedTemporaryTableDdl($ddl['Create Table']))->execute(); foreach ($realUsers as $row) { $db->createCommand()->insert('users',$row); }
    $u=new Users('create'); $u->attributes=array('username'=>'wp06_snapshot_actor','fullname'=>'Synthetic snapshot actor','password'=>'fake','roles'=>'users','status'=>'A','last_login_date'=>'2026-11-19 00:00:00'); rdCheck($u->save(),'Snapshot actor');
    foreach (array('revoke','handover') as $race) {
        $db->createCommand()->insert('crm_user_hotel_access',array('user_id'=>$u->id,'hotel_id'=>$hid,'scope_level'=>'own','updated_by'=>1,'updated_at_utc'=>gmdate('Y-m-d H:i:s')));
        $db->createCommand()->update('crm_account_hotel',array('owner_user_id'=>$u->id),'account_id=:id',array(':id'=>$aid)); $app->user->id=$u->id; $outer=$db->beginTransaction(); $raceKey=bin2hex(random_bytes(16));
        try {
            $db->createCommand('SELECT owner_user_id FROM crm_account_hotel WHERE account_id=:id')->queryScalar(array(':id'=>$aid));
            if ($race==='revoke') { $peer->createCommand()->delete('crm_user_hotel_access','user_id=:user AND hotel_id=:hotel',array(':user'=>$u->id,':hotel'=>$hid)); }
            else { $peer->createCommand()->update('crm_account_hotel',array('owner_user_id'=>1),'account_id=:id',array(':id'=>$aid)); }
            try { ReportService::create($aid,$hid,null,$fields,array(),$raceKey); throw new Exception('Stale scope accepted'); } catch (CHttpException $e) { rdCheck($e->statusCode===404,'Scope must be current: '.$race); }
            rdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_customer_request WHERE request_key=:key')->queryScalar(array(':key'=>$raceKey))===0,'Denied snapshot left journal');
        } finally { if ($outer->active) { $outer->rollback(); } $app->user->id=1; }
        $db->createCommand()->delete('crm_user_hotel_access','user_id=:user AND hotel_id=:hotel',array(':user'=>$u->id,':hotel'=>$hid));
    }
    $db->createCommand('DROP TEMPORARY TABLE users')->execute(); rdCheck($db->createCommand('SELECT * FROM users')->queryAll()===$realUsers,'Snapshot tests altered real users');
} finally {
    if ($trigger) { $db->createCommand('DROP TRIGGER IF EXISTS '.$trigger)->execute(); }
    $cleanup=$db->beginTransaction();
    try {
        if ($aid) {
            $taskIds=$db->createCommand('SELECT id FROM crm_task WHERE account_id=:id')->queryColumn(array(':id'=>$aid));
            foreach ($taskIds as $id) { foreach (array('crm_notification','crm_task_reminder') as $table) { $db->createCommand()->delete($table,'task_id=:id',array(':id'=>$id)); } $db->createCommand()->delete('crm_audit_event',"entity_type='task' AND entity_id=:id",array(':id'=>(string)$id)); }
            $db->createCommand()->delete('crm_task','account_id=:id',array(':id'=>$aid));
            foreach ($db->createCommand('SELECT id FROM reports WHERE account_id=:id')->queryColumn(array(':id'=>$aid)) as $id) { $db->createCommand()->delete('crm_audit_event',"entity_type='report' AND entity_id=:id",array(':id'=>(string)$id)); }
            $db->createCommand()->delete('reports','account_id=:id',array(':id'=>$aid));
            foreach ($db->createCommand('SELECT id FROM crm_contact_person WHERE account_id=:id')->queryColumn(array(':id'=>$aid)) as $id) { $db->createCommand()->delete('crm_audit_event',"entity_type='contact_person' AND entity_id=:id",array(':id'=>(string)$id)); }
            $db->createCommand()->delete('crm_audit_event',"(entity_type='account' AND entity_id=:id) OR (entity_type='account_hotel' AND entity_id=:pair)",array(':id'=>(string)$aid,':pair'=>$aid.':'.$hid));
            foreach (array('crm_customer_request','crm_contact_person','crm_account_market','crm_account_hotel') as $table) { $db->createCommand()->delete($table,'account_id=:id',array(':id'=>$aid)); } $db->createCommand()->delete('crm_account','id=:id',array(':id'=>$aid));
        }
        if ($hid) { $db->createCommand()->delete('crm_user_hotel_access','hotel_id=:id',array(':id'=>$hid)); $db->createCommand()->delete('crm_hotel','id=:id',array(':id'=>$hid)); } $cleanup->commit();
    } catch (Exception $e) { $cleanup->rollback(); throw $e; }
    $db->createCommand('DROP TEMPORARY TABLE IF EXISTS users')->execute(); if ($peer) { $peer->setActive(false); }
}
echo "PASS: $checks Report durability assertions; last-task rollback, quick-contact rollback, bounded whole-request retry, independent commits, reconnect/replay, lock contention and stale scope.\n";
