<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
define('YII_ENABLE_EXCEPTION_HANDLER', false);
define('YII_ENABLE_ERROR_HANDLER', false);
define('IS_IN_TESTS', true);
$root = dirname(__FILE__).'/../..';
require_once (getenv('YII_FRAMEWORK_PATH') ?: $root.'/../../yii/yii.php');
class ManagementSmokeUser extends CWebUser
{
    public static $currentId = '1';
    public static $states = array();
    public function init() {}
    public function getId() { return self::$currentId; }
    public function getIsGuest() { return self::$currentId === null; }
    public function getState($key, $defaultValue = null) { return isset(self::$states[$key]) ? self::$states[$key] : $defaultValue; }
    public function setState($key, $value, $defaultValue = null) { self::$states[$key] = $value; }
    public function getReturnUrl($defaultUrl = null) { return $defaultUrl; }
    public function login($identity, $duration = 0) {
        self::$currentId = (string)$identity->id;
        self::$states = $identity->getPersistentStates();
        return true;
    }
    public function logout($destroySession = true) { self::$currentId = null; self::$states = array(); }
}
class ManagementSmokeRequest extends CHttpRequest
{
    public $method = 'GET';
    public $ajax = false;
    public function getRequestType() { return $this->method; }
    public function getIsPostRequest() { return $this->method === 'POST'; }
    public function getIsAjaxRequest() { return $this->ajax; }
    public function getUserHostAddress() { return '127.0.0.1'; }
}
class ManagementRedirect extends Exception {}
trait CaptureManagementRedirect
{
    public function redirect($url, $terminate = true, $statusCode = 302) { throw new ManagementRedirect; }
}
$_SERVER['SCRIPT_FILENAME'] = realpath($root.'/index.php');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'crm.hthub.local';
$_SERVER['REQUEST_URI'] = '/';
$config = require $root.'/protected/config/test.php';
$config['params']['mvp']['customerWrite']=false; // Exercise pre-cutover legacy compatibility.
$config['components']['user'] = array('class'=>'ManagementSmokeUser');
$config['components']['request'] = array('class'=>'ManagementSmokeRequest','enableCsrfValidation'=>true);
$config['components']['log']['routes'] = array();
$app = Yii::createWebApplication($config);
Yii::import('application.controllers.*');
class TestContactController extends ContactController { use CaptureManagementRedirect; }
class TestUsersController extends UsersController { use CaptureManagementRedirect; }
class TestSiteController extends SiteController { use CaptureManagementRedirect; }

