<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ob_start(); define('IS_IN_TESTS',true); define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
class CustomerTestUser extends CWebUser {
    public static $current=1;
    public function init() {} public function getId() { return self::$current; } public function getIsGuest() { return self::$current===null; }
}
class CustomerTestRequest extends CHttpRequest {
    public $method='GET'; public function getRequestType() { return $this->method; } public function getIsPostRequest() { return $this->method==='POST'; }
}
class CustomerRedirect extends Exception {}
$root=dirname(__DIR__,2); $_SERVER['SCRIPT_FILENAME']=$root.'/index.php'; $_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['HTTP_HOST']='crm.test.local'; $_SERVER['REQUEST_URI']='/'; $_SERVER['REQUEST_METHOD']='GET';
$config=require __DIR__.'/../config/test.php'; $config['components']['user']=array('class'=>'CustomerTestUser'); $config['components']['request']=array('class'=>'CustomerTestRequest','enableCsrfValidation'=>true);
$app=Yii::createWebApplication($config); $db=$app->db; $db->setActive(true);
if ($db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Synthetic isolated fixture required.'); }
Yii::import('application.controllers.*');
class CustomerControllerTest extends AccountController { public function redirect($url,$terminate=true,$statusCode=302) { throw new CustomerRedirect; } }
class CustomerUsersTest extends UsersController { public function redirect($url,$terminate=true,$statusCode=302) { throw new CustomerRedirect; } }
require_once __DIR__.'/support/TemporaryTableFixture.php';
$checks=0;
function ck($value,$message) { global $checks; if (!$value) { throw new Exception($message); } $checks++; }
function status($expected,$fn,$message) { try { $fn(); } catch (CHttpException $e) { ck($e->statusCode===$expected,$message.' received '.$e->statusCode.': '.$e->getMessage()); return; } throw new Exception($message.' not rejected'); }
function key4() { return bin2hex(random_bytes(16)); }
function rec4($class,$values) { $m=new $class; $m->setAttributes($values,false); ck($m->save(),$class.': '.CJSON::encode($m->errors)); return $m; }
function run4($action,$get=array(),$post=array(),$method='GET',$csrf=true,$users=false) {
    $_GET=$get; $_POST=$post; Yii::app()->request->method=$method;
    if ($method==='POST' && $csrf) { $_POST[Yii::app()->request->csrfTokenName]=Yii::app()->request->csrfToken; }
    $c=$users?new CustomerUsersTest('users'):new CustomerControllerTest('account'); Yii::app()->controller=$c; $c->init(); ob_start();
    try { $c->run($action); return ob_get_contents(); } catch (CustomerRedirect $e) { return 'redirect'; } finally { ob_end_clean(); }
}
function task4($aid,$hid,$uid,$pid=null,$extra=array()) {
    return rec4('Task',array_merge(array('account_id'=>$aid,'hotel_id'=>$hid,'contact_person_id'=>$pid,'assignee_user_id'=>$uid,'code'=>'TEST-'.substr(key4(),0,20),'client_request_key'=>key4(),'title'=>'Synthetic task','kind'=>'task','due_mode'=>'datetime','due_at_utc'=>'2030-01-02 03:00:00','timezone'=>'Asia/Ho_Chi_Minh'),$extra));
}
$baseline=array(); foreach (array('users','roles') as $table) {
    $baseline[$table]=$db->createCommand('SELECT * FROM '.$table)->queryAll(); $ddl=$db->createCommand('SHOW CREATE TABLE '.$table)->queryRow(); $db->createCommand(isolatedTemporaryTableDdl($ddl['Create Table']))->execute();
    foreach ($baseline[$table] as $row) { $db->createCommand()->insert($table,$row); }
}
$legacyBefore=array(); foreach (array('contact','reports') as $table) { $legacyBefore[$table]=$db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll(); }
$tx=$db->beginTransaction();
try {
    $actors=array(); foreach (array('a'=>'users','b'=>'users','c'=>'users','manager'=>'admin','reader'=>'reservation') as $label=>$role) {
        $u=new Users('create'); $u->attributes=array('username'=>'wp04_'.$label,'fullname'=>'WP04 '.$label,'password'=>'synthetic','roles'=>$role,'status'=>'A','last_login_date'=>'2026-11-18 00:00:00'); ck($u->save(),'User fixture'); $actors[$label]=(int)$u->id;
    }
    $h1=rec4('Hotel',array('code'=>'WP04-H1','name'=>'WP04 Hotel One')); $h2=rec4('Hotel',array('code'=>'WP04-H2','name'=>'WP04 Hidden Hotel')); $h3=rec4('Hotel',array('code'=>'WP04-H3','name'=>'WP04 New Hotel'));
    foreach (array(array('a',$h1,'own'),array('b',$h1,'own'),array('c',$h2,'own'),array('manager',$h1,'hotel'),array('manager',$h3,'hotel'),array('reader',$h1,'hotel')) as $g) { rec4('UserHotelAccess',array('user_id'=>$actors[$g[0]],'hotel_id'=>$g[1]->id,'scope_level'=>$g[2])); }
    ck(ActionAccess::can('account','contactcreate',$actors['a']) && ActionAccess::can('account','assignpreview',$actors['manager']),'WP04 role seed missing');
    CustomerTestUser::$current=$actors['a'];
    ck(strpos(run4('create',array('hotel_id'=>$h1->id)),'Chưa phân công')===false,'Own create form must fix owner to self');
    status(403,function()use($h1,$actors){AccountService::create(array('name'=>'Forbidden other owner'),$h1->id,$actors['b'],null,array(),key4());},'Own creates for another sales');
    status(404,function()use($h2){AccountService::create(array('name'=>'Forbidden hotel'),$h2->id,null,null,array(),key4());},'Own outside hotel');
    status(400,function()use($h1){AccountService::create(array('name'=>'Injected','created_by'=>1),$h1->id,null,null,array(),key4());},'Protected field injection');
    $key=key4(); $args=array(array('name'=>' WP04 Unified Company ','segment'=>'CORPORATE','phone'=>'02811112222'),$h1->id,null,array('first_name'=>'Lan','email'=>'lan@example.test'),array('VN','EU','vn'),$key);
    $r=call_user_func_array(array('AccountService','create'),$args); $aid=$r['account_id']; $pid=$r['contact_id'];
    ck(call_user_func_array(array('AccountService','create'),$args)['replayed'],'Create replay');
    ck(AccountReadService::listing(array('q'=>'02811112222'),1)['total']===1,'Search company phone');
    $ah=AccountHotel::model()->findByPk(array('account_id'=>$aid,'hotel_id'=>$h1->id)); ck((int)$ah->owner_user_id===$actors['a'],'Own default owner');
    ck((int)Account::model()->findByPk($aid)->created_by===$actors['a'],'Actor attribution');
    ck(AccountReadService::detail($aid,'overview',1)['markets']===array('EU','VN'),'Markets normalized');
    status(409,function()use($h1){AccountService::create(array('name'=>'wp04 unified company'),$h1->id,null,null,array(),key4());},'Normalized duplicate');
    $changed=$args; $changed[0]['name']='Other'; status(409,function()use($changed){call_user_func_array(array('AccountService','create'),$changed);},'Request key payload mismatch');
    $beforeCount=Account::model()->count(); $beforeAudit=AuditEvent::model()->count();
    status(422,function()use($h1){AccountService::create(array('name'=>'WP04 rollback bad person'),$h1->id,null,array('email'=>'bad'),array(),key4());},'Invalid first contact');
    ck(Account::model()->count()===$beforeCount && AuditEvent::model()->count()===$beforeAudit,'Create partial rollback');
    $a=Account::model()->findByPk($aid); $updated=AccountService::update($aid,array('notes'=>'Updated by owner'),array('VN'),$a->lock_version,key4());
    status(409,function()use($aid,$a){AccountService::update($aid,array('notes'=>'stale'),array(),$a->lock_version,key4());},'Stale profile update');
    ck((int)Account::model()->findByPk($aid)->lock_version===(int)$a->lock_version+1,'Version increment');
    $p=AccountService::person($aid,null,array('first_name'=>'Minh','email'=>'lan@example.test'),null,key4());
    ck($p['contact_id']!==$pid,'Shared email allowed for different contacts');
    status(409,function()use($aid){AccountService::person($aid,null,array('first_name'=>' lan '),null,key4());},'Duplicate person name');
    $person=ContactPerson::model()->findByPk($pid); AccountService::person($aid,$pid,array('phone'=>'0900000000'),$person->lock_version,key4());
    status(409,function()use($aid,$pid,$person){AccountService::person($aid,$pid,array('phone'=>'stale'),$person->lock_version,key4());},'Stale contact update');
    $person=ContactPerson::model()->findByPk($pid);
    CustomerTestUser::$current=$actors['b']; status(404,function()use($aid){AccountReadService::detail($aid,'overview',1);},'Own other customer');
    ck(AccountReadService::listing(array('q'=>'WP04 Unified'),1)['total']===0,'List Own isolation');
    CustomerTestUser::$current=1;
    $plan=AssignmentService::preview($aid,$h2->id); AssignmentService::handover($aid,$h2->id,$actors['c'],$plan['expected_version'],$plan['plan_hash'],'Add second hotel',key4());
    $hidden=AccountService::create(array('name'=>'WP04 Hidden Company'),$h2->id,$actors['c'],null,array(),key4());
    $foreignPerson=AccountService::person($hidden['account_id'],null,array('first_name'=>'Hidden person'),null,key4());
    status(422,function()use($h1){AccountService::create(array('name'=>'wp04 unified company'),$h1->id,null,null,array(),key4(),true,'');},'All duplicate override requires reason');
    $duplicate=AccountService::create(array('name'=>'wp04 unified company'),$h1->id,null,null,array(),key4(),true,'Distinct legal company verified');
    ck((int)$duplicate['account_id']!==(int)$aid,'Explicit same-name distinction');
    $reportIds=array();
    foreach (array($h1,$h2) as $hotel) {
        $raw=$legacyBefore['reports'][0]; unset($raw['id']); $raw['company_name']='WP04 Unified Company'; $raw['report']='WP04 Report for '.$hotel->name;
        $raw['account_id']=$aid; $raw['contact_person_id']=$pid; $raw['hotel_id']=$hotel->id; $raw['identity_state']='linked'; $raw['client_request_key']=key4();
        $db->createCommand()->insert('reports',$raw); $reportIds[]=$db->lastInsertID;
    }
    CustomerTestUser::$current=$actors['a'];
    ck(AccountReadService::detail($aid,'reports',1)['total']===1,'Report tab must exclude hidden hotel');
    $reportHtml=run4('view',array('id'=>$aid,'tab'=>'reports'));
    ck(strpos($reportHtml,'WP04 Report for WP04 Hotel One')!==false && strpos($reportHtml,'WP04 Report for WP04 Hidden Hotel')===false,'Report content leaked across Hotels');
    status(404,function()use($aid,$foreignPerson){AccountService::person($aid,$foreignPerson['contact_id'],array('first_name'=>'Hacked'),1,key4());},'Cross-account contact mutation');
    status(403,function()use($aid){AccountReadService::detail($aid,'audit',1);},'Own reads audit across hotels');
    status(403,function()use($aid){AccountReadService::detail($aid,'sources',1);},'Own reads raw sources');
    ck(count(AccountReadService::detail($aid,'overview',1)['assignments'])===1,'Hidden hotel assignment leaked');
    ck(AccountReadService::listing(array('q'=>'WP04 Unified','hotel_id'=>(string)$h2->id),1)['total']===0,'Hidden hotel filter side channel');
    ck(AccountReadService::listing(array('q'=>'WP04 Unified','owner_user_id'=>(string)$actors['c']),1)['total']===0,'Hidden owner filter side channel');
    status(403,function()use($aid,$h1){AssignmentService::preview($aid,$h1->id);},'Own handover');
    CustomerTestUser::$current=$actors['manager'];
    status(403,function()use($aid){AccountService::archive($aid,null,Account::model()->findByPk($aid)->lock_version,'partial manager',key4());},'Partial hotel manager archives shared profile');
    status(403,function()use($aid,$pid,$person){AccountService::archive($aid,$pid,$person->lock_version,'partial manager',key4());},'Partial manager archives shared contact');
    status(404,function()use($aid,$h2){AssignmentService::preview($aid,$h2->id);},'Manager other hotel handover');
    $t1=task4($aid,$h1->id,$actors['a'],$pid); $t2=task4($aid,$h1->id,$actors['a'],null,array('status'=>'completed','completed_by'=>$actors['a'],'completed_at_utc'=>gmdate('Y-m-d H:i:s'),'completion_note'=>'Finished'));
    CustomerTestUser::$current=1; $tHidden=task4($aid,$h2->id,$actors['c']);
    $pending=rec4('TaskReminder',array('task_id'=>$t1->id,'recipient_user_id'=>$actors['a'],'schedule_version'=>1,'reminder_kind'=>'before','notify_at_utc'=>'2030-01-02 02:30:00'));
    $delivered=rec4('TaskReminder',array('task_id'=>$t1->id,'recipient_user_id'=>$actors['a'],'schedule_version'=>1,'reminder_kind'=>'overdue','notify_at_utc'=>'2030-01-03 02:00:00','state'=>'delivered','delivered_at_utc'=>gmdate('Y-m-d H:i:s')));
    rec4('Notification',array('reminder_id'=>$delivered->id,'task_id'=>$t1->id,'recipient_user_id'=>$actors['a'],'schedule_version'=>1,'kind'=>'overdue'));
    CustomerTestUser::$current=$actors['a']; ck((int)Notification::model()->count(CustomerPolicy::notificationCriteria())===1,'Old owner notification fixture');
    ck(AccountReadService::detail($aid,'tasks',1)['total']===2,'Task tab must exclude hidden hotel');
    CustomerTestUser::$current=$actors['manager']; $plan=AssignmentService::preview($aid,$h1->id); ck(count($plan['tasks'])===1,'Only open local tasks in handover preview');
    status(422,function()use($aid,$h1,$actors,$plan){AssignmentService::handover($aid,$h1->id,$actors['c'],$plan['expected_version'],$plan['plan_hash'],'wrong hotel owner',key4());},'Receiver lacks hotel');
    status(409,function()use($aid,$h1,$plan){AssignmentService::handover($aid,$h1->id,null,$plan['expected_version'],$plan['plan_hash'],'unassign open work',key4());},'Unassign with open tasks');
    $db->createCommand()->update('users',array('status'=>'I'),'id=:id',array(':id'=>$actors['b']));
    status(422,function()use($aid,$h1,$actors,$plan){AssignmentService::handover($aid,$h1->id,$actors['b'],$plan['expected_version'],$plan['plan_hash'],'inactive receiver',key4());},'Inactive receiver');
    $db->createCommand()->update('users',array('status'=>'A'),'id=:id',array(':id'=>$actors['b']));
    $t1->title='Changed after preview'; ck($t1->save(),'Task drift fixture');
    status(409,function()use($aid,$h1,$actors,$plan){AssignmentService::handover($aid,$h1->id,$actors['b'],$plan['expected_version'],$plan['plan_hash'],'stale preview',key4());},'Task changed after preview');
    $plan=AssignmentService::preview($aid,$h1->id); $key=key4();
    // Force validation failure after assignment save: the transaction must restore everything.
    $db->createCommand()->update('crm_contact_person',array('status'=>'archived'),'id=:id',array(':id'=>$pid));
    status(422,function()use($aid,$h1,$actors,$plan,$key){AssignmentService::handover($aid,$h1->id,$actors['b'],$plan['expected_version'],$plan['plan_hash'],'atomic handover',$key);},'Handover partial failure');
    ck((int)AccountHotel::model()->findByPk(array('account_id'=>$aid,'hotel_id'=>$h1->id))->owner_user_id===$actors['a'],'Assignment not rolled back');
    ck(TaskReminder::model()->findByPk($pending->id)->state==='pending','Reminder rollback');
    ck(!$db->createCommand('SELECT COUNT(*) FROM crm_customer_request WHERE request_key=:key')->queryScalar(array(':key'=>$key)),'Failed request journal persisted');
    $db->createCommand()->update('crm_contact_person',array('status'=>'active'),'id=:id',array(':id'=>$pid));
    $result=AssignmentService::handover($aid,$h1->id,$actors['b'],$plan['expected_version'],$plan['plan_hash'],'atomic handover',$key);
    ck($result['moved_tasks']===1,'Moved count'); ck(AssignmentService::handover($aid,$h1->id,$actors['b'],$plan['expected_version'],$plan['plan_hash'],'atomic handover',$key)['replayed'],'Handover replay');
    $fresh=Task::model()->findByPk($t1->id); ck((int)$fresh->assignee_user_id===$actors['b'] && (int)$fresh->schedule_version===2 && (int)$fresh->lock_version===(int)$t1->lock_version+1,'Task owner/version handover');
    ck((int)$fresh->created_by===(int)$t1->created_by && (int)Task::model()->findByPk($t2->id)->assignee_user_id===$actors['a'],'Historical attribution rewritten');
    ck((int)Task::model()->findByPk($tHidden->id)->assignee_user_id===$actors['c'],'Other hotel task moved');
    CustomerTestUser::$current=1;
    HotelAccessService::replace($actors['manager'],array(array('hotel_id'=>(string)$h2->id,'scope_level'=>'hotel'),array('hotel_id'=>(string)$h3->id,'scope_level'=>'hotel')),'Revoke H1 but retain shared profile through H2');
    CustomerTestUser::$current=$actors['manager'];
    ck((int)AccountReadService::detail($aid,'overview',1)['account']->id===(int)$aid,'Replay permission fixture still sees shared profile');
    status(404,function()use($aid,$h1,$actors,$plan,$key){AssignmentService::handover($aid,$h1->id,$actors['b'],$plan['expected_version'],$plan['plan_hash'],'atomic handover',$key);},'Replay leaked former Hotel after grant revoked');
    CustomerTestUser::$current=1;
    HotelAccessService::replace($actors['manager'],array(array('hotel_id'=>(string)$h1->id,'scope_level'=>'hotel'),array('hotel_id'=>(string)$h3->id,'scope_level'=>'hotel')),'Restore synthetic manager scope');

    ck(TaskReminder::model()->findByPk($pending->id)->state==='cancelled' && TaskReminder::model()->findByPk($delivered->id)->state==='delivered','Old reminder states');
    ck((int)TaskReminder::model()->count('task_id=:id AND schedule_version=2 AND recipient_user_id=:user AND state=\'pending\'',array(':id'=>$t1->id,':user'=>$actors['b']))===2,'Replacement reminder schedules');
    CustomerTestUser::$current=$actors['a']; status(404,function()use($aid){AccountReadService::detail($aid,'overview',1);},'Former owner retains profile');
    ck((int)Notification::model()->count(CustomerPolicy::notificationCriteria())===0,'Stale notification remains visible');
    CustomerTestUser::$current=$actors['b']; ck(AccountReadService::detail($aid,'tasks',1)['total']===2,'New owner cannot read completed historical tasks');
    CustomerTestUser::$current=1;
    status(409,function()use($actors){HotelAccessService::replace($actors['b'],array(),'must hand over');},'Revoke owner hotel grant');
    status(409,function()use($aid){AccountService::archive($aid,null,Account::model()->findByPk($aid)->lock_version,'open work',key4());},'Archive account with work');
    status(409,function()use($aid,$pid){AccountService::archive($aid,$pid,ContactPerson::model()->findByPk($pid)->lock_version,'open contact work',key4());},'Archive contact with work');
    $before=TaskReminderPlanner::plan($fresh,'2030-01-02 02:45:00'); ck($before['before']==='2030-01-02 02:45:00' && $before['overdue']==='2030-01-03 02:00:00','Timed planner immediate and next local nine');
    ck(TaskReminderPlanner::plan($fresh,'2030-01-02 03:00:00')===array('overdue'=>'2030-01-02 03:00:00'),'Overdue planner');
    $dateTask=clone $fresh; $dateTask->due_mode='date'; $dateTask->due_date='2030-01-02'; $dateTask->deadline_at_utc='2030-01-02 17:00:00';
    ck(TaskReminderPlanner::plan($dateTask,'2030-01-01 00:00:00')===array('due_day'=>'2030-01-02 02:00:00','overdue'=>'2030-01-03 02:00:00'),'Date-only planner');
    $appointment=clone $fresh; $appointment->kind='appointment'; $appointment->starts_at_utc='2030-01-02 01:00:00';
    ck(TaskReminderPlanner::plan($appointment,'2030-01-01 00:00:00')['before']==='2030-01-02 00:30:00','Appointment before start');
    ck(TaskReminderPlanner::plan($t2)===array(),'Closed task schedules');
    // Render every page, including shared hotel scope, field errors and escaped stored content.
    $xss=AccountService::create(array('name'=>'WP04 <script>alert(1)</script>'),$h1->id,$actors['b'],null,array(),key4());
    $html=run4('admin',array('q'=>'WP04')); ck(strpos($html,'&lt;script&gt;')!==false && strpos($html,'<script>alert(1)</script>')===false,'List XSS');
    ck(strpos(run4('create'),'name="request_key"')!==false,'Create page request key');
    ck(strpos(run4('update',array('id'=>$aid)),'expected_version')!==false,'Edit page version');
    foreach (array('overview','contacts','reports','tasks','sources','audit') as $tab) { ck(strpos(run4('view',array('id'=>$aid,'tab'=>$tab)),'WP04 Unified Company')!==false,'Detail tab '.$tab); }
    ck(strpos(run4('contactcreate',array('id'=>$aid)),'Person[first_name]')!==false,'Contact form');
    ck(strpos(run4('contactupdate',array('id'=>$aid,'person_id'=>$pid)),'0900000000')!==false,'Contact edit value');
    ck(strpos(run4('assignpreview',array('id'=>$aid,'hotel_id'=>$h1->id)),'name="plan_hash"')!==false,'Handover form');
    $freshPreview=AssignmentService::preview($aid,$h1->id); $extraTask=task4($aid,$h1->id,$actors['b']);
    status(409,function()use($aid,$h1,$actors,$freshPreview){AssignmentService::handover($aid,$h1->id,$actors['a'],$freshPreview['expected_version'],$freshPreview['plan_hash'],'New task after preview',key4());},'New task added after preview');
    $db->createCommand()->update('crm_task',array('status'=>'cancelled','cancelled_at_utc'=>gmdate('Y-m-d H:i:s'),'cancel_reason'=>'Synthetic close'),'id=:id',array(':id'=>$extraTask->id));

    ck(strpos(run4('hotelaccess',array('id'=>$actors['b'],'format'=>'html'),array(),'GET',true,true),'scopes[')!==false,'Hotel grant UI');
    $grants=$db->createCommand('SELECT hotel_id,scope_level FROM crm_user_hotel_access WHERE user_id=:id ORDER BY hotel_id')->queryAll(true,array(':id'=>$actors['reader']));
    $grantPost=array('ui'=>'1','scopes'=>array($h1->id=>'hotel',$h3->id=>'own'),'reason'=>'HTTP grant update','expected_hash'=>hash('sha256',CJSON::encode($grants)));
    ck(run4('hotelaccess',array('id'=>$actors['reader'],'format'=>'html'),$grantPost,'POST',true,true)==='redirect','Hotel grant POST form');
    status(409,function()use($actors,$grantPost){run4('hotelaccess',array('id'=>$actors['reader'],'format'=>'html'),$grantPost,'POST',true,true);},'Stale hotel grant form');

    status(405,function()use($aid){run4('assign',array('id'=>$aid));},'GET handover mutation');
    status(405,function()use($aid){run4('archive',array('id'=>$aid));},'GET archive mutation');
    status(400,function()use($aid){run4('update',array('id'=>$aid),array(),'POST',false);},'Missing CSRF update');
    status(400,function()use($aid){run4('assign',array('id'=>$aid),array(),'POST',false);},'Missing CSRF handover');
    $formKey=key4(); $post=array('Account'=>array('name'=>'WP04 POST form','email'=>'bad'),'hotel_id'=>$h1->id,'owner_user_id'=>$actors['b'],'request_key'=>$formKey);
    $html=run4('create',array(),$post,'POST'); ck(strpos($html,'Chưa lưu được')!==false && strpos($html,'WP04 POST form')!==false && strpos($html,$formKey)!==false,'Validation must preserve form');
    $post['Account']['email']='valid@example.test'; $result=CJSON::decode(run4('create',array('format'=>'json'),$post,'POST')); ck(!empty($result['account_id']),'Create POST endpoint');
    $editPost=array('Account'=>array('notes'=>'Updated through HTTP'),'markets'=>'VN','expected_version'=>$result['version'],'request_key'=>key4());
    ck(run4('update',array('id'=>$result['account_id']),$editPost,'POST')==='redirect','Update POST endpoint');
    $contactPost=array('Person'=>array('first_name'=>'HTTP Contact'),'request_key'=>key4()); $httpContact=CJSON::decode(run4('contactcreate',array('id'=>$result['account_id'],'format'=>'json'),$contactPost,'POST')); ck(!empty($httpContact['contact_id']),'Contact POST endpoint');
    $plan=CJSON::decode(run4('assignpreview',array('id'=>$result['account_id'],'hotel_id'=>$h1->id,'format'=>'json')));
    $assignPost=array('hotel_id'=>$h1->id,'owner_user_id'=>$actors['a'],'expected_version'=>$plan['expected_version'],'plan_hash'=>$plan['plan_hash'],'reason'=>'HTTP handover','request_key'=>key4());
    ck(run4('assign',array('id'=>$result['account_id']),$assignPost,'POST')==='redirect','Handover POST endpoint');
    CustomerTestUser::$current=$actors['reader']; status(403,function(){run4('create');},'Read role writes');
    ck(strpos(run4('admin'),'WP04 Unified Company')!==false,'Read role list');
    CustomerTestUser::$current=1;
    $peer=Yii::createComponent(require __DIR__.'/../config/db-test.php'); $peer->setActive(true); $mutex=CustomerImportService::lockName();
    ck((int)$peer->createCommand('SELECT GET_LOCK(:key,0)')->queryScalar(array(':key'=>$mutex))===1,'Peer lock');
    try { status(409,function()use($h1){AccountService::create(array('name'=>'Must wait for importer'),$h1->id,null,null,array(),key4());},'Shared importer mutex'); }
    finally { $peer->createCommand('SELECT RELEASE_LOCK(:key)')->queryScalar(array(':key'=>$mutex)); $peer->setActive(false); }
    // Archive shared records only after every Hotel has finished its open work.
    $db->createCommand()->update('crm_task',array('status'=>'cancelled','cancelled_at_utc'=>gmdate('Y-m-d H:i:s'),'cancel_reason'=>'Synthetic cleanup'),"account_id=:id AND status IN ('open','in_progress')",array(':id'=>$aid));
    AccountService::archive($aid,$pid,ContactPerson::model()->findByPk($pid)->lock_version,'Contact left company',key4());
    $blocked=new Task; $blocked->setAttributes(array('account_id'=>$aid,'contact_person_id'=>$pid,'hotel_id'=>$h1->id,'assignee_user_id'=>$actors['b'],'code'=>'BAD-'.substr(key4(),0,20),'client_request_key'=>key4(),'title'=>'Blocked','due_mode'=>'date','due_date'=>'2030-02-01','timezone'=>'Asia/Ho_Chi_Minh'),false);
    ck(!$blocked->save() && $blocked->hasErrors('account_id'),'Task referencing archived contact');
    AccountService::archive($aid,null,Account::model()->findByPk($aid)->lock_version,'No longer active',key4());
    ck(Account::model()->findByPk($aid)->status==='archived' && count(AccountReadService::assignments($aid))===2,'Archive must retain historical scope');
    status(409,function()use($aid){AccountService::update($aid,array('notes'=>'Should reject'),array(),Account::model()->findByPk($aid)->lock_version,key4());},'Archived profile edit');
    status(409,function()use($aid){AccountService::person($aid,null,array('first_name'=>'New'),null,key4());},'Archived profile contact create');
    status(409,function()use($aid,$h3){AssignmentService::preview($aid,$h3->id);},'Archived profile new hotel');
    $blocked->contact_person_id=null; ck(!$blocked->save(),'Task referencing archived account');
    CustomerTestUser::$current=$actors['b']; ck(AccountReadService::detail($aid,'overview',1)['account']->status==='archived','Archive historical access');
    CustomerTestUser::$current=1; $flags=$app->params['mvp']; $off=$flags; $off['customerWrite']=false; $app->params['mvp']=$off;
    status(503,function()use($h1){AccountService::create(array('name'=>'Disabled'),$h1->id,null,null,array(),key4());},'Feature flag guard'); $app->params['mvp']=$flags;
    require_once __DIR__.'/../migrations/mvp/m261118_200000_prepare_customer_workflows.php';
    try { (new m261118_200000_prepare_customer_workflows)->down(); throw new Exception('Rollback with requests accepted'); } catch (CDbException $e) { ck(strpos($e->getMessage(),'requests')!==false,'Migration rollback guard'); }
} finally {
    if ($tx->active) { $tx->rollback(); }
    foreach (array('roles','users') as $table) { $db->createCommand('DROP TEMPORARY TABLE IF EXISTS '.$table)->execute(); }
}
foreach ($legacyBefore as $table=>$rows) { ck($rows===$db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll(),'Legacy fixture not restored '.$table); }
foreach ($baseline as $table=>$rows) { ck($rows===$db->createCommand('SELECT * FROM '.$table)->queryAll(),'Users/roles fixture not restored '.$table); }
ob_end_clean(); echo "PASS: $checks customer assertions; scoped pages, contact CRUD/archive, action/CSRF, duplicate review, version/replay, atomic Hotel handover, reminder schedules and rollback.\n";
