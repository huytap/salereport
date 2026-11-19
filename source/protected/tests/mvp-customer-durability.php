<?php
/** Commit/reconnect/replay and failed handover with independent visibility; test DB only. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
$app=Yii::createConsoleApplication(require __DIR__.'/../config/console.php');
class CustomerDurableActor extends CApplicationComponent { public $id=1; public function getIsGuest() { return false; } }
$app->setComponent('user',new CustomerDurableActor); $flags=$app->params['mvp']; $flags['customerWrite']=true; $app->params['mvp']=$flags;
$db=$app->db; $db->setActive(true);
if ($db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Synthetic isolated fixture required.'); }
require_once __DIR__.'/support/TemporaryTableFixture.php';
$usersBefore=$db->createCommand('SELECT * FROM users')->queryAll(); $ddl=$db->createCommand('SHOW CREATE TABLE users')->queryRow(); $db->createCommand(isolatedTemporaryTableDdl($ddl['Create Table']))->execute(); foreach ($usersBefore as $row) { $db->createCommand()->insert('users',$row); }
$checks=0; $aid=null; $pid=null; $hid=null; $tid=null; $peer=null; $label='WP04 durable '.bin2hex(random_bytes(6));
function cdCheck($ok,$message) { global $checks; if (!$ok) { throw new Exception($message); } $checks++; }
function cdSave($class,array $values) { $m=new $class; $m->setAttributes($values,false); cdCheck($m->save(),$class.': '.CJSON::encode($m->errors)); return $m; }
try {
    $user=new Users('create'); $user->attributes=array('username'=>'wp04_durable_owner','fullname'=>'Synthetic recipient','password'=>'fake','roles'=>'users','status'=>'A','last_login_date'=>'2026-11-18 00:00:00'); cdCheck($user->save(),'Synthetic temporary user');
    $h=cdSave('Hotel',array('code'=>'D4-'.bin2hex(random_bytes(6)),'name'=>$label)); $hid=$h->id;
    cdSave('UserHotelAccess',array('user_id'=>$user->id,'hotel_id'=>$hid,'scope_level'=>'own'));
    $createKey=bin2hex(random_bytes(16)); $r=AccountService::create(array('name'=>$label),$hid,1,array('first_name'=>'Synthetic durable contact'),array('VN'),$createKey); $aid=$r['account_id']; $pid=$r['contact_id'];
    $peer=Yii::createComponent(require __DIR__.'/../config/db-test.php'); $peer->setActive(true);
    cdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_account WHERE id=:id')->queryScalar(array(':id'=>$aid))===1,'Peer cannot see account commit');
    cdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_customer_request WHERE actor_user_id=1 AND request_key=:key')->queryScalar(array(':key'=>$createKey))===1,'Peer cannot see replay journal');
    $t=cdSave('Task',array('account_id'=>$aid,'contact_person_id'=>$pid,'hotel_id'=>$hid,'assignee_user_id'=>1,'code'=>'D4-'.bin2hex(random_bytes(8)),'client_request_key'=>bin2hex(random_bytes(16)),'title'=>'Durable task','due_mode'=>'date','due_date'=>'2030-01-01','timezone'=>'Asia/Ho_Chi_Minh')); $tid=$t->id;
    $reminder=cdSave('TaskReminder',array('task_id'=>$tid,'recipient_user_id'=>1,'schedule_version'=>1,'reminder_kind'=>'due_day','notify_at_utc'=>'2030-01-01 02:00:00'));
    $plan=AssignmentService::preview($aid,$hid); $handoverKey=bin2hex(random_bytes(16));
    $db->createCommand()->update('crm_contact_person',array('status'=>'archived'),'id=:id',array(':id'=>$pid));
    try { AssignmentService::handover($aid,$hid,$user->id,$plan['expected_version'],$plan['plan_hash'],'Durable atomic handover',$handoverKey); throw new Exception('Failed handover accepted'); }
    catch (CustomerValidationException $e) { cdCheck(true,'Expected task validation failure'); }
    cdCheck((int)$peer->createCommand('SELECT owner_user_id FROM crm_account_hotel WHERE account_id=:id')->queryScalar(array(':id'=>$aid))===1,'Failed handover committed owner');
    cdCheck((int)$peer->createCommand('SELECT schedule_version FROM crm_task WHERE id=:id')->queryScalar(array(':id'=>$tid))===1,'Failed handover committed task version');
    cdCheck($peer->createCommand('SELECT state FROM crm_task_reminder WHERE id=:id')->queryScalar(array(':id'=>$reminder->id))==='pending','Failed handover cancelled reminder');
    cdCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_customer_request WHERE request_key=:key')->queryScalar(array(':key'=>$handoverKey))===0,'Failed request committed journal');
    $db->createCommand()->update('crm_contact_person',array('status'=>'active'),'id=:id',array(':id'=>$pid));
    $done=AssignmentService::handover($aid,$hid,$user->id,$plan['expected_version'],$plan['plan_hash'],'Durable atomic handover',$handoverKey);
    cdCheck($done['moved_tasks']===1 && (int)$peer->createCommand('SELECT owner_user_id FROM crm_account_hotel WHERE account_id=:id')->queryScalar(array(':id'=>$aid))===(int)$user->id,'Peer cannot see handover');
    cdCheck((int)$peer->createCommand('SELECT schedule_version FROM crm_task WHERE id=:id')->queryScalar(array(':id'=>$tid))===2,'Peer cannot see task generation');
    cdCheck($peer->createCommand('SELECT state FROM crm_task_reminder WHERE id=:id')->queryScalar(array(':id'=>$reminder->id))==='cancelled','Peer cannot see old reminder invalidation');
    cdCheck((int)$peer->createCommand("SELECT COUNT(*) FROM crm_task_reminder WHERE task_id=:id AND schedule_version=2 AND state='pending'")->queryScalar(array(':id'=>$tid))===2,'Peer cannot see replacement schedules');
    $auditCount=$db->createCommand("SELECT COUNT(*) FROM crm_audit_event WHERE entity_type='task' AND entity_id=:id")->queryScalar(array(':id'=>(string)$tid));
    // Reconnect both connections: replay must use the committed journal, without in-memory state.
    $db->setActive(false); $db->setActive(true); $peer->setActive(false); $peer->setActive(true);
    cdCheck(AssignmentService::handover($aid,$hid,$user->id,$plan['expected_version'],$plan['plan_hash'],'Durable atomic handover',$handoverKey)['replayed'],'Replay after completed request');
    cdCheck($peer->createCommand("SELECT COUNT(*) FROM crm_audit_event WHERE entity_type='task' AND entity_id=:id")->queryScalar(array(':id'=>(string)$tid))===$auditCount,'Replay duplicated audit');
    cdCheck(AccountService::create(array('name'=>$label),$hid,1,array('first_name'=>'Synthetic durable contact'),array('VN'),$createKey)['replayed'],'Create replay after owner changed');
} finally {
    $cleanup=$db->beginTransaction();
    try {
        if ($tid) {
            foreach (array('crm_notification','crm_task_reminder') as $table) { $db->createCommand()->delete($table,'task_id=:id',array(':id'=>$tid)); }
            $db->createCommand()->delete('crm_task','id=:id',array(':id'=>$tid));
            $db->createCommand()->delete('crm_audit_event',"entity_type='task' AND entity_id=:id",array(':id'=>(string)$tid));
        }
        if ($aid) {
            $db->createCommand()->delete('crm_audit_event',"(entity_type='account' AND entity_id=:account) OR (entity_type='contact_person' AND entity_id=:person) OR (entity_type='account_hotel' AND entity_id=:pair)",array(':account'=>(string)$aid,':person'=>(string)$pid,':pair'=>$aid.':'.$hid));
            foreach (array('crm_customer_request','crm_contact_person','crm_account_market','crm_account_hotel') as $table) { $db->createCommand()->delete($table,'account_id=:id',array(':id'=>$aid)); }
            $db->createCommand()->delete('crm_account','id=:id',array(':id'=>$aid));
        }
        if ($hid) { $db->createCommand()->delete('crm_user_hotel_access','hotel_id=:id',array(':id'=>$hid)); $db->createCommand()->delete('crm_hotel','id=:id',array(':id'=>$hid)); }
        $cleanup->commit();
    } catch (Exception $e) { $cleanup->rollback(); throw $e; }
    $db->createCommand('DROP TEMPORARY TABLE IF EXISTS users')->execute(); if ($peer) { $peer->setActive(false); }
}
cdCheck($db->createCommand('SELECT * FROM users')->queryAll()===$usersBefore,'Real users changed');
echo "PASS: $checks customer durability assertions; independent commits, failed handover rollback, recipient/version/reminders, replay and run-owned cleanup.\n";
