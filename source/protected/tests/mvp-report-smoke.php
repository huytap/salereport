<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ob_start(); define('IS_IN_TESTS',true); define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
class ReportTestUser extends CWebUser { public static $current=1; public function init() {} public function getId() { return self::$current; } public function getIsGuest() { return self::$current===null; } }
class ReportTestRequest extends CHttpRequest { public $method='GET'; public function getRequestType() { return $this->method; } public function getIsPostRequest() { return $this->method==='POST'; } }
class ReportRedirect extends Exception {}
$root=dirname(__DIR__,2); $_SERVER['SCRIPT_FILENAME']=$root.'/index.php'; $_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['HTTP_HOST']='crm.test.local'; $_SERVER['REQUEST_URI']='/'; $_SERVER['REQUEST_METHOD']='GET';
$config=require __DIR__.'/../config/test.php'; $config['components']['user']=array('class'=>'ReportTestUser'); $config['components']['request']=array('class'=>'ReportTestRequest','enableCsrfValidation'=>true);
$app=Yii::createWebApplication($config); $db=$app->db; $db->setActive(true); if ($db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Synthetic test fixture required.'); }
Yii::import('application.controllers.*'); class ReportControllerTest extends ReportController { public function redirect($url,$terminate=true,$statusCode=302) { throw new ReportRedirect; } }
require_once __DIR__.'/support/TemporaryTableFixture.php'; $checks=0;
function rc($ok,$message) { global $checks; if (!$ok) { throw new Exception($message); } $checks++; }
function rs($expected,$fn,$message) { try { $fn(); } catch (CHttpException $e) { rc($e->statusCode===$expected,$message.' got '.$e->statusCode.' '.$e->getMessage()); return; } throw new Exception($message.' not rejected'); }
function rk() { return bin2hex(random_bytes(16)); }
function rr($class,$values) { $m=new $class; $m->setAttributes($values,false); rc($m->save(),$class.': '.CJSON::encode($m->errors)); return $m; }
function reportFields($overrides=array()) { return array_merge(array('date'=>'2026-11-19','sales_type'=>'Visit','objective'=>'Follow up','expected_result'=>'Groups','report'=>'Synthetic visit 😀 <script>bad()</script>','follow_up'=>'Send quote','action'=>'Call','comments'=>'Synthetic note','new_account'=>'No','highlight'=>0),$overrides); }
function reportTask($title,$overrides=array()) { return array('fields'=>array_merge(array('title'=>$title,'kind'=>'task','due_mode'=>'date','due_date'=>'2030-01-08','due_at_utc'=>null,'starts_at_utc'=>null,'ends_at_utc'=>null,'timezone'=>'Asia/Ho_Chi_Minh'),$overrides)); }
function reportHttp($action,$get=array(),$post=array(),$method='GET',$csrf=true) {
    $_GET=$get; $_POST=$post; Yii::app()->request->method=$method; http_response_code(200);
    if ($method==='POST' && $csrf) { $_POST[Yii::app()->request->csrfTokenName]=Yii::app()->request->csrfToken; }
    $c=new ReportControllerTest('report'); Yii::app()->controller=$c; $c->init(); ob_start();
    try { $c->run($action); return ob_get_contents(); } catch (ReportRedirect $e) { return 'redirect'; } finally { ob_end_clean(); }
}
$baseline=array(); foreach (array('users','roles') as $table) { $baseline[$table]=$db->createCommand('SELECT * FROM '.$table)->queryAll(); $ddl=$db->createCommand('SHOW CREATE TABLE '.$table)->queryRow(); $db->createCommand(isolatedTemporaryTableDdl($ddl['Create Table']))->execute(); foreach ($baseline[$table] as $row) { $db->createCommand()->insert($table,$row); } }
$legacy=array(); foreach (array('contact','reports','trackings','user_history') as $table) { $legacy[$table]=$db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll(); }
$tx=$db->beginTransaction();
try {
    $actors=array(); foreach (array('a'=>'users','b'=>'users','other'=>'users','manager'=>'admin','reader'=>'reservation') as $label=>$role) { $u=new Users('create'); $u->attributes=array('username'=>'wp06_'.$label,'fullname'=>'WP06 '.$label,'password'=>'fake','roles'=>$role,'status'=>'A','last_login_date'=>'2026-11-19 00:00:00'); rc($u->save(),'User fixture'); $actors[$label]=(int)$u->id; }
    $h1=rr('Hotel',array('code'=>'WP06-H1','name'=>'WP06 Hotel One')); $h2=rr('Hotel',array('code'=>'WP06-H2','name'=>'WP06 Hidden Hotel'));
    foreach (array(array('a',$h1,'own'),array('b',$h1,'own'),array('other',$h2,'own'),array('manager',$h1,'hotel'),array('reader',$h1,'hotel')) as $g) { rr('UserHotelAccess',array('user_id'=>$actors[$g[0]],'hotel_id'=>$g[1]->id,'scope_level'=>$g[2])); }
    $markets=array(); for ($i=0;$i<20;$i++) { $markets[]=sprintf('%02d',$i).str_repeat('M',62); }
    $name=str_repeat('N',240).' KH 😀';
    $account=AccountService::create(array('name'=>$name,'segment'=>'CORPORATE','phone'=>'02811112222','email'=>'company@example.test','building'=>str_repeat('D',128),'address_1'=>str_repeat('A',255),'address_2'=>str_repeat('B',255),'address_3'=>str_repeat('C',255)), $h1->id,$actors['a'],array('first_name'=>'Lan','last_name'=>str_repeat('L',128),'position'=>str_repeat('P',128),'email'=>'person@example.test','phone'=>'0900000000'),$markets,rk()); $aid=$account['account_id']; $pid=$account['contact_id'];
    $hidden=AccountService::create(array('name'=>'WP06 Hidden'),$h2->id,$actors['other'],array('first_name'=>'Hidden person'),array(),rk());
    $unassigned=AccountService::create(array('name'=>'WP06 Unassigned'),$h1->id,null,null,array(),rk());
    rc(ActionAccess::can('report','create',$actors['a']) && ActionAccess::can('report','update',$actors['manager']) && !ActionAccess::can('report','create',$actors['reader']),'Report action seed');
    ReportTestUser::$current=$actors['a'];
    rs(404,function()use($hidden,$h2){ReportService::create($hidden['account_id'],$h2->id,null,reportFields(),array(),rk());},'Cross-Hotel Report create');
    rs(404,function()use($aid,$h1,$hidden){ReportService::create($aid,$h1->id,$hidden['contact_id'],reportFields(),array(),rk());},'Cross-account contact');
    rs(400,function()use($aid,$h1){ReportService::create($aid,$h1->id,null,reportFields(array('company_name'=>'Forged snapshot')),array(),rk());},'Snapshot injection');
    rs(400,function()use($aid,$h1){ReportService::create($aid,$h1->id,null,reportFields(array('added_by'=>1)),array(),rk());},'Actor injection');
    rs(400,function()use($aid,$h1){ReportService::create($aid,$h1->id,null,reportFields(array('ent'=>'2030-01-01')),array(),rk());},'Legacy schedule injection');
    $counts=array(Report::model()->count(),Task::model()->count(),TaskReminder::model()->count(),ContactPerson::model()->count(),AuditEvent::model()->count());
    try { ReportService::create($aid,$h1->id,null,reportFields(array('date'=>'bad','report'=>'')),array(reportTask(''),reportTask('Invalid date',array('due_date'=>'2030-02-31'))),rk(),array('first_name'=>'Would be orphan')); throw new Exception('Invalid composite accepted'); }
    catch (CustomerValidationException $e) { rc(isset($e->errors['Report.date'],$e->errors['Report.report'],$e->errors['tasks.0.title'],$e->errors['tasks.1.due_date']),'All field/row validation errors returned'); }
    rc($counts===array(Report::model()->count(),Task::model()->count(),TaskReminder::model()->count(),ContactPerson::model()->count(),AuditEvent::model()->count()),'Prevalidation changed rows');
    $key=rk(); $rows=array(reportTask('Send quote'),reportTask('Visit hotel',array('kind'=>'appointment','due_mode'=>'datetime','due_date'=>null,'due_at_utc'=>'2030-01-09 04:00:00','starts_at_utc'=>'2030-01-09 03:00:00','ends_at_utc'=>'2030-01-09 04:00:00')));
    $result=ReportService::create($aid,$h1->id,$pid,reportFields(),$rows,$key); $rid=$result['report_id'];
    rc(count($result['tasks'])===2 && $result['version']===1,'Report and two tasks');
    $m=Report::model()->findByPk($rid); $snapshot=CJSON::decode($m->snapshot_json);
    rc($m->company_name===$name && mb_strlen($m->last_name)===128 && mb_strlen($m->position)===128,'Snapshot not truncated');
    rc(mb_strlen($m->company_address)===896 && mb_strlen($m->market)===1299,'Full address/markets preserved');
    rc($m->email==='person@example.test' && $m->phone==='0900000000' && $m->hotels===$h1->name,'Snapshot Contact/Hotel from server');
    rc($snapshot['account']['phone']==='02811112222' && (int)$snapshot['contact']['id']===(int)$pid && (int)$snapshot['owner_user_id']===$actors['a'],'Full canonical snapshot stored');
    rc($m->ent===null && $m->sales===null && $m->site_ins===null && $m->done===null,'No invented legacy dates or done');
    rc((int)$m->added_by===$actors['a'] && $m->created_at_utc && $m->updated_at_utc,'Report audit actor/UTC');
    foreach ($result['tasks'] as $r) { $t=Task::model()->findByPk($r['task_id']); rc((int)$t->report_id===(int)$rid && (int)$t->account_id===(int)$aid && (int)$t->hotel_id===(int)$h1->id && (int)$t->contact_person_id===(int)$pid && (int)$t->assignee_user_id===$actors['a'],'Task context/inherited contact/current owner'); }
    rc((int)TaskReminder::model()->count('task_id IN ('.implode(',',array_map('intval',array_column($result['tasks'],'task_id'))).')')===4,'All task reminder plans');
    $auditCount=AuditEvent::model()->count(); rc(ReportService::create($aid,$h1->id,$pid,reportFields(),$rows,$key)['replayed'],'Report create replay'); rc(AuditEvent::model()->count()===$auditCount,'Replay duplicated audit');
    rs(409,function()use($aid,$h1,$pid,$rows,$key){ReportService::create($aid,$h1->id,$pid,reportFields(array('report'=>'Changed')), $rows,$key);},'Request key payload drift');
    $safe=Report::model()->findByPk($rid); $safe->attributes=array('account_id'=>999,'snapshot_json'=>'forged','created_at_utc'=>'2000-01-01 00:00:00'); rc((int)$safe->account_id===(int)$aid && $safe->snapshot_json===$m->snapshot_json && $safe->created_at_utc===$m->created_at_utc,'Report model protects MVP identity/snapshot/audit fields');
    $originalSnapshot=$m->snapshot_json; $originalLegacy=array($m->company_name,$m->first_name,$m->email,$m->market);
    AccountService::update($aid,array('name'=>'Renamed after report'),array('VN'),Account::model()->findByPk($aid)->lock_version,rk()); AccountService::person($aid,$pid,array('first_name'=>'Changed after report','email'=>'new@example.test'),ContactPerson::model()->findByPk($pid)->lock_version,rk());
    $changed=Report::model()->findByPk($rid); rc($changed->snapshot_json===$originalSnapshot && array($changed->company_name,$changed->first_name,$changed->email,$changed->market)===$originalLegacy,'Canonical edit rewrote Report snapshot');
    rc(ReportService::create($aid,$h1->id,$pid,reportFields(),$rows,$key)['replayed'],'Replay after canonical changes');
    $update=ReportService::update($rid,array('report'=>'Revised narrative','comments'=>'Keep snapshot'),array(reportTask('New follow-up')),1,rk()); rc($update['version']===2 && count($update['tasks'])===1,'Update plus new task');
    $changed=Report::model()->findByPk($rid); rc($changed->snapshot_json===$originalSnapshot && (int)$changed->added_by===$actors['a'] && $changed->added_date===$m->added_date,'Update changed snapshot/creator');
    rs(409,function()use($rid){ReportService::update($rid,array('report'=>'stale'),array(),1,rk());},'Stale Report update');
    rs(400,function()use($rid){ReportService::update($rid,array('account_id'=>999),array(),2,rk());},'Rebind Report identity');
    $counts=array(Report::model()->findByPk($rid)->report,Task::model()->count(),AuditEvent::model()->count());
    rs(422,function()use($rid){ReportService::update($rid,array('report'=>'Must rollback'),array(reportTask('valid'),reportTask('bad',array('due_date'=>'bad'))),2,rk());},'One invalid appended task');
    rc($counts===array(Report::model()->findByPk($rid)->report,Task::model()->count(),AuditEvent::model()->count()),'Update partial persistence');
    $quick=ReportService::create($aid,$h1->id,null,reportFields(),array(reportTask('Inherited new contact')),rk(),array('first_name'=>'New quick person','email'=>'quick@example.test'));
    rc($quick['contact_id'] && (int)Report::model()->findByPk($quick['report_id'])->contact_person_id===(int)$quick['contact_id'],'Quick Contact bound to Report');
    rc((int)Task::model()->findByPk($quick['tasks'][0]['task_id'])->contact_person_id===(int)$quick['contact_id'],'Quick Contact inherited by task');
    rs(422,function()use($aid,$h1){ReportService::create($aid,$h1->id,null,reportFields(),array(),rk(),array('first_name'=>'New quick person'));},'Duplicate quick Contact');
    rs(400,function()use($aid,$h1,$pid){ReportService::create($aid,$h1->id,$pid,reportFields(),array(),rk(),array('first_name'=>'ambiguous'));},'Existing and new contact combined');
    $case='ReportCaseKey'.rk(); $one=ReportService::create($aid,$h1->id,null,reportFields(),array(),$case); $two=ReportService::create($aid,$h1->id,null,reportFields(),array(),strtolower($case)); rc((int)$one['report_id']!==(int)$two['report_id'],'Case-sensitive Report request key');
    rs(400,function()use($aid,$h1){ReportService::create($aid,$h1->id,null,reportFields(),array_fill(0,21,reportTask('too many')),rk());},'Task count bound');
    // Rule is inclusive through calendar day +7 in Asia/Ho_Chi_Minh, independent of business date.
    $deadline=ReportService::editDeadline($changed); rc(ReportService::canEdit($changed,(new DateTimeImmutable($deadline,new DateTimeZone('UTC')))->modify('-1 second')->format('Y-m-d H:i:s')),'Day seven last second'); rc(!ReportService::canEdit($changed,$deadline),'Day eight boundary');
    $db->createCommand()->update('reports',array('created_at_utc'=>'2000-01-01 00:00:00'),'id=:id',array(':id'=>$rid));
    rs(409,function()use($rid){ReportService::update($rid,array('report'=>'Expired'),array(reportTask('No task')),2,rk());},'Expired Report update');
    $before=LegacyImportSource::hash(Report::model()->findByPk($rid)->attributes,'report');
    $t=TaskService::create($aid,$h1->id,reportTask('Work from locked report')['fields'],rk(),false,'',null,$rid); TaskService::complete($t['task_id'],1,'Task independent',rk());
    rc($before===LegacyImportSource::hash(Report::model()->findByPk($rid)->attributes,'report'),'Task changed locked Report');
    $html=reportHttp('view',array('id'=>$rid)); rc(strpos($html,'Lịch sử báo cáo')!==false && strpos($html,'Revised narrative')!==false,'Report history rendered'); rc(strpos($html,'đã khóa')!==false && strpos($html,'Work from locked report')!==false && strpos($html,'Thêm việc từ báo cáo này')!==false,'Locked detail keeps tasks and task action');
    ReportTestUser::$current=$actors['b']; rs(404,function()use($rid){reportHttp('view',array('id'=>$rid));},'Other Own Report detail');
    ReportTestUser::$current=$actors['manager']; rs(404,function()use($hidden,$h2){ReportService::create($hidden['account_id'],$h2->id,null,reportFields(),array(),rk());},'Hotel manager outside scope');
    $plan=AssignmentService::preview($aid,$h1->id); AssignmentService::handover($aid,$h1->id,$actors['b'],$plan['expected_version'],$plan['plan_hash'],'Handover reports and tasks',rk());
    ReportTestUser::$current=$actors['a']; rs(404,function()use($aid,$h1,$pid,$rows,$key){ReportService::create($aid,$h1->id,$pid,reportFields(),$rows,$key);},'Former owner replay');
    ReportTestUser::$current=$actors['b']; rc(strpos(reportHttp('view',array('id'=>$rid)),'Revised narrative')!==false,'New owner reads historic Report');
    $new=ReportService::create($aid,$h1->id,null,reportFields(),array(reportTask('New owner work')),rk()); rc((int)Task::model()->findByPk($new['tasks'][0]['task_id'])->assignee_user_id===$actors['b'],'New owner assignment');
    // Existing legacy linked report with zero date columns is edited without touching those columns.
    ReportTestUser::$current=1; $raw=$legacy['reports'][0]; unset($raw['id']); $raw['account_id']=$aid; $raw['hotel_id']=$h1->id; $raw['contact_person_id']=null; $raw['identity_state']='linked'; $raw['client_request_key']=null; $raw['snapshot_json']=null; $raw['created_at_utc']=null; $raw['updated_at_utc']=null; $raw['added_date']=(new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'); $raw['ent']=$raw['sales']=$raw['site_ins']='0000-00-00';
    $mode=$db->createCommand('SELECT @@SESSION.sql_mode')->queryScalar(); try { $db->createCommand("SET SESSION sql_mode=''")->execute(); $db->createCommand()->insert('reports',$raw); $legacyId=$db->lastInsertID; } finally { $db->createCommand('SET SESSION sql_mode=:mode')->execute(array(':mode'=>$mode)); }
    ReportService::update($legacyId,array('report'=>'Approved legacy edit'),array(),1,rk()); $legacyEdited=Report::model()->findByPk($legacyId); rc($legacyEdited->sales==='0000-00-00' && $legacyEdited->site_ins==='0000-00-00' && $legacyEdited->ent==='0000-00-00' && $legacyEdited->snapshot_json===null,'Legacy zero dates/snapshot preserved');
    $hashRaw=$legacy['reports'][0]; $withColumns=$hashRaw; unset($hashRaw['snapshot_json'],$hashRaw['created_at_utc'],$hashRaw['updated_at_utc']); rc(LegacyImportSource::hash($hashRaw,'report')===LegacyImportSource::hash($withColumns,'report'),'WP06 columns changed old import fingerprint');
    // Controller form, add/remove row and JSON mutation paths.
    ReportTestUser::$current=$actors['b']; $html=reportHttp('create',array('account_id'=>$aid,'hotel_id'=>$h1->id)); rc(strpos($html,'name="request_key"')!==false && strpos($html,'Lập Sales Report')!==false,'Report form render');
    $post=array('ui'=>'1','account_id'=>$aid,'hotel_id'=>$h1->id,'contact_person_id'=>'','request_key'=>rk(),'Report'=>reportFields(),'form_action'=>'add_task');
    $count=Report::model()->count(); $html=reportHttp('create',array(),$post,'POST'); rc(strpos($html,'tasks[0][fields][title]')!==false && Report::model()->count()===$count,'Add task row writes no Report');
    $post['tasks']=array(reportTask('First'),reportTask('Second')); unset($post['form_action']); $post['remove_task']='0'; $html=reportHttp('create',array(),$post,'POST'); rc(strpos($html,'Second')!==false && strpos($html,'tasks[1][fields][title]')===false,'Remove/reindex row');
    unset($post['remove_task']); $post['tasks']=array(reportTask('HTTP attached')); $httpResult=CJSON::decode(reportHttp('create',array('format'=>'json'),$post,'POST')); rc(count($httpResult['tasks'])===1,'HTTP Report+Task commit');
    $bad=$post; $bad['request_key']=rk(); $bad['tasks']=array(reportTask('invalid',array('due_date'=>'bad'))); $err=CJSON::decode(reportHttp('create',array('format'=>'json'),$bad,'POST')); rc(http_response_code()===422 && $err['error_code']==='VALIDATION_ERROR' && isset($err['field_errors']['tasks.0.due_date']),'JSON row error contract');
    $html=reportHttp('create',array(),$bad,'POST'); rc(strpos($html,$bad['request_key'])!==false && strpos($html,'invalid')!==false && strpos($html,'Chưa lưu được')!==false,'HTML keeps report/tasks/key on failure');
    rc(strpos($html,'Việc mới 1 — Ngày đến hạn')!==false && strpos($html,'tasks.0.due_date:')===false,'HTML validation uses human row/field labels while JSON retains keys');
    $err=CJSON::decode(reportHttp('create',array('format'=>'json'),$post,'POST',false)); rc(http_response_code()===400 && $err['error_code']==='INVALID_REQUEST','Report POST CSRF');
    $err=CJSON::decode(reportHttp('create',array('format'=>'json'),array('company'=>'Renamed after report'),'POST')); rc(http_response_code()===410,'Legacy company clone disabled');
    $edit=array('Report'=>array('report'=>'HTTP revised'),'tasks'=>array(reportTask('HTTP appended')),'expected_version'=>$httpResult['version'],'request_key'=>rk()); $edited=CJSON::decode(reportHttp('update',array('id'=>$httpResult['report_id'],'format'=>'json'),$edit,'POST')); rc($edited['version']===2 && count($edited['tasks'])===1,'HTTP update+task');
    $html=reportHttp('view',array('id'=>$new['report_id'])); rc(strpos($html,'&lt;script&gt;')!==false && strpos($html,'<script>bad()</script>')===false,'Report detail escapes stored text');
    ReportTestUser::$current=$actors['reader']; $err=CJSON::decode(reportHttp('create',array('format'=>'json'))); rc(http_response_code()===403 && $err['error_code']==='FORBIDDEN','Reader cannot create');
    ReportTestUser::$current=$actors['manager']; $only=ReportService::create($unassigned['account_id'],$h1->id,null,reportFields(),array(),rk()); rc($only['report_id'] && !$only['tasks'],'Unassigned customer Report without task');
    rs(422,function()use($unassigned,$h1){ReportService::create($unassigned['account_id'],$h1->id,null,reportFields(),array(reportTask('No owner')),rk());},'Unassigned customer cannot receive task');
    ReportTestUser::$current=1;
    $badShape=$post; $badShape['form_action']='add_task'; $badShape['tasks']=array('bad-key'=>reportTask('bad index'));
    $err=CJSON::decode(reportHttp('create',array('format'=>'json'),$badShape,'POST')); rc(http_response_code()===400 && $err['error_code']==='INVALID_REQUEST','Malformed task list during form action');
    $badShape['tasks']=array(reportTask(array('nested'))); $err=CJSON::decode(reportHttp('create',array('format'=>'json'),$badShape,'POST')); rc(http_response_code()===400 && $err['error_code']==='INVALID_REQUEST','Malformed task field during add row');
    $flags=$app->params['mvp']; $oldCustomer=$flags; $oldCustomer['customerWrite']=false; $app->params['mvp']=$oldCustomer;
    $err=CJSON::decode(reportHttp('import',array('format'=>'json'))); rc(http_response_code()===410,'Legacy import closed by Report flag independently');
    $reportCount=Report::model()->count(); $err=CJSON::decode(reportHttp('delete',array('id'=>$new['report_id'],'format'=>'json'),array(),'POST')); rc(http_response_code()===410 && Report::model()->count()===$reportCount,'Legacy delete cannot remove canonical Report');
    $app->params['mvp']=$flags;
    ReportTestUser::$current=1; $flags=$app->params['mvp']; $off=$flags; $off['reportWrite']=false; $app->params['mvp']=$off; rs(503,function()use($aid,$h1){ReportService::create($aid,$h1->id,null,reportFields(),array(),rk());},'Report feature gate'); $app->params['mvp']=$flags;
    require_once __DIR__.'/../migrations/mvp/m261119_110000_prepare_report_workflows.php'; try { (new m261119_110000_prepare_report_workflows)->down(); throw new Exception('Report data rollback allowed'); } catch (CDbException $e) { rc(strpos($e->getMessage(),'workflow data')!==false,'Migration preserves Report workflow history'); }
} finally { if ($tx->active) { $tx->rollback(); } foreach (array('roles','users') as $table) { $db->createCommand('DROP TEMPORARY TABLE IF EXISTS '.$table)->execute(); } }
foreach ($legacy as $table=>$rows) { rc($rows===$db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll(),'Legacy changed '.$table); }
foreach ($baseline as $table=>$rows) { rc($rows===$db->createCommand('SELECT * FROM '.$table)->queryAll(),'User/role changed '.$table); }
ob_end_clean(); echo "PASS: $checks Report assertions; canonical snapshots, multiple tasks/row errors, transaction rollback, replay/version, scopes/handover, quick contact, 7-day lock, legacy preservation and HTML/JSON/CSRF.\n";
