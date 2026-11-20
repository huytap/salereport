<?php
if (PHP_SAPI!=='cli') { exit(2); }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
$config=require __DIR__.'/../config/console.php'; foreach (array('customerWrite','reportWrite','taskWrite') as $f) { $config['params']['mvp'][$f]=true; }
$app=Yii::createConsoleApplication($config); $db=$app->db; $db->active=true;
class MoveDurabilityActor extends CApplicationComponent { public $id=1; public function getIsGuest() { return false; } }
$app->setComponent('user',new MoveDurabilityActor);
if ($db->createCommand('SELECT DATABASE()')->queryScalar()!=='sale_report_mvp_test_cutover' || $db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1' || $db->createCommand('SELECT COUNT(*) FROM crm_account')->queryScalar() || $db->createCommand('SELECT COUNT(*) FROM crm_transfer_batch')->queryScalar() || CutoverService::state()['stage']!=='preparing') { throw new Exception('Dedicated empty synthetic WP08 schema required.'); }
$checks=0; function dc($ok,$m) { global $checks; if (!$ok) { throw new Exception($m); } $checks++; }
function csvJob($h,$name,$person='') { $s=fopen('php://temp','w+'); fputcsv($s,array('account_name','contact_first_name')); fputcsv($s,array($name,$person)); rewind($s); try { $id=CustomerTransferService::previewCsv($s,$h); } finally { fclose($s); } CustomerTransferService::map($id,1,1,array('mode'=>'create','contact_mode'=>$person===''?'none':'create','owner_user_id'=>1,'reason'=>'Synthetic durability approved')); $v=CustomerTransferService::view($id); CustomerTransferService::approve($id,$v['batch']['lock_version']); return $id; }
function spawnMove($id) { $cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/support/CustomerMoveProcess.php').' '.escapeshellarg((string)$id); $p=proc_open($cmd,array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes); fclose($pipes[0]); return array($p,$pipes); }
function finishMove($p) { $out=stream_get_contents($p[1][1]); $err=stream_get_contents($p[1][2]); fclose($p[1][1]); fclose($p[1][2]); return array('exit'=>proc_close($p[0]),'json'=>json_decode($out,true),'stderr'=>$err); }
$nonce=bin2hex(random_bytes(6)); $trigger='wp08_fault_'.$nonce; $hid=null; $batches=array(); $names=array('WP08 fault '.$nonce,'WP08 race '.$nonce); $gateBefore=CutoverService::state(); $auditBefore=(int)$db->createCommand('SELECT COALESCE(MAX(id),0) FROM crm_audit_event')->queryScalar();
$source=array(); foreach (array('contact','reports','users','roles','trackings','user_history') as $t) { $source[$t]=$db->createCommand('SELECT * FROM '.$t)->queryAll(); }
try {
    $h=new Hotel; $h->code='W08-'.strtoupper($nonce); $h->name='Synthetic durability Hotel'; CustomerWriteSupport::save($h); $hid=$h->id;
    $id=csvJob($hid,$names[0],'Fault person'); $batches[]=$id;
    $db->createCommand("CREATE TRIGGER `$trigger` BEFORE INSERT ON crm_contact_person FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic WP08 contact fault'")->execute();
    $res=CustomerTransferService::commit($id); dc(isset($res['counts']['error']),'Injected child insert error recorded'); dc(!Account::model()->exists('name=:name',array(':name'=>$names[0])),'Fault rolled back Account and assignment');
    dc(!(int)$db->createCommand('SELECT COUNT(*) FROM crm_customer_request')->queryScalar(),'Fault rolled back request journal'); dc(!(int)$db->createCommand('SELECT COUNT(*) FROM crm_account_hotel')->queryScalar(),'No orphan assignment');
    $db->createCommand('DROP TRIGGER `'.$trigger.'`')->execute(); $trigger=null;
    $res=CustomerTransferService::commit($id); dc($res['status']==='completed' && $res['consistent'],'Resume after DB fault'); dc(Account::model()->count('name=:name',array(':name'=>$names[0]))==1,'Exactly one durable Account');
    $id=csvJob($hid,$names[1]); $batches[]=$id; $p1=spawnMove($id); $p2=spawnMove($id); $a=finishMove($p1); $b=finishMove($p2);
    dc(in_array($a['exit'],array(0,3),true) && in_array($b['exit'],array(0,3),true) && !$a['stderr'] && !$b['stderr'],'Two independent processes finish cleanly: '.json_encode(array($a,$b)));
    $res=CustomerTransferService::commit($id); dc($res['status']==='completed' && $res['consistent'],'Concurrent commit converges'); dc(Account::model()->count('name=:name',array(':name'=>$names[1]))==1,'Concurrent commit creates one Account');
    dc((int)$db->createCommand('SELECT COUNT(*) FROM crm_customer_request')->queryScalar()===2,'One request journal per committed row');
    // A separate connection holding the mutex blocks freeze/commit without changing state.
    $other=new IsolatedTestDbConnection($db->connectionString,$db->username,$db->password); $other->charset='utf8mb4'; $other->active=true; $key=CustomerImportService::lockName();
    dc((int)$other->createCommand('SELECT GET_LOCK(:key,0)')->queryScalar(array(':key'=>$key))===1,'Independent lock acquired');
    try { CutoverService::transition('frozen',CutoverService::state()['lock_version'],'Concurrent freeze'); throw new Exception('Freeze ignored active writer mutex'); } catch (CHttpException $e) { dc($e->statusCode===409,'Freeze serialized with writers'); }
    dc(CutoverService::state()['stage']==='preparing','Contended freeze made no change'); $other->createCommand('SELECT RELEASE_LOCK(:key)')->queryScalar(array(':key'=>$key)); $other->active=false;
    // Current-read guard must see a cutover committed after an outer transaction's snapshot.
    $snapshot=$db->beginTransaction(); $db->createCommand('SELECT * FROM crm_account')->queryAll();
    $peer=new IsolatedTestDbConnection($db->connectionString,$db->username,$db->password); $peer->charset='utf8mb4'; $peer->active=true;
    $peer->createCommand()->update('crm_cutover',array('stage'=>'frozen'),'id=1');
    try { CutoverService::canonicalWrite(); throw new Exception('Stale snapshot bypassed freeze'); } catch (CHttpException $e) { dc($e->statusCode===503,'Gate uses current read after snapshot'); } finally { $snapshot->rollback(); $peer->createCommand()->update('crm_cutover',array('stage'=>'preparing'),'id=1'); $peer->active=false; }
    // In-flight dispatch retains a shared gate lock; freeze cannot commit before it finishes.
    $barrier=new IsolatedTestDbConnection($db->connectionString,$db->username,$db->password); $barrier->charset='utf8mb4'; $barrier->active=true;
    $gateTx=$barrier->beginTransaction(); $barrier->createCommand('SELECT id FROM crm_cutover WHERE id=1 LOCK IN SHARE MODE')->queryScalar();
    $timeout=(int)$db->createCommand('SELECT @@SESSION.innodb_lock_wait_timeout')->queryScalar(); $db->createCommand('SET SESSION innodb_lock_wait_timeout=1')->execute();
    try { CutoverService::transition('frozen',CutoverService::state()['lock_version'],'Synthetic active delivery barrier'); throw new Exception('Freeze passed an in-flight shared gate lock'); }
    catch (CDbException $e) { dc(isset($e->errorInfo[1]) && (int)$e->errorInfo[1]===1205,'Freeze waits for in-flight transaction'); }
    finally { $db->createCommand('SET SESSION innodb_lock_wait_timeout='.$timeout)->execute(); $gateTx->rollback(); $barrier->active=false; }
    dc(CutoverService::state()['stage']==='preparing','Blocked freeze rolled back state/audit');
    // Real committed freeze/close stops an explicitly enabled worker; dry-run stays read-only.
    $account=Account::model()->find('name=:name',array(':name'=>$names[0]));
    TaskService::create($account->id,$hid,array('title'=>'WP08 synthetic pending','kind'=>'task','due_mode'=>'datetime','due_at_utc'=>gmdate('Y-m-d H:i:s',time()+1200),'timezone'=>'Asia/Ho_Chi_Minh'),bin2hex(random_bytes(16)));
    $p=Yii::app()->params['mvp']; $p['reminders']=true; Yii::app()->params['mvp']=$p;
    $gate=CutoverService::transition('frozen',CutoverService::state()['lock_version'],'Synthetic committed freeze');
    $queueBefore=array(); foreach (array('crm_task_reminder','crm_notification','crm_reminder_dispatch_run') as $t) { $queueBefore[$t]=$db->createCommand('SELECT * FROM '.$t)->queryAll(); }
    try { ReminderDispatchService::dispatch(); throw new Exception('Enabled worker bypassed freeze'); } catch (CHttpException $e) { dc($e->statusCode===503,'Freeze stops enabled worker'); }
    dc(ReminderDispatchService::dispatch(50,true)['dry_run']===true,'Freeze permits dry-run review');
    CutoverService::transition('closed',$gate['lock_version'],'Synthetic incident close');
    try { ReminderDispatchService::dispatch(); throw new Exception('Enabled worker bypassed close'); } catch (CHttpException $e) { dc($e->statusCode===503,'Closed stops enabled worker'); }
    foreach ($queueBefore as $t=>$rows) { dc($db->createCommand('SELECT * FROM '.$t)->queryAll()===$rows,'Frozen/closed dispatch and dry-run preserve '.$t); }
    foreach ($source as $t=>$rows) { dc($db->createCommand('SELECT * FROM '.$t)->queryAll()===$rows,'Source preserved '.$t); }
} finally {
    if ($trigger) { $db->createCommand('DROP TRIGGER IF EXISTS `'.$trigger.'`')->execute(); }
    $tx=$db->beginTransaction();
    try {
        $accounts=$db->createCommand('SELECT id FROM crm_account WHERE name IN (:a,:b)')->queryColumn(array(':a'=>$names[0],':b'=>$names[1]));
        foreach ($accounts as $id) {
            foreach ($db->createCommand('SELECT id FROM crm_task WHERE account_id=:id')->queryColumn(array(':id'=>$id)) as $taskId) { $db->createCommand()->delete('crm_task_reminder','task_id=:id',array(':id'=>$taskId)); $db->createCommand()->delete('crm_task','id=:id',array(':id'=>$taskId)); }

            $db->createCommand()->delete('crm_customer_request','account_id=:id',array(':id'=>$id));
            $db->createCommand()->delete('crm_contact_person','account_id=:id',array(':id'=>$id));
            $db->createCommand()->delete('crm_account_market','account_id=:id',array(':id'=>$id));
            $db->createCommand()->delete('crm_account_hotel','account_id=:id',array(':id'=>$id));
            $db->createCommand()->delete('crm_account','id=:id',array(':id'=>$id));
        }
        foreach ($batches as $id) { $db->createCommand()->delete('crm_transfer_row','batch_id=:id',array(':id'=>$id)); $db->createCommand()->delete('crm_transfer_batch','id=:id',array(':id'=>$id)); }
        if ($hid) { $db->createCommand()->delete('crm_hotel','id=:id',array(':id'=>$hid)); }
        // This fixture requires an otherwise empty dedicated schema; all later audit IDs belong to these jobs.
        $db->createCommand()->delete('crm_audit_event','id>:before AND actor_user_id=1',array(':before'=>$auditBefore));
        // Restore the dedicated synthetic gate fixture; production has no such reset endpoint.
        $restore=$gateBefore; unset($restore['id']); $db->createCommand()->update('crm_cutover',$restore,'id=1'); $tx->commit();
    } catch (Exception $e) { $tx->rollback(); throw $e; }
}
dc(!(int)$db->createCommand('SELECT COUNT(*) FROM crm_account')->queryScalar() && !(int)$db->createCommand('SELECT COUNT(*) FROM crm_transfer_batch')->queryScalar(),'Owned fixtures cleaned');
echo "PASS: $checks WP08 durability assertions; child failure rollback/resume, two real processes, mutex serialization, source preservation and cleanup.\n";
