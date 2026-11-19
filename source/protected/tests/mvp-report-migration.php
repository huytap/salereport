<?php
/** Round trip only on the disposable restore schema, with an owned zero-date legacy row. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
$app=Yii::createConsoleApplication(require __DIR__.'/../config/console.php'); $db=$app->db; $db->setActive(true);
if ($db->createCommand('SELECT DATABASE()')->queryScalar()!=='sale_report_mvp_test_restore' || $db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Only synthetic sale_report_mvp_test_restore is allowed.'); }
$checks=0; $zeroId=null; $version='m261119_110000_prepare_report_workflows';
function rmCheck($ok,$message) { global $checks; if (!$ok) { throw new Exception($message); } $checks++; }
function rmMigrate($direction) {
    $args=array('yiic','migrate',$direction); if ($direction==='down') { $args[]='1'; } $args[]='--interactive=0'; ob_start();
    try { $code=Yii::app()->commandRunner->run($args); $output=ob_get_contents(); } finally { ob_end_clean(); }
    if ($code!==0 && $code!==null) { throw new Exception('Migration failed: '.$output); }
}
function rmColumns() { return Yii::app()->db->createCommand("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' ORDER BY ORDINAL_POSITION")->queryAll(); }
$last=$db->createCommand('SELECT version FROM crm_migration ORDER BY apply_time DESC,version DESC LIMIT 1')->queryScalar();
if ($last===$version) { rmMigrate('down'); }
elseif ($last!=='m261119_090000_prepare_task_workflows') { throw new Exception('Apply exactly WP00–WP05 before this test.'); }
$mode=$db->createCommand('SELECT @@SESSION.sql_mode')->queryScalar();
try {
    $zero=$db->createCommand('SELECT * FROM reports ORDER BY id LIMIT 1')->queryRow(); unset($zero['id']); $zero['company_name']='WP06 migration zero legacy'; $zero['ent']=$zero['sales']=$zero['site_ins']='0000-00-00'; $zero['client_request_key']=null;
    try { $db->createCommand('SET SESSION sql_mode=:mode')->execute(array(':mode'=>implode(',',array_diff(explode(',',$mode),array('NO_ZERO_DATE','NO_ZERO_IN_DATE'))))); $db->createCommand()->insert('reports',$zero); $zeroId=$db->lastInsertID; }
    finally { $db->createCommand('SET SESSION sql_mode=:mode')->execute(array(':mode'=>$mode)); }
    $before=$db->createCommand('SELECT * FROM reports ORDER BY id')->queryAll(); $roles=$db->createCommand('SELECT * FROM roles ORDER BY role_name')->queryAll(); $columns=rmColumns(); $tableCollation=$db->createCommand("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports'")->queryScalar();
    rmMigrate('up');
    rmCheck($db->createCommand('SELECT version FROM crm_migration ORDER BY apply_time DESC,version DESC LIMIT 1')->queryScalar()===$version,'Up migration history');
    $after=$db->createCommand('SELECT * FROM reports ORDER BY id')->queryAll(); rmCheck(count($before)===count($after),'Migration changed source row count');
    foreach ($after as $i=>$row) { rmCheck(LegacyImportSource::hash($row,'report')===LegacyImportSource::hash($before[$i],'report'),'Migration changed source values/fingerprint'); }
    rmCheck($db->createCommand('SELECT @@SESSION.sql_mode')->queryScalar()===$mode,'DDL did not restore session SQL mode');
    rmCheck($db->createCommand("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND COLUMN_NAME='client_request_key'")->queryScalar()==='utf8mb4_bin','Request key collation');
    rmCheck($db->schema->getTable('reports',true)->columns['sales']->allowNull && $db->schema->getTable('reports')->columns['site_ins']->allowNull,'Optional old schedule dates');
    rmMigrate('down');
    rmCheck(rmColumns()===$columns,'Round trip did not restore exact columns');
    rmCheck($db->createCommand("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports'")->queryScalar()===$tableCollation,'Round trip table collation');
    rmCheck($db->createCommand('SELECT * FROM reports ORDER BY id')->queryAll()===$before,'Round trip changed legacy rows/zero dates');
    rmCheck($db->createCommand('SELECT * FROM roles ORDER BY role_name')->queryAll()===$roles,'Round trip changed original rights');
    rmCheck($db->createCommand('SELECT @@SESSION.sql_mode')->queryScalar()===$mode,'Down did not restore SQL mode');
    rmMigrate('up'); rmCheck($db->schema->getTable('reports',true)->getColumn('snapshot_json')!==null,'Reapply schema');
} finally {
    $db->createCommand('SET SESSION sql_mode=:mode')->execute(array(':mode'=>$mode)); if ($zeroId) { $db->createCommand()->delete('reports','id=:id',array(':id'=>$zeroId)); }
}
echo "PASS: $checks Report migration assertions; up/down/up history, exact original columns/rights/rows, zero dates, source fingerprints, SQL mode and binary request key.\n";
