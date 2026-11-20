<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ob_start(); define('IS_IN_TESTS',true); define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
class ReminderTestUser extends CWebUser { public static $current=1; public function init() {} public function getId() { return self::$current; } public function getIsGuest() { return self::$current===null; } }
class ReminderTestRequest extends CHttpRequest { public $method='GET'; public function getRequestType() { return $this->method; } public function getIsPostRequest() { return $this->method==='POST'; } }
class ReminderRedirect extends Exception {}
$root=dirname(__DIR__,2); $_SERVER['SCRIPT_FILENAME']=$root.'/index.php'; $_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['HTTP_HOST']='crm.test.local'; $_SERVER['REQUEST_URI']='/'; $_SERVER['REQUEST_METHOD']='GET';
$config=require __DIR__.'/../config/test.php'; $config['components']['user']=array('class'=>'ReminderTestUser'); $config['components']['request']=array('class'=>'ReminderTestRequest','enableCsrfValidation'=>true);
$app=Yii::createWebApplication($config); $db=$app->db; $db->setActive(true); Yii::import('application.controllers.*');
class ReminderControllerTest extends ReminderController { public function redirect($url,$terminate=true,$statusCode=302) { throw new ReminderRedirect; } }
require_once __DIR__.'/support/TemporaryTableFixture.php'; require_once __DIR__.'/support/ReminderTestFixture.php'; $fixture=new ReminderTestFixture; $checks=0;
function nc($ok,$message) { global $checks; if (!$ok) { throw new Exception($message); } $checks++; }
function ns($code,$fn,$message) { try { $fn(); } catch (CHttpException $e) { nc($e->statusCode===$code,$message.' got '.$e->statusCode.' '.$e->getMessage()); return; } throw new Exception($message.' not rejected'); }
function nh($action,$get=array(),$post=array(),$method='GET',$csrf=true) {
    $_GET=$get; $_POST=$post; Yii::app()->request->method=$method; http_response_code(200); if ($method==='POST' && $csrf) { $_POST[Yii::app()->request->csrfTokenName]=Yii::app()->request->csrfToken; }
    $c=new ReminderControllerTest('reminder'); Yii::app()->controller=$c; $c->init(); ob_start(); try { $c->run($action); return ob_get_contents(); } catch (ReminderRedirect $e) { return 'redirect'; } finally { ob_end_clean(); }
}
function nr($class,$fields) { $m=new $class; $m->setAttributes($fields,false); nc($m->save(),$class.': '.CJSON::encode($m->errors)); return $m; }
function queueHash() { $db=Yii::app()->db; $rows=array(); foreach (array('crm_task','crm_task_reminder','crm_notification','crm_reminder_dispatch_run') as $table) { $rows[$table]=$db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll(); } return hash('sha256',CJSON::encode($rows)); }
$baseline=array(); foreach (array('users','roles') as $table) { $baseline[$table]=$db->createCommand('SELECT * FROM '.$table)->queryAll(); $ddl=$db->createCommand('SHOW CREATE TABLE '.$table)->queryRow(); $db->createCommand(isolatedTemporaryTableDdl($ddl['Create Table']))->execute(); foreach ($baseline[$table] as $row) { $db->createCommand()->insert($table,$row); } }
$sources=array(); foreach (array('contact','reports','trackings','user_history') as $table) { $sources[$table]=$db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll(); }
try {
    $actors=array(); foreach (array('a'=>'users','b'=>'users','other'=>'users','manager'=>'admin','reader'=>'reservation','super'=>'superadmin') as $label=>$role) { $u=new Users('create'); $u->attributes=array('username'=>'wp07_'.$label,'fullname'=>'WP07 '.$label,'password'=>'fake','roles'=>$role,'status'=>'A','last_login_date'=>'2026-11-19 00:00:00'); nc($u->save(),'User fixture'); $actors[$label]=(int)$u->id; }
    $h=$fixture->hotel('Hotel A'); $h2=$fixture->hotel('Hotel B');
    foreach (array(array('a',$h,'own'),array('b',$h,'own'),array('other',$h2,'own'),array('manager',$h,'hotel')) as $g) { nr('UserHotelAccess',array('user_id'=>$actors[$g[0]],'hotel_id'=>$g[1]->id,'scope_level'=>$g[2])); }
    $aid=$fixture->account($h->id,$actors['a'],'Account A'); $other=$fixture->account($h2->id,$actors['other'],'Account B');
    $task=$fixture->task($aid,$h->id,'<script>unsafe()</script>'); $slot=$fixture->due($task); $future=$fixture->task($aid,$h->id,'Future',array('due_at_utc'=>'2030-01-08 03:00:00'));
    $snapshot=queueHash(); $health=ReminderDispatchService::check(); nc(in_array('NEVER_COMPLETED',$health['alerts'],true),'Never-run alert'); nc(queueHash()===$snapshot,'Check wrote data');
    $dry=$fixture->dispatch(200,true); nc($dry['would_deliver']===1 && $dry['would_cancel']===0 && $dry['selected_count']===1,'Dry-run classifies only due slot'); nc(queueHash()===$snapshot,'Dry-run wrote data/heartbeat');
    $flags=$app->params['mvp']; $off=$flags; $off['reminders']=false; $app->params['mvp']=$off;
    ns(503,function(){ReminderDispatchService::dispatch();},'Dispatch feature gate'); nc(ReminderDispatchService::dispatch(200,true)['would_deliver']===1,'Disabled dry-run still read only'); $app->params['mvp']=$flags;
    $tx=$db->beginTransaction(); try { ns(409,function(){ReminderDispatchService::dispatch();},'Dispatch nested transaction'); } finally { $tx->rollback(); }
    $actualDb=$app->db; $ordinary=new CDbConnection('mysql:host=127.0.0.1;port=9;dbname=not_a_test_schema'); $ordinary->autoConnect=false;
    try { $app->setComponent('db',$ordinary); ns(503,function(){ReminderDispatchService::dispatch(1,true);},'Worker rejects a regular connection before opening PDO'); nc(!$ordinary->active,'Rejected connection must remain unopened'); } finally { $app->setComponent('db',$actualDb); }
    ns(400,function(){ReminderDispatchService::dispatch(501);},'Batch upper bound'); ns(400,function(){ReminderDispatchService::dispatch('1 OR 1');},'Batch injection');
    $run=$fixture->dispatch(1); nc($run['created_count']===1 && $run['processed_count']===1 && $run['status']==='completed','Dispatch creates one notification');
    $delivered=TaskReminder::model()->findByPk($slot->id); $notice=Notification::model()->find('reminder_id=:id',array(':id'=>$slot->id));
    nc($delivered->state==='delivered' && $delivered->delivered_at_utc && (int)$delivered->attempt_count===1,'Delivered metadata'); nc($notice && $notice->read_at_utc===null,'Created notification unread');
    $taskNow=Task::model()->findByPk($task->id); nc($taskNow->attributes===$task->attributes,'Dispatch changed Task content/version');
    $again=$fixture->dispatch(); nc($again['created_count']===0 && $again['processed_count']===0 && (int)Notification::model()->count()===1,'Dispatch repeat duplicates notification');
    nc(ReminderDispatchService::check()['healthy'],'Completed heartbeat health');
    ReminderTestUser::$current=$actors['a']; $before=queueHash(); $summary=ReminderService::summary(); nc($summary['unread_count']===1 && count($summary['items'])===1 && !isset($summary['monitor']),'Recipient summary and no global monitor leak');
    nc(queueHash()===$before,'GET summary marks read'); nc(ReminderService::inbox('unread')['total']===1,'Inbox unread');
    $html=nh('admin'); nc(strpos($html,'&lt;script&gt;unsafe()&lt;/script&gt;')!==false && strpos($html,'<script>unsafe()</script>')===false && strpos($html,'Đánh dấu đã đọc')!==false,'Inbox HTML/XSS/read form');
    $json=CJSON::decode(nh('summary',array('format'=>'json'))); nc($json['unread_count']===1,'Summary endpoint');
    $err=CJSON::decode(nh('read',array('id'=>$notice->id,'format'=>'json'))); nc(http_response_code()===405 && $err['error_code']==='METHOD_NOT_ALLOWED','GET read rejected');
    $err=CJSON::decode(nh('read',array('id'=>$notice->id,'format'=>'json'),array(),'POST',false)); nc(http_response_code()===400,'Read CSRF');
    foreach (array($actors['b'],$actors['manager'],1) as $actor) { ReminderTestUser::$current=$actor; ns(404,function()use($notice){ReminderService::read($notice->id);},'Other recipient including All cannot mark read'); }
    ReminderTestUser::$current=$actors['a']; $read=CJSON::decode(nh('read',array('id'=>$notice->id,'format'=>'json'),array(),'POST')); nc($read['read_at_utc'] && ReminderService::summary()['unread_count']===0 && ReminderService::inbox('read')['total']===1,'POST read and counts');
    $db->createCommand()->update('crm_notification',array('read_at_utc'=>'2020-01-01 00:00:00'),'id=:id',array(':id'=>$notice->id)); nc(ReminderService::read($notice->id)['read_at_utc']==='2020-01-01 00:00:00','Read retry preserves first timestamp');
    // Changes after delivery are hidden immediately, not merely at the next worker run.
    TaskService::reschedule($task->id,array('kind'=>'task','due_mode'=>'datetime','due_date'=>null,'due_at_utc'=>gmdate('Y-m-d H:i:s',time()+1200),'starts_at_utc'=>null,'ends_at_utc'=>null,'timezone'=>'Asia/Ho_Chi_Minh'),1,'Changed after notification',bin2hex(random_bytes(16)));
    nc(ReminderService::inbox('all')['total']===0,'Old version visible after reschedule'); ns(404,function()use($notice){ReminderService::read($notice->id);},'Read stale version');
    $fixture->dispatch(); nc(ReminderService::summary()['unread_count']===1,'New generation delivered');
    $currentNotice=Notification::model()->find('task_id=:id AND schedule_version=2',array(':id'=>$task->id)); TaskService::complete($task->id,2,'Finished',bin2hex(random_bytes(16)));
    nc(ReminderService::inbox('all')['total']===0,'Closed task notification visible'); ns(404,function()use($currentNotice){ReminderService::read($currentNotice->id);},'Read closed-task notification');
    ReminderTestUser::$current=1;
    $cases=array('closed'=>'TASK_CLOSED','version'=>'STALE_SCHEDULE','recipient'=>'STALE_RECIPIENT','owner'=>'OWNER_CHANGED','inactive'=>'INACTIVE_USER','grant'=>'HOTEL_ACCESS_REVOKED','context'=>'INACTIVE_CONTEXT','missed'=>'MISSED_DEADLINE','action'=>'ACTION_REVOKED');
    foreach ($cases as $case=>$reason) {
        $a=$fixture->account($h->id,$actors['a'],'invalid '.$case); $t=$fixture->task($a,$h->id,'invalid '.$case); $r=$fixture->due($t); $roleBefore=null;
        if ($case==='closed') { $db->createCommand()->update('crm_task',array('status'=>'cancelled','cancelled_at_utc'=>gmdate('Y-m-d H:i:s'),'cancel_reason'=>'Synthetic external close'),'id=:id',array(':id'=>$t->id)); }
        elseif ($case==='version') { $db->createCommand()->update('crm_task',array('schedule_version'=>2),'id=:id',array(':id'=>$t->id)); }
        elseif ($case==='recipient') { $db->createCommand()->update('crm_task_reminder',array('recipient_user_id'=>$actors['b']),'id=:id',array(':id'=>$r->id)); }
        elseif ($case==='owner') { $db->createCommand()->update('crm_account_hotel',array('owner_user_id'=>$actors['b']),'account_id=:id',array(':id'=>$a)); }
        elseif ($case==='inactive') { $db->createCommand()->update('users',array('status'=>'I'),'id=:id',array(':id'=>$actors['a'])); }
        elseif ($case==='grant') { $db->createCommand()->delete('crm_user_hotel_access','user_id=:id AND hotel_id=:hotel',array(':id'=>$actors['a'],':hotel'=>$h->id)); }
        elseif ($case==='context') { $db->createCommand()->update('crm_account',array('status'=>'archived'),'id=:id',array(':id'=>$a)); }
        elseif ($case==='missed') { $db->createCommand()->update('crm_task',array('due_at_utc'=>'2020-01-01 00:00:00','deadline_at_utc'=>'2020-01-01 00:00:00'),'id=:id',array(':id'=>$t->id)); }
        elseif ($case==='action') { $roleBefore=$db->createCommand("SELECT access_rights FROM roles WHERE role_name='users'")->queryScalar(); $rights=CJSON::decode($roleBefore); $rights['task']=array_values(array_diff($rights['task'],array('view'))); $db->createCommand()->update('roles',array('access_rights'=>CJSON::encode($rights)),"role_name='users'"); }
        $out=$fixture->dispatch(); $rr=TaskReminder::model()->findByPk($r->id); nc($out['cancelled_count']===1 && $rr->state==='cancelled' && $rr->last_error_code===$reason,'Invalid slot cancelled: '.$case); nc(!Notification::model()->exists('reminder_id=:id',array(':id'=>$r->id)),'Invalid slot produced notification: '.$case);
        if ($case==='inactive') { $db->createCommand()->update('users',array('status'=>'A'),'id=:id',array(':id'=>$actors['a'])); }
        if ($case==='grant') { nr('UserHotelAccess',array('user_id'=>$actors['a'],'hotel_id'=>$h->id,'scope_level'=>'own')); }
        if ($case==='action') { $db->createCommand()->update('roles',array('access_rights'=>$roleBefore),"role_name='users'"); }
    }
    $a=$fixture->account($h->id,$actors['a'],'Recover'); $t=$fixture->task($a,$h->id,'Recover'); $r=$fixture->due($t);
    $n=nr('Notification',array('reminder_id'=>$r->id,'task_id'=>$t->id,'recipient_user_id'=>$actors['a'],'schedule_version'=>1,'kind'=>'before'));
    ReminderTestUser::$current=$actors['a']; nc(ReminderService::inbox('all')['total']===0,'Pending reminder notification must not appear delivered');
    $snap=queueHash(); nc($fixture->dispatch(200,true)['would_recover']===1 && queueHash()===$snap,'Dry-run recovery no writes'); $out=$fixture->dispatch(); nc($out['recovered_count']===1 && $out['created_count']===0,'Recover pre-existing slot without duplicate');
    nc(ReminderService::summary()['unread_count']===1,'Recovered notification visible');
    $roleBefore=$db->createCommand("SELECT access_rights FROM roles WHERE role_name='users'")->queryScalar(); $rights=CJSON::decode($roleBefore); $rights['task']=array_values(array_diff($rights['task'],array('view'))); $db->createCommand()->update('roles',array('access_rights'=>CJSON::encode($rights)),"role_name='users'"); nc(ReminderService::summary()['unread_count']===0,'Task-view permission revoked hides notification'); $db->createCommand()->update('roles',array('access_rights'=>$roleBefore),"role_name='users'");
    ReminderTestUser::$current=$actors['manager']; $plan=AssignmentService::preview($a,$h->id); AssignmentService::handover($a,$h->id,$actors['b'],$plan['expected_version'],$plan['plan_hash'],'Hand over reminder ownership',bin2hex(random_bytes(16)));
    ReminderTestUser::$current=$actors['a']; nc(ReminderService::inbox('all')['total']===0,'Former owner sees notification after handover');
    $fixture->dispatch(); ReminderTestUser::$current=$actors['b']; nc(ReminderService::summary()['unread_count']===1,'New owner receives replacement schedule');
    ReminderTestUser::$current=$actors['other']; nc(ReminderService::summary()['unread_count']===0,'Other Hotel notification scope');
    ReminderTestUser::$current=$actors['manager']; ns(403,function(){ReminderService::monitor();},'Hotel manager global monitoring');
    ReminderTestUser::$current=$actors['reader']; $err=CJSON::decode(nh('admin',array('format'=>'json'))); nc(http_response_code()===403 && $err['error_code']==='FORBIDDEN','Read-only role has no inbox action');
    ReminderTestUser::$current=1; $global=ReminderService::summary(); nc(isset($global['monitor']) && $global['unread_count']===0,'All does not inherit others inbox');
    ReminderTestUser::$current=$actors['a']; $beforeOverdue=ReminderService::summary()['overdue_task_count']; $beforeUnread=ReminderService::summary()['unread_count'];
    $today=(new DateTimeImmutable('today',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d');
    $dateOnly=$fixture->task($aid,$h->id,'Date-only reminder',array('due_mode'=>'date','due_date'=>$today,'due_at_utc'=>null));
    $dateSlot=TaskReminder::model()->find("task_id=:id AND reminder_kind='due_day'",array(':id'=>$dateOnly->id));
    $db->createCommand()->update('crm_task_reminder',array('notify_at_utc'=>gmdate('Y-m-d H:i:s')),'id=:id',array(':id'=>$dateSlot->id));
    $past=TaskService::create($aid,$h->id,array('title'=>'Confirmed overdue','kind'=>'task','due_mode'=>'date','due_date'=>'2020-01-01','due_at_utc'=>null,'starts_at_utc'=>null,'ends_at_utc'=>null,'timezone'=>'Asia/Ho_Chi_Minh'),bin2hex(random_bytes(16)),true,'Synthetic overdue still requires action');
    $types=$fixture->dispatch(); nc($types['created_count']===2,'Date-only and overdue slots delivered');
    $pastNotice=Notification::model()->find('task_id=:id',array(':id'=>$past['task_id'])); nc($pastNotice && $pastNotice->kind==='overdue' && Notification::model()->find('reminder_id=:id',array(':id'=>$dateSlot->id))->kind==='due_day','Correct notification kinds');
    $summary=ReminderService::summary(); nc($summary['overdue_task_count']===$beforeOverdue+1 && $summary['unread_count']===$beforeUnread+2,'Overdue task count independent of unread notifications');
    ReminderService::read($pastNotice->id); nc(ReminderService::summary()['overdue_task_count']===$beforeOverdue+1,'Marking read must not complete overdue task');
    ReminderTestUser::$current=1;
    $conflictTask=$fixture->task($aid,$h->id,'Malformed existing notification'); $conflictSlot=$fixture->due($conflictTask);
    $db->createCommand()->insert('crm_notification',array('reminder_id'=>$conflictSlot->id,'recipient_user_id'=>$actors['a'],'task_id'=>$conflictTask->id,'schedule_version'=>1,'kind'=>'due_day','created_at_utc'=>gmdate('Y-m-d H:i:s')));
    $snap=queueHash(); $prediction=$fixture->dispatch(200,true); nc($prediction['would_cancel']===1 && isset($prediction['reasons']['NOTIFICATION_CONFLICT']) && queueHash()===$snap,'Dry-run predicts invalid existing notification without writes');
    $out=$fixture->dispatch(); nc($out['cancelled_count']===1 && TaskReminder::model()->findByPk($conflictSlot->id)->last_error_code==='NOTIFICATION_CONFLICT','Conflicting notification never marked delivered');
    $tooEarly=TaskReminder::model()->find("task_id=:id AND reminder_kind='overdue'",array(':id'=>$conflictTask->id)); $db->createCommand()->update('crm_task_reminder',array('notify_at_utc'=>gmdate('Y-m-d H:i:s')),'id=:id',array(':id'=>$tooEarly->id));
    $out=$fixture->dispatch(); nc($out['cancelled_count']===1 && TaskReminder::model()->findByPk($tooEarly->id)->last_error_code==='INVALID_OVERDUE_SLOT','No early overdue notification');
    $completedRuns=array_map('intval',$fixture->runs); $db->createCommand()->update('crm_reminder_dispatch_run',array('finished_at_utc'=>gmdate('Y-m-d H:i:s',time()-600)),"id IN (".implode(',',$completedRuns).") AND status='completed'");
    nc(in_array('STALE_HEARTBEAT',ReminderService::monitor()['alerts'],true),'Stale success heartbeat warning');
    $superAccount=$fixture->account($h2->id,$actors['super'],'Explicit All owner'); $superTask=$fixture->task($superAccount,$h2->id,'All owner without Hotel grant');
    $db->createCommand()->update('users',array('status'=>'I'),'id=1'); $systemRun=$fixture->dispatch(); nc($systemRun['created_count']===1,'System worker must not impersonate inactive user 1'); $db->createCommand()->update('users',array('status'=>'A'),'id=1');
    ReminderTestUser::$current=$actors['super']; nc(ReminderService::summary()['unread_count']===1 && isset(ReminderService::summary()['monitor']),'Explicit All recipient and monitor work without Hotel grant');
    ReminderTestUser::$current=1;
    $adminRights=$db->createCommand("SELECT access_rights FROM roles WHERE role_name='admin'")->queryScalar(); $rights=CJSON::decode($adminRights); $rights['reminder'][]='monitor'; $db->createCommand()->update('roles',array('access_rights'=>CJSON::encode($rights)),"role_name='admin'");
    ReminderTestUser::$current=$actors['manager']; ns(403,function(){ReminderService::monitor();},'Monitor action alone must not grant All scope'); ReminderTestUser::$current=1; $db->createCommand()->update('roles',array('access_rights'=>$adminRights),"role_name='admin'");
    $lateTask=$fixture->task($aid,$h->id,'Late queue'); $late=$fixture->due($lateTask); $db->createCommand()->update('crm_task_reminder',array('notify_at_utc'=>gmdate('Y-m-d H:i:s',time()-601)),'id=:id',array(':id'=>$late->id));
    $runKey=bin2hex(random_bytes(16)); $db->createCommand()->insert('crm_reminder_dispatch_run',array('run_key'=>$runKey,'started_at_utc'=>gmdate('Y-m-d H:i:s',time()-600),'heartbeat_at_utc'=>gmdate('Y-m-d H:i:s',time()-600),'status'=>'running')); $fixture->runs[]=$db->lastInsertID;
    $health=ReminderService::monitor(); nc(in_array('BACKLOG',$health['alerts'],true) && in_array('STUCK_RUN',$health['alerts'],true),'Backlog and hung worker alert'); nc(strpos(CJSON::encode($health),$fixture->prefix)===false,'Monitor exposes customer title');
    nc(strpos(nh('monitor'),'Giám sát dispatch')!==false,'Monitor page render');
    $html=nh('admin'); nc(strpos($html,'mvp-reminder-count')!==false && strpos($html,'mvp-reminders.js')!==false && strpos($html,'mvp-dispatch-banner')!==false,'Bell/poller/monitor banner render');
    $roleController=new ReminderControllerTest('reminder'); Yii::app()->controller=$roleController; $roleController->init(); $roleHtml=$roleController->renderPartial('/role/_form',array('model'=>Role::model()->findByPk('users')),true); nc(strpos($roleHtml,'reminder_summary')!==false && strpos($roleHtml,'reminder_read')!==false && strpos($roleHtml,'reminder_monitor')!==false,'Reminder actions visible in role configuration');
    $_GET=array(); $_POST=array(); Yii::app()->request->method='GET'; $legacyController=new AjaxController('ajax'); Yii::app()->controller=$legacyController; $legacyController->init(); ns(410,function()use($legacyController){$legacyController->run('reminderdaily');},'Legacy reminder URL disabled');
    require_once __DIR__.'/../migrations/mvp/m261119_130000_prepare_reminder_dispatch.php'; try { (new m261119_130000_prepare_reminder_dispatch)->down(); throw new Exception('Dispatch history rollback accepted'); } catch (CDbException $e) { nc(strpos($e->getMessage(),'history')!==false,'Migration preserves dispatch history'); }
} finally { ReminderTestUser::$current=1; $fixture->cleanup(); foreach (array('roles','users') as $table) { $db->createCommand('DROP TEMPORARY TABLE IF EXISTS '.$table)->execute(); } }
foreach ($sources as $table=>$rows) { nc($rows===$db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll(),'Legacy source changed '.$table); } foreach ($baseline as $table=>$rows) { nc($rows===$db->createCommand('SELECT * FROM '.$table)->queryAll(),'Real fixture user/roles changed '.$table); }
ob_end_clean(); echo "PASS: $checks reminder assertions; dry-run, dispatch eligibility, exactly-once/recovery, recipient-only read, current scopes/version, inbox/JSON/CSRF/XSS, health and legacy URL.\n";
