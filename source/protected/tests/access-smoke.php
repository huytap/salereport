<?php
// Run with PHP 7.4: php source/protected/tests/access-smoke.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__FILE__).'/../..';
$yii = getenv('YII_FRAMEWORK_PATH') ?: $root.'/../../yii/yii.php';
require_once $yii;

// Exercise authenticated rendering without a browser session or database writes.
class AccessSmokeUser extends CWebUser
{
    public function init() {}
    public function getId() { return '1'; }
    public function getIsGuest() { return false; }
    public function getState($key, $defaultValue = null) { return $defaultValue; }
}

$_SERVER['SCRIPT_FILENAME'] = realpath($root.'/index.php');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'crm.hthub.local';
$_SERVER['REQUEST_URI'] = '/site/admin';
$_SERVER['REQUEST_METHOD'] = 'GET';
$config = require $root.'/protected/config/test.php';
$config['components']['user'] = array('class' => 'AccessSmokeUser');
$config['components']['log']['routes'] = array();
$app = Yii::createWebApplication($config);

function checkAccess($condition, $message)
{
    if (!$condition) {
        throw new Exception($message);
    }
}

$form = new LoginForm;
checkAccess(!$form->validate(), 'Blank credentials must be rejected.');
checkAccess($form->hasErrors('username') && $form->hasErrors('password'), 'Both fields are required.');

$identity = new UserIdentityAm("' OR 1=1 -- ", 'invalid-access-smoke-password');
checkAccess(!$identity->authenticate(), 'SQL syntax in a username must not authenticate.');
checkAccess($identity->getId() === null, 'Failed authentication must not assign an identity.');

ob_start();
$app->runController('site/admin');
$html = ob_get_clean();
checkAccess(strpos($html, 'Welcome to Ho Tram Resort') !== false, 'Dashboard must render.');
checkAccess(strpos($html, 'class="huge"') !== false, 'Dashboard counts must render.');
echo "PASS: required credentials, parameterized authentication, database reads and authenticated dashboard.\n";
