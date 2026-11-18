<?php

// Creates synthetic legacy fixtures ONLY in an empty, explicitly named test schema.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('YII_ENABLE_EXCEPTION_HANDLER', false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: dirname(__FILE__).'/../../../../yii/yii.php');
$app = Yii::createConsoleApplication(require dirname(__FILE__).'/../config/console.php');
$db = $app->db;
$db->setActive(true);
$name = $db->createCommand('SELECT DATABASE()')->queryScalar();
$tables = $db->schema->getTableNames();
if ($tables) {
    if (in_array('mvp_test_fixture', $tables, true) && $db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar() === 'wp01-v1') {
        echo "Isolated synthetic fixture already exists: $name\n";
        exit(0);
    }
    throw new CDbException('Fixture preparation requires an empty test schema; no existing tables were changed.');
}
$dump = file_get_contents(dirname(__FILE__).'/../../_db/hotramre_app.sql');
foreach (array('users','roles','contact','reports','incident','staff_asst','trackings','user_history') as $table) {
    if (!preg_match('/CREATE TABLE `'.preg_quote($table, '/').'` \(.*?;/s', $dump, $m)) {
        throw new CException('Missing legacy schema definition: '.$table);
    }
    $db->createCommand($m[0])->execute();
    // Schema only: never execute INSERTs from the data dump.
    if (preg_match_all('/ALTER TABLE `'.preg_quote($table, '/').'`\s+.*?;/s', $dump, $alters)) {
        foreach ($alters[0] as $sql) {
            $sql = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $sql);
            $db->createCommand($sql)->execute();
        }
    }
}
$db->schema->refresh();
foreach (array('users','contact','reports') as $table) {
    $values = array();
    foreach ($db->schema->getTable($table)->columns as $column => $definition) {
        if ($definition->autoIncrement || $definition->allowNull || $definition->defaultValue !== null) { continue; }
        if (preg_match('/^int/', $definition->dbType)) { $values[$column] = 1; }
        elseif ($definition->dbType === 'date') { $values[$column] = '2026-11-18'; }
        elseif ($definition->dbType === 'datetime') { $values[$column] = '2026-11-18 09:00:00'; }
        else { $values[$column] = ''; }
    }
    if ($table === 'users') {
        $values = array_merge($values, array('username'=>'fixture_admin','fullname'=>'Synthetic Test Administrator',
            'password'=>sha1(md5('synthetic-test-only')), 'status'=>'A', 'roles'=>'superadmin'));
    } elseif ($table === 'contact') { $values['company'] = 'Synthetic legacy company'; }
    else { $values['company_name'] = 'Synthetic legacy report'; $values['date'] = '2026-11-18'; }
    $db->createCommand()->insert($table, $values);
}
$db->createCommand()->insert('roles', array('role_name'=>'superadmin','access_rights'=>'{}','remark'=>'Synthetic test role',
    'added_date'=>'2026-11-18 09:00:00','updated_date'=>'2026-11-18 09:00:00','updated_by'=>1));
$db->createCommand('CREATE TABLE mvp_test_fixture (version VARCHAR(32) NOT NULL PRIMARY KEY) ENGINE=InnoDB')->execute();
$db->createCommand()->insert('mvp_test_fixture', array('version'=>'wp01-v1'));
echo "Prepared schema-only legacy fixtures with synthetic data: $name\n";