function managementCheck($condition, $message) { if (!$condition) { throw new Exception($message); } }
function managementRun($class, $id, $action, $get = array(), $post = array(), $ajax = false, $method = null)
{
    $_GET = $get; $_POST = $post;
    if ($post || $method === 'POST') { $_POST[Yii::app()->request->csrfTokenName] = Yii::app()->request->csrfToken; }
    Yii::app()->request->method = $method ?: ($post ? 'POST' : 'GET');
    Yii::app()->request->ajax = $ajax;
    $controller = new $class($id);
    Yii::app()->controller = $controller;
    $controller->init();
    ob_start();
    try {
        $controller->run($action);
        $html = ob_get_contents();
    } catch (ManagementRedirect $e) {
        $html = '';
    } finally { ob_end_clean(); }
    return $html;
}
$tables = array('contact', 'users', 'roles', 'user_history', 'trackings');
require_once __DIR__.'/support/TemporaryTableFixture.php';
try {
    foreach ($tables as $table) {
        $row = $app->db->createCommand('SHOW CREATE TABLE '.$app->db->quoteTableName($table))->queryRow();
        $ddl = isolatedTemporaryTableDdl($row['Create Table']);
        $app->db->createCommand($ddl)->execute();
    }
    require_once $root.'/protected/migrations/m261118_090000_restore_optional_dates.php';
    $migration = new m261118_090000_restore_optional_dates;
    $migration->temporaryTablesOnly = true;
    $migration->up();
    $migration->up(); // Safe to rerun on an already upgraded schema.
    $admin = new Users('create');
    $admin->attributes = array('username'=>'testadmin','fullname'=>'Test Administrator','password'=>'test-password','roles'=>'admin');
    managementCheck($admin->save(), 'Minimal user creation failed: '.json_encode($admin->getErrors()));
    $role = new Role;
    $role->role_name = 'viewer';
    $role->access_rights = '{"contact":["admin","view"],"users":["admin","view","update"]}';
    managementCheck($role->save(), 'Role fixture failed.');
    $contact = array('company'=>'Test Contact', 'first_name'=>'Test', 'hotel_produced'=>array('H1','H2'), 'market'=>array('VN','US'), 'validity_from'=>'18-Nov-2026', 'validity_to'=>'');
    managementRun('TestContactController', 'contact', 'create', array(), array('Contact'=>$contact));
    $saved = Contact::model()->findByPk(1);
    managementCheck($saved && $saved->hotel_produced === 'H1,H2' && $saved->market === 'VN,US', 'Contact creation/multi-select failed.');
    managementCheck($saved->validity_from === '2026-11-18' && $saved->validity_to === null, 'Contact date conversion failed.');
    foreach (array('admin','create','view','update','import','report') as $action) {
        $html = managementRun('TestContactController','contact',$action,array('id'=>1));
        managementCheck(strlen($html)>100 && strpos($html,'<html')!==false, 'Contact '.$action.' failed to render.');
    }
    $changed = array('company'=>'Changed Contact','hotel_produced'=>array('H2'), 'validity_from'=>'18-Nov-2026');
    managementRun('TestContactController','contact','update',array('id'=>1),array('Contact'=>$changed));
    $saved = Contact::model()->findByPk(1);
    managementCheck($saved->company==='Changed Contact' && $saved->market==='' && $saved->hotel_produced==='H2', 'Contact edit/cleared selection failed.');
    $invalid = managementRun('TestContactController','contact','create',array(),array('Contact'=>array('company'=>'','validity_from'=>'31-Feb-2026')));
    managementCheck(strpos($invalid,'errorSummary')!==false && Contact::model()->count()==1, 'Invalid contact must not save.');
    $json = managementRun('TestContactController','contact','admin',array('Contact'=>array('company'=>'Changed')),array(),true);
    managementCheck(is_array(json_decode($json,true)), 'Contact AJAX grid must return valid JSON.');

    $stream = fopen($root.'/template/template.csv','rb');
    $result = ContactCsv::importStream($stream); fclose($stream);
    managementCheck($result['imported']===1 && !$result['errors'], 'Existing CSV template import failed: '.json_encode($result));
    $stream = fopen('php://temp','w+');
    fwrite($stream,"\xEF\xBB\xBFCompany,Validity From\r\n\"Công ty, Việt Nam\",18-Nov-2026\r\nBad date,31-Feb-2026\r\n"); rewind($stream);
    $result = ContactCsv::importStream($stream); fclose($stream);
    managementCheck($result['imported']===1 && count($result['errors'])===1, 'UTF-8/quoted fields/per-row CSV errors failed.');
    $upload = tempnam(sys_get_temp_dir(),'crm-import-');
    file_put_contents($upload,"Company,Email\nUpload Contact,upload@example.test\n");
    $_FILES = array('Contact'=>array('name'=>array('attachment'=>'contacts.csv'),'type'=>array('attachment'=>'text/csv'),
        'tmp_name'=>array('attachment'=>$upload),'error'=>array('attachment'=>UPLOAD_ERR_OK),'size'=>array('attachment'=>filesize($upload))));
    CUploadedFile::reset();
    $html = managementRun('TestContactController','contact','import',array(),array('upload'=>'yes'));
    managementCheck(strpos($html,'Imported 1 contact(s)')!==false, 'CSV upload action failed.');
    unset($_FILES); CUploadedFile::reset(); unlink($upload);
    $xlsx = managementRun('TestContactController','contact','report',array(),array('do_export'=>'yes'));
    managementCheck(substr($xlsx,0,2)==='PK', 'Excel export is not an XLSX archive.');
    $path = tempnam(sys_get_temp_dir(),'crm-export-'); file_put_contents($path,$xlsx);
    $zip = new ZipArchive; managementCheck($zip->open($path)===true, 'XLSX archive cannot be opened.');
    $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
    $zip->close(); unlink($path);
    $sheet->registerXPathNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    managementCheck(count($sheet->xpath('//s:row')) == Contact::model()->count()+1, 'Excel export omitted records.');
    $cells = $sheet->xpath('//s:c[@r="A2"]/s:is/s:t');
    managementCheck((string)$cells[0]==='Changed Contact', 'Excel export content failed.');

    $userData = array('username'=>'newuser','fullname'=>'New User','password'=>str_repeat('a',40),'status'=>'A','roles'=>'viewer');
    managementRun('TestUsersController','users','create',array(),array('Users'=>$userData));
    $user = Users::model()->getByUsername('newuser');
    managementCheck($user && $user->last_login_date===null && $user->password===sha1(md5(str_repeat('a',40))), 'Create user/hash/optional last login failed.');
    foreach (array('admin','create','view','update','history') as $action) {
        $html = managementRun('TestUsersController','users',$action,array('id'=>$user->id));
        managementCheck(strlen($html)>100 && strpos($html,'<html')!==false, 'Users '.$action.' failed to render.');
        managementCheck(strpos($html,$user->password)===false, 'User page exposed a password hash.');
    }
    managementRun('TestUsersController','users','update',array('id'=>$user->id),array('Users'=>array('fullname'=>'Updated User','password'=>'injected','new_password'=>'')));
    $user->refresh(); managementCheck($user->fullname==='Updated User' && $user->password===sha1(md5(str_repeat('a',40))), 'User edit must preserve an unchanged password.');
    managementRun('TestUsersController','users','update',array('id'=>$user->id),array('Users'=>array('new_password'=>'updated-password')));
    $user->refresh(); managementCheck($user->password===sha1(md5('updated-password')), 'Password reset failed.');
    $beforeCount = Users::model()->count();
    $html = managementRun('TestUsersController','users','create',array(),array('Users'=>$userData));
    managementCheck(Users::model()->count()==$beforeCount && strpos($html,'errorSummary')!==false, 'Duplicate username must be rejected.');
    $json = managementRun('TestUsersController','users','admin',array(),array(),true);
    managementCheck(is_array(json_decode($json,true)), 'Users AJAX grid must return valid JSON.');

    ManagementSmokeUser::$currentId = (string)$user->id;
    ManagementSmokeUser::$states = array('type'=>'viewer');
    try { managementRun('TestContactController','contact','delete',array('id'=>1),array('delete'=>'yes')); throw new Exception('Missing permission check.'); }
    catch (CHttpException $e) { managementCheck($e->statusCode===403,'Wrong permission response.'); }
    managementRun('TestUsersController','users','update',array('id'=>$user->id),array('Users'=>array('roles'=>'admin','fullname'=>'No Escalation')));
    $user->refresh(); managementCheck($user->roles==='viewer','A non-admin escalated account privileges.');
    ManagementSmokeUser::$currentId = '1'; ManagementSmokeUser::$states = array();
    try { managementRun('TestContactController','contact','delete',array('id'=>1)); throw new Exception('GET deletion was allowed.'); }
    catch (CHttpException $e) { managementCheck($e->statusCode===400,'Wrong GET deletion response.'); }
    try { managementRun('TestUsersController','users','delete',array('id'=>1),array('delete'=>'yes')); throw new Exception('Admin deletion was allowed.'); }
    catch (CHttpException $e) { managementCheck($e->statusCode===403,'Wrong protected account response.'); }
    try { managementRun('TestContactController','contact','view',array('id'=>999999)); throw new Exception('Missing record was allowed.'); }
    catch (CHttpException $e) { managementCheck($e->statusCode===404,'Wrong missing record response.'); }

    ManagementSmokeUser::$currentId = null;
    managementRun('TestSiteController','site','login',array(),array('LoginForm'=>array('username'=>'newuser','password'=>'updated-password')));
    $history = UserHistory::model()->findByAttributes(array('user_id'=>$user->id));
    managementCheck($history && $history->logout_time===null, 'Login history was not recorded.');
    $user->refresh(); managementCheck($user->last_login_date!==null, 'Last login timestamp was not updated.');
    $html = managementRun('TestSiteController','site','changepassword',array('id'=>1),array('Users'=>array('old_password'=>'wrong','new_password'=>'changed-password','confirm_new_password'=>'changed-password')));
    $user->refresh(); managementCheck($user->password===sha1(md5('updated-password')), 'Invalid old password was accepted.');
    managementRun('TestSiteController','site','changepassword',array('id'=>1),array('Users'=>array('old_password'=>'updated-password','new_password'=>'changed-password','confirm_new_password'=>'changed-password')));
    $user->refresh(); $admin->refresh();
    managementCheck($user->password===sha1(md5('changed-password')) && $admin->password===sha1(md5('test-password')), 'Password change must only affect the session account.');
    managementRun('TestSiteController','site','logout',array(),array('logout'=>'yes'));
    $history->refresh(); managementCheck($history->logout_time!==null,'Logout history was not recorded.');
    ManagementSmokeUser::$currentId='1'; ManagementSmokeUser::$states=array();
    $json = managementRun('TestUsersController','users','history',array(),array(),true);
    managementCheck(is_array(json_decode($json,true)), 'History AJAX grid must return valid JSON.');
    managementRun('TestContactController','contact','delete',array('id'=>1),array('delete'=>'yes'),true);
    managementCheck(Contact::model()->findByPk(1)===null,'Contact delete failed.');
    managementRun('TestUsersController','users','delete',array('id'=>$user->id),array('delete'=>'yes'),true);
    managementCheck(Users::model()->findByPk($user->id)===null,'User delete failed.');
    $html = managementRun('TestUsersController','users','history');
    managementCheck(strpos($html,'(deleted user)')!==false,'History must survive deleted users.');
    ob_end_clean();
    echo "PASS: Contact CRUD/pages/AJAX, date and multi-select forms, CSV template/upload/errors, complete XLSX export.\n";
    echo "PASS: Users CRUD/pages/AJAX, hashing/reset/change password, permissions, POST-only deletion, login/logout history.\n";
    echo "PASS: optional-date migration is idempotent; all writes stayed in temporary tables.\n";
} finally {
    foreach ($tables as $table) {
        $app->db->createCommand('DROP TEMPORARY TABLE IF EXISTS '.$app->db->quoteTableName($table))->execute();
    }
}
