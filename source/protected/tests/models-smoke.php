<?php
/** PHP 7.4 integration checks. Writes are confined to connection-local TEMPORARY tables. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__FILE__).'/../..';
define('YII_ENABLE_EXCEPTION_HANDLER', false);
define('YII_ENABLE_ERROR_HANDLER', false);
define('IS_IN_TESTS', true);
require_once (getenv('YII_FRAMEWORK_PATH') ?: $root.'/../../yii/yii.php');
class ModelSmokeUser extends CWebUser
{
    public function init() {}
    public function getId() { return '1'; }
    public function getIsGuest() { return false; }
    public function getState($key, $defaultValue = null) { return $defaultValue; }
}
$_SERVER['SCRIPT_FILENAME'] = realpath($root.'/index.php');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'crm.hthub.local';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$config = require $root.'/protected/config/main.php';
$config['components']['user'] = array('class' => 'ModelSmokeUser');
$config['components']['log']['routes'] = array();
$app = Yii::createWebApplication($config);
function modelCheck($condition, $message)
{
    if (!$condition) {
        throw new Exception($message);
    }
}
$models = array('Users', 'Role', 'Contact', 'Report', 'StaffAsst', 'Trackings', 'UserHistory', 'Incident');
$updateFields = array('Users'=>'fullname', 'Role'=>'remark', 'Contact'=>'company',
    'Report'=>'company_name', 'StaffAsst'=>'comment', 'Trackings'=>'object_name',
    'UserHistory'=>'ip_address', 'Incident'=>'guest_name');
$ddl = array();
foreach ($models as $class) {
    $model = new $class('search');
    $model->unsetAttributes();
    $provider = $model->search();
    $provider->getTotalItemCount();
    $provider->getData();
    $table = $model->tableName();
    $row = $app->db->createCommand('SHOW CREATE TABLE '.$app->db->quoteTableName($table))->queryRow();
    $ddl[$table] = preg_replace('/AUTO_INCREMENT=\d+\b/', 'AUTO_INCREMENT=1',
        preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $row['Create Table']));
}
echo "PASS: all 8 model mappings, live schema, search and pagination.\n";
foreach (array('report/admin', 'users/admin', 'users/history') as $route) {
    ob_start();
    try {
        $app->runController($route);
        $html = ob_get_contents();
    } finally {
        ob_end_clean();
    }
    modelCheck(strpos($html, 'table') !== false, $route.' must render its model grid.');
}
echo "PASS: existing report, users and login history pages render with restored models.\n";

try {
    foreach ($ddl as $sql) {
        $app->db->createCommand($sql)->execute();
    }
    $records = array();
    foreach ($models as $class) {
        $model = new $class;
        foreach ($model->getTableSchema()->columns as $name => $column) {
            if ($column->autoIncrement || $column->defaultValue !== null) {
                continue;
            }
            if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/i', $column->dbType)) {
                $model->$name = 1;
            } elseif ($column->dbType === 'date') {
                $model->$name = '2026-11-18';
            } elseif ($column->dbType === 'datetime') {
                $model->$name = '2026-11-18 10:00:00';
            } else {
                $model->$name = 'fixture';
            }
        }
        if ($class === 'Users') {
            $model->username = 'fixture_user';
            $model->password = 'fixture-password';
            $model->email = 'fixture@example.test';
            $model->roles = array('fixture_role');
        } elseif ($class === 'Role') {
            $model->role_name = 'fixture_role';
            $model->access_rights = '{"report":["admin","view"]}';
        } elseif ($class === 'Report') {
            $model->hotels = array('a', 'b');
        }
        modelCheck($model->save(), $class.' insert failed: '.json_encode($model->getErrors()));
        $records[$class] = $model;
        $loaded = $class::model()->findByPk($model->primaryKey);
        modelCheck($loaded !== null, $class.' read after insert failed.');
        $field = $updateFields[$class];
        $loaded->$field = 'updated';
        modelCheck($loaded->save(), $class.' update failed: '.json_encode($loaded->getErrors()));
        modelCheck($class::model()->findByPk($model->primaryKey)->$field === 'updated', $class.' update was not persisted.');
        $records[$class] = $loaded;
    }
    modelCheck($records['StaffAsst']->user->id == $records['Users']->id, 'Staff user relation failed.');
    modelCheck($records['UserHistory']->user->id == $records['Users']->id, 'History user relation failed.');
    modelCheck($records['Report']->user->id == $records['Users']->id, 'Report user relation failed.');
    modelCheck($records['Trackings']->user->id == $records['Users']->id, 'Tracking user relation failed.');
    modelCheck(isset(Users::model()->getList()[1]), 'User options failed.');
    modelCheck(Users::model()->getByUsername('fixture_user')->id == 1, 'Username lookup failed.');
    modelCheck(Users::model()->checkByEmail('fixture@example.test'), 'Email lookup failed.');
    modelCheck(Users::model()->getByUsername("' OR 1=1 -- ") === null, 'Unsafe username lookup.');
    modelCheck(Role::model()->getNameUser(1) === 'fixture_role', 'User roles lookup failed.');
    modelCheck(Role::model()->getNameRoleById('fixture_role')['report'] === array('admin', 'view'), 'Role actions failed.');
    modelCheck(isset(Role::model()->getAllRoleName()['fixture_role']), 'Role list failed.');
    modelCheck(Contact::model()->getList()->pagination === false, 'Contact export must not be paginated.');
    $copy = Report::model()->getByCompany('updated');
    modelCheck($copy->isNewRecord && $copy->id === null && $copy->company_name === 'updated', 'Company copy must create a new report.');
    modelCheck(Report::model()->getByCompany('unknown')->company_name === 'unknown', 'Unknown company must not cause a null dereference.');

    $user = Users::model()->findByPk(1);
    modelCheck($user->password === sha1(md5('fixture-password')), 'Password hashing failed.');
    $user->new_password = 'replacement-password';
    modelCheck($user->save(), 'Password update failed.');
    modelCheck(Users::model()->findByPk(1)->password === sha1(md5('replacement-password')), 'New password did not persist.');
    $identity = new UserIdentityAm('fixture_user', 'replacement-password');
    modelCheck($identity->authenticate() && $identity->getId() == 1, 'Restored models must authenticate.');
    $user->status = 'I';
    modelCheck($user->save(), 'Status update failed.');
    modelCheck(!(new UserIdentityAm('fixture_user', 'replacement-password'))->authenticate(), 'Inactive user must not authenticate.');

    $invalid = new Contact;
    $invalid->company = str_repeat('x', 256);
    modelCheck(!$invalid->validate() && $invalid->hasErrors('company'), 'Column length validation failed.');
    $invalid->company = array('nested' => array('bad'));
    modelCheck(!$invalid->validate() && $invalid->hasErrors('company'), 'Array input must be rejected.');
    $report = Report::model()->findByPk(1);
    $report->ent = '';
    $report->highlight = '';
    modelCheck($report->save() && $report->ent === null && $report->highlight === 0, 'Optional form values must normalize for strict SQL.');
    $report->highlight = 2147483648;
    modelCheck(!$report->validate() && $report->hasErrors('highlight'), 'Integer overflow must fail validation.');
    $invalidRole = new Role;
    $invalidRole->role_name = 'invalid';
    $invalidRole->access_rights = '{broken';
    modelCheck(!$invalidRole->validate() && $invalidRole->hasErrors('access_rights'), 'Role JSON validation failed.');
    $search = new Report('search');
    $search->unsetAttributes();
    $search->attributes = array('company_name' => 'updated', 'added_by' => 1);
    modelCheck((int)$search->search()->totalItemCount === 1, 'Search filters failed.');
    $_GET['filter'] = array('from_date' => '2026-11-19');
    modelCheck((int)$search->search()->totalItemCount === 0, 'Date filter failed.');
    unset($_GET['filter']);
    $search->date = '18-Nov-2026';
    modelCheck((int)$search->search()->totalItemCount === 1, 'Grid date picker filter failed.');
    $search->date = '31-Feb-2026';
    modelCheck((int)$search->search()->totalItemCount === 0, 'Invalid filter dates must not match rows.');

    // Reproduce old zero dates only in the temporary table, then restore strict mode.
    $sqlMode = $app->db->createCommand('SELECT @@SESSION.sql_mode')->queryScalar();
    try {
        $app->db->createCommand('SET SESSION sql_mode = :mode')->execute(array(':mode' => ''));
        $app->db->createCommand("UPDATE reports SET sales = '0000-00-00' WHERE id = 1")->execute();
    } finally {
        $app->db->createCommand('SET SESSION sql_mode = :mode')->execute(array(':mode' => $sqlMode));
    }
    $legacy = Report::model()->findByPk(1);
    $legacy->comments = 'Updated under strict SQL';
    modelCheck($legacy->save(), 'Updating a legacy record failed: '.json_encode($legacy->getErrors()));
    modelCheck(Report::model()->findByPk(1)->sales === '0000-00-00', 'An unchanged legacy date must be preserved.');
    $unsafe = new Report;
    $unsafe->attributes = array('id' => 999, 'added_by' => 999, 'company_name' => 'safe');
    modelCheck($unsafe->id === null && $unsafe->added_by === null && $unsafe->company_name === 'safe', 'Audit fields must not be mass assignable.');
    $form = new ContactForm;
    $form->attributes = array('name'=>'Test', 'email'=>'invalid', 'subject'=>'Test', 'body'=>'Test');
    modelCheck(!$form->validate() && $form->hasErrors('email'), 'Contact email validation failed.');
    foreach (array_reverse($models) as $class) {
        modelCheck($records[$class]->delete(), $class.' delete failed.');
        modelCheck($class::model()->count() == 0, $class.' delete did not persist.');
    }
    echo "PASS: isolated CRUD for 8 tables, relations, helpers, authentication, validation, filters and protected audit fields.\n";
} finally {
    foreach (array_keys($ddl) as $table) {
        // DROP TEMPORARY can never drop the real application table, even after partial setup.
        $app->db->createCommand('DROP TEMPORARY TABLE IF EXISTS '.$app->db->quoteTableName($table))->execute();
    }
}
