<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ob_start(); define('IS_IN_TESTS',true); define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
class TaskTestUser extends CWebUser { public static $current=1; public function init() {} public function getId() { return self::$current; } public function getIsGuest() { return self::$current===null; } }
class TaskTestRequest extends CHttpRequest { public $method='GET'; public function getRequestType() { return $this->method; } public function getIsPostRequest() { return $this->method==='POST'; } }
class TaskRedirect extends Exception {}
$root=dirname(__DIR__,2); $_SERVER['SCRIPT_FILENAME']=$root.'/index.php'; $_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['HTTP_HOST']='crm.test.local'; $_SERVER['REQUEST_URI']='/'; $_SERVER['REQUEST_METHOD']='GET';
$config=require __DIR__.'/../config/test.php'; $config['components']['user']=array('class'=>'TaskTestUser'); $config['components']['request']=array('class'=>'TaskTestRequest','enableCsrfValidation'=>true);
$app=Yii::createWebApplication($config); $db=$app->db; $db->setActive(true); if ($db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Synthetic test fixture required.'); }
Yii::import('application.controllers.*');
class TaskControllerTest extends TaskController { public function redirect($url,$terminate=true,$statusCode=302) { throw new TaskRedirect; } }
require_once __DIR__.'/support/TemporaryTableFixture.php';
$checks=0;
function tcheck($value,$message) { global $checks; if (!$value) { throw new Exception($message); } $checks++; }
function tstatus($expected,$fn,$message) { try { $fn(); } catch (CHttpException $e) { tcheck($e->statusCode===$expected,$message.' received '.$e->statusCode.' '.$e->getMessage()); return; } throw new Exception($message.' not rejected'); }
function tk() { return bin2hex(random_bytes(16)); }
function tr($class,$values) { $m=new $class; $m->setAttributes($values,false); tcheck($m->save(),$class.': '.CJSON::encode($m->errors)); return $m; }
function httpTask($action,$get=array(),$post=array(),$method='GET',$csrf=true) {
    $_GET=$get; $_POST=$post; Yii::app()->request->method=$method; http_response_code(200);
    if ($method==='POST' && $csrf) { $_POST[Yii::app()->request->csrfTokenName]=Yii::app()->request->csrfToken; }
    $c=new TaskControllerTest('task'); Yii::app()->controller=$c; $c->init(); ob_start();
    try { $c->run($action); return ob_get_contents(); } catch (TaskRedirect $e) { return 'redirect'; } finally { ob_end_clean(); }
}
function schedule5($date='2030-01-08') { return array('kind'=>'task','due_mode'=>'date','due_date'=>$date,'due_at_utc'=>null,'starts_at_utc'=>null,'ends_at_utc'=>null,'timezone'=>'Asia/Ho_Chi_Minh'); }
function makeTask5($account,$hotel,$extra=array(),$key=null) { return TaskService::create($account,$hotel,array_merge(schedule5(),array('title'=>'Synthetic task'),$extra),$key ?: tk()); }
$baseline=array(); foreach (array('users','roles') as $table) { $baseline[$table]=$db->createCommand('SELECT * FROM '.$table)->queryAll(); $ddl=$db->createCommand('SHOW CREATE TABLE '.$table)->queryRow(); $db->createCommand(isolatedTemporaryTableDdl($ddl['Create Table']))->execute(); foreach ($baseline[$table] as $row) { $db->createCommand()->insert($table,$row); } }
$legacy=array(); foreach (array('contact','reports') as $table) { $legacy[$table]=$db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll(); }
$tx=$db->beginTransaction();
try {
    $actors=array(); foreach (array('a'=>'users','b'=>'users','other'=>'users','manager'=>'admin','reader'=>'reservation') as $label=>$role) { $u=new Users('create'); $u->attributes=array('username'=>'wp05_'.$label,'fullname'=>'WP05 '.$label,'password'=>'fake','roles'=>$role,'status'=>'A','last_login_date'=>'2026-11-19 00:00:00'); tcheck($u->save(),'User fixture'); $actors[$label]=(int)$u->id; }
    $h1=tr('Hotel',array('code'=>'WP05-H1','name'=>'WP05 Hotel One')); $h2=tr('Hotel',array('code'=>'WP05-H2','name'=>'WP05 Hidden Hotel'));
    foreach (array(array('a',$h1,'own'),array('b',$h1,'own'),array('other',$h2,'own'),array('manager',$h1,'hotel'),array('reader',$h1,'hotel')) as $g) { tr('UserHotelAccess',array('user_id'=>$actors[$g[0]],'hotel_id'=>$g[1]->id,'scope_level'=>$g[2])); }
    $account=AccountService::create(array('name'=>'WP05 Task Account'),$h1->id,$actors['a'],array('first_name'=>'Task contact'),array(),tk()); $aid=$account['account_id']; $pid=$account['contact_id'];
    $hidden=AccountService::create(array('name'=>'WP05 Hidden Account'),$h2->id,$actors['other'],array('first_name'=>'Hidden contact'),array(),tk());
    $unassigned=AccountService::create(array('name'=>'WP05 Unassigned'),$h1->id,null,null,array(),tk());
    tcheck(ActionAccess::can('task','start',$actors['a']) && ActionAccess::can('task','reschedule',$actors['a']),'WP05 action seed');
    TaskTestUser::$current=$actors['a'];
    tstatus(404,function()use($hidden,$h2){makeTask5($hidden['account_id'],$h2->id);},'Create hidden Hotel');
    tstatus(400,function()use($aid,$h1){makeTask5($aid,$h1->id,array('created_by'=>1));},'Audit injection');
    tstatus(409,function()use($aid,$h1,$actors){TaskService::create($aid,$h1->id,array_merge(schedule5(),array('title'=>'wrong receiver')),tk(),false,'',$actors['b']);},'Owner injection');
    $before=Task::model()->count(); $events=AuditEvent::model()->count();
    tstatus(422,function()use($aid,$h1){makeTask5($aid,$h1->id,array('title'=>''));},'Missing title');
    tcheck(Task::model()->count()===$before && AuditEvent::model()->count()===$events,'Invalid create left data');
    tstatus(422,function()use($aid,$h1,$hidden){makeTask5($aid,$h1->id,array('contact_person_id'=>$hidden['contact_id']));},'Cross-account contact');
    tstatus(422,function()use($aid,$h1){makeTask5($aid,$h1->id,array('timezone'=>'not-a-zone'));},'Invalid timezone');
    tstatus(422,function()use($aid,$h1){makeTask5($aid,$h1->id,array('due_date'=>'2030-02-31'));},'Invalid date');
    tstatus(422,function()use($aid,$h1){makeTask5($aid,$h1->id,array('due_at_utc'=>'2030-01-08 00:00:00'));},'Mixed date/timed deadline');
    $key=tk(); $r=makeTask5($aid,$h1->id,array('contact_person_id'=>$pid),$key); $id=$r['task_id']; $task=Task::model()->findByPk($id);
    tcheck($r['status']==='open' && (int)$task->assignee_user_id===$actors['a'],'Create owner/status');
    tcheck($task->deadline_at_utc==='2030-01-08 17:00:00','Date deadline next local midnight');
    tcheck((int)TaskReminder::model()->count('task_id=:id',array(':id'=>$id))===2,'Create reminder plan');
    tcheck(makeTask5($aid,$h1->id,array('contact_person_id'=>$pid),$key)['replayed'],'Double click create');
    $caseKey='CaseSensitiveTaskKey'.tk(); $caseOne=makeTask5($aid,$h1->id,array('title'=>'Case request one'),$caseKey); $caseTwo=makeTask5($aid,$h1->id,array('title'=>'Case request two'),strtolower($caseKey)); tcheck((int)$caseOne['task_id']!==(int)$caseTwo['task_id'],'Request key case agrees between journal and Task index');
    tstatus(409,function()use($aid,$h1,$key){makeTask5($aid,$h1->id,array('title'=>'different'),$key);},'Same key different payload');
    TaskTestUser::$current=$actors['b']; tstatus(404,function()use($id){TaskReadService::detail($id);},'Other Own reads task');
    tcheck(TaskReadService::listing(array(),'all',1)['total']===0,'Own list scope');
    TaskTestUser::$current=$actors['a'];
    $updated=TaskService::update($id,array('title'=>'Updated content','priority'=>'high'),1,tk()); tcheck($updated['version']===2 && $updated['schedule_version']===1,'Content update versions');
    tstatus(409,function()use($id){TaskService::update($id,array('title'=>'stale'),1,tk());},'Stale update');
    tstatus(400,function()use($id){TaskService::update($id,array('due_date'=>'2030-01-09'),2,tk());},'Change deadline through content editor');
    $started=TaskService::start($id,2,tk()); tcheck($started['status']==='in_progress' && $started['schedule_version']===1,'Start task');
    tstatus(409,function()use($id,$started){TaskService::start($id,$started['version'],tk());},'Start twice with new request');
    tstatus(422,function()use($id,$started){TaskService::complete($id,$started['version'],'',tk());},'Complete without outcome');
    $delivered=tr('TaskReminder',array('task_id'=>$id,'recipient_user_id'=>$actors['a'],'schedule_version'=>1,'reminder_kind'=>'before','notify_at_utc'=>'2030-01-01 00:00:00','state'=>'delivered','delivered_at_utc'=>gmdate('Y-m-d H:i:s')));
    tr('Notification',array('reminder_id'=>$delivered->id,'task_id'=>$id,'recipient_user_id'=>$actors['a'],'schedule_version'=>1,'kind'=>'before'));
    tcheck((int)Notification::model()->count(CustomerPolicy::notificationCriteria())===1,'Notification fixture');
    $newSchedule=schedule5('2030-01-10');
    tstatus(422,function()use($id,$newSchedule,$started){TaskService::reschedule($id,$newSchedule,$started['version'],'',tk());},'Reschedule reason');
    $rescheduled=TaskService::reschedule($id,$newSchedule,$started['version'],'Customer moved meeting',tk());
    tcheck($rescheduled['schedule_version']===2 && $rescheduled['status']==='in_progress','Reschedule versions/status');
    tcheck((int)TaskReminder::model()->count("task_id=:id AND state='pending' AND schedule_version=1",array(':id'=>$id))===0,'Old schedule not cancelled');
    tcheck(TaskReminder::model()->findByPk($delivered->id)->state==='delivered','Delivered history changed');
    tcheck((int)Notification::model()->count(CustomerPolicy::notificationCriteria())===0,'Old generation notification visible');
    tstatus(409,function()use($id,$started){TaskService::complete($id,$started['version'],'Old window result',tk());},'Complete races reschedule');
    $completeKey=tk(); $completed=TaskService::complete($id,$rescheduled['version'],'Quoted successfully',$completeKey);
    tcheck($completed['status']==='completed' && $completed['schedule_version']===3,'Complete state/generation');
    $closed=Task::model()->findByPk($id); tcheck((int)$closed->completed_by===$actors['a'] && $closed->completed_at_utc && $closed->completion_note==='Quoted successfully','Completion actor/outcome');
    tcheck((int)TaskReminder::model()->count("task_id=:id AND state='pending'",array(':id'=>$id))===0,'Complete schedules active');
    tcheck(TaskService::complete($id,$rescheduled['version'],'Quoted successfully',$completeKey)['replayed'],'Complete replay');
    tstatus(409,function()use($id,$completed){TaskService::reschedule($id,schedule5(),$completed['version'],'Late window',tk());},'Closed task reschedule');
    tstatus(409,function()use($id,$completed){TaskService::update($id,array('title'=>'closed edit'),$completed['version'],tk());},'Closed task edit');
    tstatus(403,function()use($id,$completed){TaskService::reopen($id,schedule5(),$completed['version'],'Own reopen',tk());},'Own reopen');
    TaskTestUser::$current=$actors['manager'];
    $plan=AssignmentService::preview($aid,$h1->id); AssignmentService::handover($aid,$h1->id,$actors['b'],$plan['expected_version'],$plan['plan_hash'],'New responsible sales',tk());
    tcheck((int)Task::model()->findByPk($id)->assignee_user_id===$actors['a'],'Closed handover kept historical owner');
    $reopened=TaskService::reopen($id,schedule5('2030-01-11'),$completed['version'],'Follow up again',tk());
    $fresh=Task::model()->findByPk($id); tcheck($reopened['status']==='open' && (int)$fresh->assignee_user_id===$actors['b'] && $fresh->completed_by===null && $fresh->completion_note===null,'Reopen new owner/clear metadata');
    tcheck($reopened['schedule_version']===4 && (int)$fresh->created_by===$actors['a'],'Reopen generation/history');
    $audit=AuditEvent::model()->find("entity_type='task' AND entity_id=:id AND event_type='reopen'",array(':id'=>(string)$id)); tcheck(CJSON::decode($audit->before_json)['completion_note']==='Quoted successfully','Reopen lost old outcome audit');
    TaskTestUser::$current=$actors['a']; tstatus(404,function()use($id,$rescheduled,$completeKey){TaskService::complete($id,$rescheduled['version'],'Quoted successfully',$completeKey);},'Former owner replay after handover');
    TaskTestUser::$current=$actors['b']; $cancelled=TaskService::cancel($id,$reopened['version'],'Customer declined',tk()); tcheck($cancelled['status']==='cancelled','Cancel state');
    tcheck(Task::model()->findByPk($id)->completed_by===null && Task::model()->findByPk($id)->cancel_reason==='Customer declined','Cancel not complete');
    // Expired inputs need explicit confirmation and reason; no replay of past before/day slots.
    tstatus(422,function()use($aid,$h1){makeTask5($aid,$h1->id,array('due_date'=>'2020-01-01'));},'Overdue create without confirmation');
    tstatus(422,function()use($aid,$h1){TaskService::create($aid,$h1->id,array_merge(schedule5('2020-01-01'),array('title'=>'Past')),tk(),true,'');},'Overdue reason');
    $overdue=TaskService::create($aid,$h1->id,array_merge(schedule5('2020-01-01'),array('title'=>'Past confirmed')),tk(),true,'Historical follow-up still needed');
    tcheck((int)TaskReminder::model()->count('task_id=:id',array(':id'=>$overdue['task_id']))===1,'Overdue only one schedule');
    tcheck(TaskReminder::model()->find('task_id=:id',array(':id'=>$overdue['task_id']))->reminder_kind==='overdue','Overdue schedule kind');
    tcheck(TaskReadService::listing(array(),'overdue',1)['total']===1,'Overdue tab');
    $local=array('kind'=>'appointment','due_mode'=>'datetime','due_date'=>null,'due_at_local'=>'2030-01-08T11:00','starts_at_local'=>'2030-01-08T10:00','ends_at_local'=>'2030-01-08T11:00','timezone'=>'Asia/Ho_Chi_Minh','title'=>'Appointment <script>bad()</script>');
    $appointment=TaskService::create($aid,$h1->id,$local,tk()); $appt=Task::model()->findByPk($appointment['task_id']);
    tcheck($appt->starts_at_utc==='2030-01-08 03:00:00' && $appt->deadline_at_utc==='2030-01-08 04:00:00','Local to UTC appointment');
    tcheck(TaskReminder::model()->find("task_id=:id AND reminder_kind='before'",array(':id'=>$appt->id))->notify_at_utc==='2030-01-08 02:30:00','Appointment before start');
    tstatus(422,function()use($aid,$h1,$local){$local['ends_at_local']='2030-01-08T09:00'; TaskService::create($aid,$h1->id,$local,tk());},'End before start');
    tstatus(422,function()use($aid,$h1,$local){$local['due_at_local']='2030-01-08T12:00'; TaskService::create($aid,$h1->id,$local,tk());},'Appointment deadline not end');
    tstatus(422,function(){TaskSchedule::localToUtc('2030-03-10T02:30','America/New_York','starts_at_local');},'DST gap');
    tstatus(422,function(){TaskSchedule::localToUtc('2030-11-03T01:30','America/New_York','starts_at_local');},'DST repeated time');
    tcheck(TaskSchedule::localToUtc('2030-03-10T03:30','America/New_York','x')==='2030-03-10 07:30:00','DST valid local time');
    tstatus(400,function(){TaskSchedule::input(array('due_at_local'=>'2030-01-01T10:00','due_at_utc'=>'2030-01-01 03:00:00'));},'Mixed UTC/local');
    // Weekly overlap, exact boundaries and separate date-only items.
    $weekFrom='2030-01-07'; $weekTo='2030-01-14';
    $cross=array_merge($local,array('title'=>'Cross week','starts_at_local'=>'2030-01-06T23:00','ends_at_local'=>'2030-01-07T01:00','due_at_local'=>'2030-01-07T01:00'));
    $crossTask=TaskService::create($aid,$h1->id,$cross,tk());
    $beforeWeek=array_merge($cross,array('title'=>'Ends exactly at start','ends_at_local'=>'2030-01-07T00:00','due_at_local'=>'2030-01-07T00:00')); TaskService::create($aid,$h1->id,$beforeWeek,tk());
    $afterWeek=array_merge($local,array('title'=>'Starts exactly at end','starts_at_local'=>'2030-01-14T00:00','ends_at_local'=>'2030-01-14T01:00','due_at_local'=>'2030-01-14T01:00')); TaskService::create($aid,$h1->id,$afterWeek,tk());
    $dateTask=makeTask5($aid,$h1->id,array('title'=>'Date-only separate'));
    TaskTestUser::$current=1; $hiddenAppointment=TaskService::create($hidden['account_id'],$h2->id,array_merge($local,array('title'=>'HIDDEN CALENDAR TITLE')),tk());
    TaskTestUser::$current=$actors['b']; $cal=TaskReadService::calendar($weekFrom,$weekTo,'Asia/Ho_Chi_Minh');
    $ids=array_column($cal['events'],'id'); tcheck(count($ids)===2 && in_array((int)$crossTask['task_id'],$ids),'Weekly interval overlap/exclusive bounds');
    tcheck(!in_array((int)$hiddenAppointment['task_id'],$ids),'Weekly Hotel isolation');
    tcheck(in_array((int)$dateTask['task_id'],array_column($cal['date_tasks'],'id')) && !in_array((int)$dateTask['task_id'],$ids),'Date-only not fake appointment');
    tstatus(400,function(){TaskReadService::calendar('2030-01-01','2030-02-01','Asia/Ho_Chi_Minh');},'Unbounded calendar range');
    tstatus(400,function(){TaskReadService::calendar('2030-02-31','2030-03-01','UTC');},'Invalid calendar date');
    $json=CJSON::decode(httpTask('calendar',array('from'=>$weekFrom,'to'=>$weekTo,'format'=>'json'))); tcheck(count($json['events'])===2 && isset($json['date_tasks']),'Calendar JSON');
    $html=httpTask('calendar',array('from'=>$weekFrom)); tcheck(strpos($html,'Date-only separate')!==false && strpos($html,'&lt;script&gt;')!==false && strpos($html,'HIDDEN CALENDAR TITLE')===false,'Calendar HTML/XSS');
    tcheck(strpos(httpTask('admin',array('tab'=>'all')),'Công việc')!==false,'List page');
    tcheck(strpos(httpTask('view',array('id'=>$appt->id)),'Lịch sử công việc')!==false,'Detail page');
    tcheck(strpos(httpTask('create',array('account_id'=>$aid,'hotel_id'=>$h1->id)),'name="request_key"')!==false,'Create form');
    tcheck(strpos(httpTask('update',array('id'=>$appt->id)),'Task[title]')!==false,'Content form');
    tcheck(strpos(httpTask('reschedule',array('id'=>$appt->id)),'Task[starts_at_local]')!==false,'Schedule form');
    $post=array('account_id'=>$aid,'hotel_id'=>$h1->id,'request_key'=>tk(),'Task'=>array_merge(schedule5(),array('title'=>'HTTP create')));
    $createdHttp=CJSON::decode(httpTask('create',array('format'=>'json'),$post,'POST')); tcheck(isset($createdHttp['task_id']),'Create JSON endpoint');
    $bad=$post; $bad['request_key']=tk(); $bad['Task']['title']=''; $error=CJSON::decode(httpTask('create',array('format'=>'json'),$bad,'POST'));
    tcheck(http_response_code()===422 && $error['error_code']==='VALIDATION_ERROR' && isset($error['field_errors']['title']),'JSON validation contract');
    $bad['Task']['title']=''; $badHtml=httpTask('create',array(),$bad,'POST'); tcheck(strpos($badHtml,$bad['request_key'])!==false && strpos($badHtml,'Chưa lưu được')!==false,'HTML error preserves request');
    $error=CJSON::decode(httpTask('complete',array('id'=>$appt->id,'format'=>'json'),array(),'POST',false)); tcheck(http_response_code()===400 && $error['error_code']==='INVALID_REQUEST','CSRF JSON');
    $error=CJSON::decode(httpTask('cancel',array('id'=>$appt->id,'format'=>'json'))); tcheck(http_response_code()===405 && $error['error_code']==='METHOD_NOT_ALLOWED','GET cancel');
    $mut=array('request_key'=>tk(),'expected_version'=>$createdHttp['version'],'note'=>'HTTP outcome');
    $httpDone=CJSON::decode(httpTask('complete',array('id'=>$createdHttp['task_id'],'format'=>'json'),$mut,'POST')); tcheck($httpDone['status']==='completed','Complete HTTP');
    $stale=CJSON::decode(httpTask('reschedule',array('id'=>$createdHttp['task_id'],'format'=>'json'),array('request_key'=>tk(),'expected_version'=>1,'reason'=>'old window','Task'=>schedule5()),'POST')); tcheck(http_response_code()===409 && $stale['error_code']==='CONFLICT','Closed stale schedule HTTP');
    $apiUpdate=CJSON::decode(httpTask('update',array('id'=>$appt->id,'format'=>'json'),array('request_key'=>tk(),'expected_version'=>1,'Task'=>array('title'=>'HTTP edited appointment')),'POST'));
    tcheck($apiUpdate['version']===2 && $apiUpdate['schedule_version']===1,'Update HTTP endpoint');
    $apiSchedule=array('kind'=>'appointment','due_mode'=>'datetime','due_date'=>null,'due_at_local'=>'2030-01-09T11:00','starts_at_local'=>'2030-01-09T10:00','ends_at_local'=>'2030-01-09T11:00','timezone'=>'Asia/Ho_Chi_Minh');
    $apiChanged=CJSON::decode(httpTask('reschedule',array('id'=>$appt->id,'format'=>'json'),array('request_key'=>tk(),'expected_version'=>2,'reason'=>'HTTP changed schedule','Task'=>$apiSchedule),'POST'));
    tcheck($apiChanged['version']===3 && $apiChanged['schedule_version']===2,'Reschedule HTTP endpoint');
    $conflictKey=tk(); $pendingSchedule=$apiSchedule; $pendingSchedule['due_at_local']='2030-01-10T11:00'; $pendingSchedule['starts_at_local']='2030-01-10T10:00'; $pendingSchedule['ends_at_local']='2030-01-10T11:00';
    $conflictHtml=httpTask('reschedule',array('id'=>$appt->id),array('request_key'=>$conflictKey,'expected_version'=>2,'reason'=>'Stale browser input','Task'=>$pendingSchedule),'POST');
    tcheck(http_response_code()===409 && strpos($conflictHtml,'Mở phiên bản mới để đối chiếu')!==false && strpos($conflictHtml,'Stack Trace')===false,'HTML stale form is recoverable without debug output');
    tcheck(strpos($conflictHtml,$conflictKey)!==false && strpos($conflictHtml,'2030-01-10T11:00')!==false && strpos($conflictHtml,'disabled="disabled"')!==false,'Stale browser values/key retained with save disabled');
    tcheck((int)Task::model()->findByPk($appt->id)->lock_version===3 && Task::model()->findByPk($appt->id)->due_at_utc==='2030-01-09 04:00:00','HTML conflict never overwrites current schedule');
    $injection=CJSON::decode(httpTask('update',array('id'=>$appt->id,'format'=>'json'),array('request_key'=>tk(),'expected_version'=>3,'Task'=>array('status'=>'completed')),'POST'));
    tcheck(http_response_code()===400 && $injection['error_code']==='INVALID_REQUEST','Status injection through content endpoint');
    TaskTestUser::$current=$actors['manager'];
    $apiReopened=CJSON::decode(httpTask('reopen',array('id'=>$createdHttp['task_id'],'format'=>'json'),array('request_key'=>tk(),'expected_version'=>$httpDone['version'],'reason'=>'HTTP reopen','Task'=>schedule5()),'POST'));
    tcheck($apiReopened['status']==='open','Reopen HTTP endpoint');
    TaskTestUser::$current=$actors['b'];
    $apiStarted=CJSON::decode(httpTask('start',array('id'=>$createdHttp['task_id'],'format'=>'json'),array('request_key'=>tk(),'expected_version'=>$apiReopened['version']),'POST'));
    tcheck($apiStarted['status']==='in_progress','Start HTTP endpoint');
    $apiCancelled=CJSON::decode(httpTask('cancel',array('id'=>$createdHttp['task_id'],'format'=>'json'),array('request_key'=>tk(),'expected_version'=>$apiStarted['version'],'note'=>'HTTP cancellation'),'POST'));
    tcheck($apiCancelled['status']==='cancelled','Cancel HTTP endpoint');
    // A linked old Report is a source reference, never a gate on Task age or a target for schedule edits.
    TaskTestUser::$current=1; $reportIds=array();
    foreach (array(array($aid,$h1->id),array($hidden['account_id'],$h2->id)) as $context) {
        $raw=$legacy['reports'][0]; unset($raw['id']); $raw['company_name']='WP05 source'; $raw['added_date']='2000-01-01 00:00:00'; $raw['date']='2000-01-01'; $raw['report']='Preserve old narrative'; $raw['account_id']=$context[0]; $raw['hotel_id']=$context[1]; $raw['contact_person_id']=null; $raw['identity_state']='linked'; $raw['client_request_key']=tk();
        $db->createCommand()->insert('reports',$raw); $reportIds[]=$db->lastInsertID;
    }
    $reportHash=LegacyImportSource::hash($db->createCommand('SELECT * FROM reports WHERE id=:id')->queryRow(true,array(':id'=>$reportIds[0])),'report');
    TaskTestUser::$current=$actors['b'];
    tstatus(404,function()use($aid,$h1,$reportIds){TaskService::create($aid,$h1->id,array_merge(schedule5(),array('title'=>'wrong report')),tk(),false,'',null,$reportIds[1]);},'Cross-Hotel report context');
    $reportTask=TaskService::create($aid,$h1->id,array_merge(schedule5(),array('title'=>'Follow old Report','contact_person_id'=>$pid)),tk(),false,'',null,$reportIds[0]);
    TaskService::complete($reportTask['task_id'],1,'Independent task completed',tk());
    tcheck(LegacyImportSource::hash($db->createCommand('SELECT * FROM reports WHERE id=:id')->queryRow(true,array(':id'=>$reportIds[0])),'report')===$reportHash,'Task write changed Report narrative/history');
    $db->createCommand()->update('crm_contact_person',array('status'=>'archived'),'id=:id',array(':id'=>$pid));
    tstatus(422,function()use($aid,$h1,$pid){makeTask5($aid,$h1->id,array('contact_person_id'=>$pid));},'Archived contact task');
    $db->createCommand()->update('crm_contact_person',array('status'=>'active'),'id=:id',array(':id'=>$pid));
    $db->createCommand()->update('users',array('status'=>'I'),'id=:id',array(':id'=>$actors['b']));
    TaskTestUser::$current=$actors['manager'];
    tstatus(422,function()use($aid,$h1){makeTask5($aid,$h1->id);},'Inactive current owner');
    $db->createCommand()->update('users',array('status'=>'A'),'id=:id',array(':id'=>$actors['b']));
    TaskTestUser::$current=$actors['manager']; tcheck(strpos(httpTask('reopen',array('id'=>$id)),'Mở lại công việc')!==false,'Reopen form');
    tstatus(409,function()use($unassigned,$h1){makeTask5($unassigned['account_id'],$h1->id);},'Unassigned profile task');
    TaskTestUser::$current=$actors['reader']; $denied=CJSON::decode(httpTask('calendar',array('format'=>'json'))); tcheck(http_response_code()===403 && $denied['error_code']==='FORBIDDEN','Action permission calendar');
    TaskTestUser::$current=1;
    $flags=$app->params['mvp']; $off=$flags; $off['taskWrite']=false; $app->params['mvp']=$off; tstatus(503,function()use($aid,$h1){makeTask5($aid,$h1->id);},'Task feature gate'); $app->params['mvp']=$flags;
    $peer=Yii::createComponent(require __DIR__.'/../config/db-test.php'); $peer->setActive(true); $lock=CustomerImportService::lockName(); $peer->createCommand('SELECT GET_LOCK(:key,0)')->queryScalar(array(':key'=>$lock));
    try { tstatus(409,function()use($aid,$h1){makeTask5($aid,$h1->id);},'Handover/import mutex'); } finally { $peer->createCommand('SELECT RELEASE_LOCK(:key)')->queryScalar(array(':key'=>$lock)); $peer->setActive(false); }
    require_once __DIR__.'/../migrations/mvp/m261119_090000_prepare_task_workflows.php'; try { (new m261119_090000_prepare_task_workflows)->down(); throw new Exception('Populated task migration rollback accepted'); } catch (CDbException $e) { tcheck(strpos($e->getMessage(),'requests')!==false,'Task rollback guard'); }
} finally { if ($tx->active) { $tx->rollback(); } foreach (array('roles','users') as $table) { $db->createCommand('DROP TEMPORARY TABLE IF EXISTS '.$table)->execute(); } }
foreach ($legacy as $table=>$rows) { tcheck($rows===$db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll(),'Legacy changed '.$table); }
foreach ($baseline as $table=>$rows) { tcheck($rows===$db->createCommand('SELECT * FROM '.$table)->queryAll(),'User/role changed '.$table); }
ob_end_clean(); echo "PASS: $checks task assertions; scoped CRUD, transitions, schedule/version/replay, current-owner reopen, reminder invalidation, timezone/DST, weekly boundaries, HTML/JSON/CSRF and rollback.\n";
