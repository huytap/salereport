<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
define('IS_IN_TESTS',true);
define('YII_ENABLE_EXCEPTION_HANDLER',false);
define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
class PolicyUser extends CWebUser
{
    public static $current=1;
    public function init() {}
    public function getId() { return self::$current; }
    public function getIsGuest() { return self::$current===null; }
    public function getState($key,$default=null) { return $key==='type'?'superadmin':$default; } // Deliberately stale/untrusted session.
}
class PolicyRequest extends CHttpRequest
{
    public $method='GET';
    public $ajax=false;
    public function getRequestType() { return $this->method; }
    public function getIsPostRequest() { return $this->method==='POST'; }
    public function getIsAjaxRequest() { return $this->ajax; }
}
class PolicyEnd extends Exception {}
class PolicyApp extends CWebApplication
{
    public function end($status=0,$exit=true) { throw new PolicyEnd; }
}
$root=dirname(__DIR__,2);
$_SERVER['SCRIPT_FILENAME']=$root.'/index.php'; $_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['HTTP_HOST']='crm.test.local'; $_SERVER['REQUEST_URI']='/'; $_SERVER['REQUEST_METHOD']='GET';
$config=require __DIR__.'/../config/test.php';
$config['params']['mvp']['reportWrite']=false; // Exercise legacy route guards before Report cutover.
$config['params']['mvp']['customerWrite']=false; // Exercise pre-cutover legacy compatibility.
$config['components']['user']=array('class'=>'PolicyUser');
$config['components']['request']=array('class'=>'PolicyRequest','enableCsrfValidation'=>true);
$app=Yii::createApplication('PolicyApp',$config); $db=$app->db; $db->setActive(true);
if ($db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Synthetic isolated fixture required.'); }
Yii::import('application.controllers.*');
require_once __DIR__.'/support/TemporaryTableFixture.php';
$count=0;
function policyCheck($condition,$message) { global $count; if (!$condition) { throw new Exception($message); } $count++; }
function policyStatus($status,$callback,$message)
{
    try { $callback(); } catch (CHttpException $e) { policyCheck($e->statusCode===$status,$message.' (received '.$e->statusCode.')'); return; }
    throw new Exception($message.' (not denied)');
}
function policyRun($controller,$action,$get=array(),$post=array(),$method='GET',$csrf=true,$ajax=false)
{
    $_GET=$get; $_POST=$post; Yii::app()->request->method=$method; Yii::app()->request->ajax=$ajax;
    if ($method==='POST' && $csrf) { $_POST[Yii::app()->request->csrfTokenName]=Yii::app()->request->csrfToken; }
    $class=ucfirst($controller).'Controller'; $c=new $class($controller); Yii::app()->controller=$c; $c->init();
    ob_start();
    try { $c->run($action); return ob_get_contents(); }
    catch (PolicyEnd $e) { return ob_get_contents(); }
    finally { ob_end_clean(); }
}
function policyRecord($class,$values)
{
    $m=new $class; $m->setAuditActorId(1); $m->setAttributes($values,false);
    policyCheck($m->save(),$class.': '.CJSON::encode($m->errors)); return $m;
}
function policyActor($name,$roles)
{
    $m=new Users('create'); $m->attributes=array('username'=>$name,'fullname'=>$name,'password'=>'fixture-password','status'=>'A','roles'=>$roles,'last_login_date'=>'2026-11-18 00:00:00');
    policyCheck($m->save(),'Synthetic user: '.CJSON::encode($m->errors)); return $m;
}
function policyIds($class,$type)
{
    return array_map('intval',Yii::app()->db->createCommand('SELECT t.id FROM '.$class::model()->tableName().' t WHERE '.(CustomerPolicy::scopeCriteria($type)->condition ?: '1=1').' ORDER BY t.id')->queryColumn());
}
$legacy=array();
foreach (array('users','roles','contact') as $table) {
    $legacy[$table]=$db->createCommand('SELECT * FROM '.$table)->queryAll();
    $ddl=$db->createCommand('SHOW CREATE TABLE '.$table)->queryRow();
    $db->createCommand(isolatedTemporaryTableDdl($ddl['Create Table']))->execute();
    foreach ($legacy[$table] as $row) { $db->createCommand()->insert($table,$row); }
}
$tx=$db->beginTransaction();
try {
    // Deliberately over-grant legacy actions to prove object scope still blocks them.
    foreach (array('users','admin') as $roleName) {
        $rights=CJSON::decode($db->createCommand('SELECT access_rights FROM roles WHERE role_name=:name')->queryScalar(array(':name'=>$roleName)));
        $rights['report']=array('admin','view','create','update','delete','report');
        $rights['contact']=array_values(array_unique(array_merge($rights['contact'],array('create','update','import'))));
        $db->createCommand()->update('roles',array('access_rights'=>CJSON::encode($rights)),'role_name=:name',array(':name'=>$roleName));
    }
    $u=policyActor('own_sales','users'); $peer=policyActor('peer_sales','users');
    $manager=policyActor('mixed_manager',' ADMIN , users '); $reservation=policyActor('reservation_reader','reservation');
    $all=policyActor('all_scope',' SUPERADMIN ');
    $db->createCommand()->insert('roles',array('role_name'=>'exporter','access_rights'=>'{"contact":["report"]}', 'remark'=>'fixture','added_date'=>'2026-11-18 00:00:00','updated_date'=>'2026-11-18 00:00:00','updated_by'=>1));
    $exporter=policyActor('export_only','exporter'); $unknown=policyActor('unknown_role','missing_role');
    $h1=policyRecord('Hotel',array('code'=>'POL-H1','name'=>'Hotel A'));
    $h2=policyRecord('Hotel',array('code'=>'POL-H2','name'=>'Hotel B'));
    foreach (array(array($u->id,$h1->id,'own'),array($peer->id,$h1->id,'own'),array($peer->id,$h2->id,'own'),array($manager->id,$h1->id,'hotel'),array($manager->id,$h2->id,'own'),array($reservation->id,$h1->id,'hotel'),array($exporter->id,$h1->id,'hotel')) as $g) {
        policyRecord('UserHotelAccess',array('user_id'=>$g[0],'hotel_id'=>$g[1],'scope_level'=>$g[2]));
    }
    $a1=policyRecord('Account',array('code'=>'POL-A1','name'=>'Visible Own Customer'));
    $a2=policyRecord('Account',array('code'=>'POL-A2','name'=>'Peer Hotel A Customer'));
    $a3=policyRecord('Account',array('code'=>'POL-A3','name'=>'Hidden Hotel B Customer'));
    $shared=policyRecord('Account',array('code'=>'POL-S','name'=>'Shared Customer'));
    $a5=policyRecord('Account',array('code'=>'POL-A5','name'=>'Manager Own Hotel B'));
    foreach (array(array($a1,$h1,$u),array($a2,$h1,$peer),array($a3,$h2,$peer),array($shared,$h1,$u),array($shared,$h2,$peer),array($a5,$h2,$manager)) as $a) {
        policyRecord('AccountHotel',array('account_id'=>$a[0]->id,'hotel_id'=>$a[1]->id,'owner_user_id'=>$a[2]->id));
    }
    $person=policyRecord('ContactPerson',array('account_id'=>$a1->id,'first_name'=>'Visible Person','email'=>'visible@example.test'));
    $secret=policyRecord('ContactPerson',array('account_id'=>$a3->id,'first_name'=>'Hidden Person','email'=>'hidden@example.test'));
    $batch=policyRecord('CustomerImportBatch',array('source_type'=>'policy-fixture','source_key'=>'policy','mapping_version'=>'1'));
    $contacts=array();
    foreach (array($a1,$a2,$a3) as $a) {
        $row=$legacy['contact'][0]; unset($row['id']); $row['company']=$a->name; $row['managed_by']=(string)$u->id; $row['hotel_produced']='Hotel A';
        $db->createCommand()->insert('contact',$row); $id=$db->lastInsertID; $contacts[]=$id;
        policyRecord('LegacyLink',array('source_type'=>'contact','source_id'=>(string)$id,'account_id'=>$a->id,'resolution_state'=>'linked','import_batch_id'=>$batch->id));
    }
    $reports=array(); $template=$db->createCommand('SELECT * FROM reports ORDER BY id LIMIT 1')->queryRow();
    foreach (array(array($a1,$h1),array($a2,$h1),array($a3,$h2),array($shared,$h2)) as $pair) {
        $row=$template; unset($row['id']); $row['account_id']=$pair[0]->id; $row['hotel_id']=$pair[1]->id; $row['identity_state']='linked';
        $row['company_name']=$pair[0]->name; $row['report']='Description '.$pair[0]->name; $row['added_by']=$peer->id; // Visibility follows assignment, not creator.
        $row['added_date']=date('Y-m-d H:i:s'); $row['updated_date']=$row['added_date'];
        $db->createCommand()->insert('reports',$row); $reports[]=$db->lastInsertID;
    }
    $task=policyRecord('Task',array('code'=>'POL-T1','account_id'=>$a1->id,'hotel_id'=>$h1->id,'title'=>'Visible Task','assignee_user_id'=>$u->id,'due_mode'=>'date','due_date'=>'2026-11-20','client_request_key'=>'policy-task'));
    $reminder=policyRecord('TaskReminder',array('task_id'=>$task->id,'recipient_user_id'=>$u->id,'schedule_version'=>1,'reminder_kind'=>'due_day','notify_at_utc'=>'2026-11-20 02:00:00','state'=>'delivered','delivered_at_utc'=>gmdate('Y-m-d H:i:s')));
    policyRecord('Notification',array('reminder_id'=>$reminder->id,'recipient_user_id'=>$u->id,'task_id'=>$task->id,'schedule_version'=>1,'kind'=>'due_day'));

    PolicyUser::$current=$u->id;
    policyCheck(!ActionAccess::isAll(),'Stale superadmin session must not grant All.');
    policyCheck(ActionAccess::can('account','lookup') && !ActionAccess::can('contact','report'),'Sales actions are separate from export.');
    policyCheck(policyIds('Account','account')===array((int)$a1->id,(int)$shared->id),'Own scope must follow current Account owner.');
    policyCheck(policyIds('Report','report')===array((int)$reports[0]),'Shared account must not expose activity from another Hotel.');
    policyCheck(policyIds('Contact','contact')===array((int)$contacts[0]),'Legacy managed_by/Hotel strings must not override mapping.');
    policyCheck(strpos(policyRun('report','view',array('id'=>$reports[0])),'Visible Own Customer')!==false,'Own report view failed.');
    policyStatus(404,function()use($reports){policyRun('report','view',array('id'=>$reports[1]));},'Cross-owner report ID leaked.');
    policyStatus(404,function()use($reports){policyRun('report','update',array('id'=>$reports[2]),array('Report'=>array('report'=>'forged')),'POST');},'Cross-Hotel POST was allowed.');
    policyStatus(404,function()use($contacts){policyRun('contact','view',array('id'=>$contacts[2]));},'Mapped legacy Contact outside scope leaked.');
    policyStatus(404,function(){policyRun('contact','view',array('id'=>1));},'Unmapped Contact must be restricted to All.');
    $grid=policyRun('contact','admin',array(),array(),'GET',true,true);
    policyCheck(strpos($grid,'Visible Own Customer')!==false && strpos($grid,'Hidden Hotel B Customer')===false,'Contact AJAX list scope failed.');
    $distinct=ExtraHelper::getListDistinct('Report','company_name');
    policyCheck(isset($distinct[$a1->name]) && !isset($distinct[$a3->name]),'Distinct dropdown leaked hidden companies.');
    $lookup=CJSON::decode(policyRun('account','lookup',array('q'=>'Customer')));
    policyCheck(count($lookup)===2,'Account lookup must be scoped.');
    policyCheck(CJSON::decode(policyRun('account','lookup',array('q'=>'Hidden')))==array(),'Lookup exposed a hidden account.');
    policyCheck(CJSON::decode(policyRun('account','lookup',array('q'=>'Shared','hotel_id'=>$h2->id)))==array(),'Lookup must enforce the requested Hotel, not another Hotel of a shared Account.');
    policyStatus(404,function()use($a3){policyRun('account','contacts',array('id'=>$a3->id));},'Contact lookup outside Account scope leaked.');
    policyCheck(count(CJSON::decode(policyRun('account','contacts',array('id'=>$a1->id))))===1,'Own contact lookup failed.');
    policyStatus(403,function(){policyRun('contact','report');},'Sales export without explicit action was allowed.');
    policyStatus(403,function(){policyRun('report','report',array(),array('do_export'=>'yes'),'POST');},'Legacy Report export alias bypassed Contact export permission.');
    policyStatus(403,function(){policyRun('contact','import',array(),array('upload'=>'yes'),'POST');},'Legacy import without All was allowed.');
    policyStatus(403,function(){policyRun('report','create',array(),array('company'=>'Hidden Hotel B Customer'),'POST');},'Legacy company-copy write must not expose hidden data.');
    policyStatus(400,function()use($contacts){policyRun('contact','view',array('id'=>$contacts[0]),array(),'POST',false);},'Missing CSRF accepted.');
    policyCheck(count(Notification::model()->findAll(CustomerPolicy::notificationCriteria()))===1,'Own notification missing.');
    $db->createCommand()->update('crm_task',array('schedule_version'=>2),'id=:id',array(':id'=>$task->id));
    policyCheck(count(Notification::model()->findAll(CustomerPolicy::notificationCriteria()))===0,'Old notification version leaked.');
    $db->createCommand()->update('crm_task',array('schedule_version'=>1),'id=:id',array(':id'=>$task->id));
    $db->createCommand()->delete('crm_user_hotel_access','user_id=:id',array(':id'=>$u->id));
    policyCheck(policyIds('Account','account')===array() && count(Notification::model()->findAll(CustomerPolicy::notificationCriteria()))===0,'Revoked Hotel grant must take effect immediately.');
    policyRecord('UserHotelAccess',array('user_id'=>$u->id,'hotel_id'=>$h1->id,'scope_level'=>'own'));

    PolicyUser::$current=$manager->id;
    policyCheck(count(policyIds('Account','account'))===4,'Mixed role scope union incorrect.');
    policyCheck(count(policyIds('Report','report'))===2,'Hotel A manager must not see Hotel B reports.');
    CustomerPolicy::assertAccountHotel($a1->id,$h1->id,'account','assign'); policyCheck(true,'Hotel manager can assign in Hotel A.');
    policyStatus(403,function()use($a5,$h2){CustomerPolicy::assertAccountHotel($a5->id,$h2->id,'account','assign');},'Management action from Hotel A leaked into Own Hotel B.');
    policyStatus(403,function()use($shared){CustomerPolicy::assertRecord('account','account','archive',$shared);},'Cannot archive shared Account without all Hotel grants.');
    policyStatus(404,function()use($reports){policyRun('report','delete',array('id'=>$reports[2]),array('delete'=>'yes'),'POST');},'Cross-Hotel deletion leaked.');
    policyStatus(403,function()use($u){policyRun('users','hotelaccess',array('id'=>$u->id));},'Hotel manager may not grant themselves All/Hotel rights.');
    $options=Users::model()->getList(); policyCheck(!isset($options[$all->id]),'User dropdown leaked unassigned global account.');

    PolicyUser::$current=$reservation->id;
    policyCheck(ActionAccess::can('account','lookup') && !ActionAccess::can('task','admin'),'Reservation is customer-read-only.');
    policyStatus(403,function()use($reports){policyRun('report','view',array('id'=>$reports[0]));},'Reservation acquired Sales Report action.');
    policyStatus(403,function()use($contacts){policyRun('contact','update',array('id'=>$contacts[0]),array('Contact'=>array('company'=>'bad')),'POST');},'Read-only Contact update accepted.');
    PolicyUser::$current=$exporter->id;
    $exportForm=policyRun('contact','report');
    policyCheck(strpos($exportForm,'name="'.Yii::app()->request->csrfTokenName.'"')!==false,'Export form must include a CSRF field.');
    $xlsx=policyRun('contact','report',array(),array('do_export'=>'yes'),'POST');
    $tmp=tempnam(sys_get_temp_dir(),'scope-xlsx-'); file_put_contents($tmp,$xlsx); $zip=new ZipArchive;
    policyCheck($zip->open($tmp)===true,'Scoped XLSX invalid.'); $xml=$zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close(); unlink($tmp);
    policyCheck(strpos($xml,'Visible Own Customer')!==false && strpos($xml,'Peer Hotel A Customer')!==false && strpos($xml,'Hidden Hotel B Customer')===false && strpos($xml,'Synthetic legacy company')===false,'Export scope differs from read scope.');
    PolicyUser::$current=$unknown->id;
    policyStatus(403,function(){policyRun('contact','admin');},'Empty action allow-list became allow-all.');
    PolicyUser::$current=$all->id;
    policyCheck(ActionAccess::isAll() && count(policyIds('Report','report'))===5,'Explicit All role failed without Hotel grants.');
    policyStatus(409,function()use($u){policyRun('users','update',array('id'=>$u->id),array('Users'=>array('status'=>'I')),'POST');},'Cannot deactivate sales with owned accounts/open tasks.');
    policyStatus(409,function()use($u){policyRun('users','delete',array('id'=>$u->id),array('delete'=>'yes'),'POST');},'Cannot delete referenced sales.');
    require_once __DIR__.'/../migrations/mvp/m261118_180000_seed_mvp_actions.php';
    $seed=new m261118_180000_seed_mvp_actions;
    try { $seed->down(); throw new Exception('Changed role rights were overwritten by rollback.'); }
    catch (CDbException $e) { policyCheck(strpos($e->getMessage(),'rights changed')!==false,'Role seed rollback must preserve later edits.'); }
    policyStatus(409,function()use($contacts){policyRun('contact','update',array('id'=>$contacts[0]),array('Contact'=>array('company'=>'diverge')),'POST');},'Mapped Contact legacy editor must be blocked even for All.');
    policyStatus(409,function()use($reports){policyRun('report','update',array('id'=>$reports[0]),array('Report'=>array('report'=>'diverge')),'POST');},'Linked Report legacy editor must be blocked even for All.');
    policyStatus(400,function()use($contacts){policyRun('contact','delete',array('id'=>$contacts[0]));},'GET deletion accepted.');
    policyStatus(400,function()use($u){policyRun('users','hotelaccess',array('id'=>$u->id),array('grants'=>array(),'reason'=>'test'),'POST',false);},'Grant update missing CSRF accepted.');
    policyStatus(409,function()use($u){policyRun('users','hotelaccess',array('id'=>$u->id),array('grants'=>array(),'reason'=>'revoke'),'POST');},'Grant revoke with owned accounts must require handover.');
    policyStatus(400,function()use($unknown,$h1){policyRun('users','hotelaccess',array('id'=>$unknown->id),array('grants'=>array(array('hotel_id'=>$h1->id,'scope_level'=>'all')),'reason'=>'forged'),'POST');},'All scope forged in Hotel grant.');
    $granted=CJSON::decode(policyRun('users','hotelaccess',array('id'=>$unknown->id),array('grants'=>array(array('hotel_id'=>$h1->id,'scope_level'=>'own')),'reason'=>'pilot'),'POST'));
    policyCheck(count($granted)===1 && $granted[0]['scope_level']==='own','All administrator grant update failed.');
    policyCheck(AuditEvent::model()->count("entity_type='user_hotel_access'")>0,'Grant mutation must be audited.');
    PolicyUser::$current=$u->id;
    $db->createCommand()->update('users',array('status'=>'I'),'id=:id',array(':id'=>$u->id));
    policyCheck(!ActionAccess::can('contact','admin') && policyIds('Account','account')===array(),'Inactive session kept data rights.');
    policyStatus(403,function(){policyRun('site','admin');},'Inactive session reached dashboard.');
    policyStatus(403,function(){policyRun('contact','admin');},'Inactive session reached legacy list.');
    $db->createCommand()->update('users',array('status'=>'A','roles'=>'missing_role'),'id=:id',array(':id'=>$u->id));
    policyCheck(!ActionAccess::can('account','lookup'),'Role removal must take effect without re-login.');
    PolicyUser::$current=null;
    policyCheck(policyIds('Account','account')===array(),'Guest scope leaked.');
} finally {
    if ($tx->active) { $tx->rollback(); }
    foreach (array('contact','roles','users') as $table) { $db->createCommand('DROP TEMPORARY TABLE IF EXISTS '.$table)->execute(); }
}
ob_end_clean();
echo "PASS: $count policy assertions; Own/Hotel/All, mixed roles, legacy URLs, lookups/export, grant audit/revocation, CSRF and stale sessions.\n";
