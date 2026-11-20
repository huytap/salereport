<?php
if (PHP_SAPI!=='cli') { exit(2); }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
$app=Yii::createConsoleApplication(require __DIR__.'/../config/console.php'); $db=$app->db; $db->active=true;
if ($db->createCommand('SELECT DATABASE()')->queryScalar()!=='sale_report_mvp_test_pilot_restore' || $db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Dedicated synthetic pilot_restore DB required.'); }
require_once __DIR__.'/../migrations/mvp/m261120_110000_prepare_pilot_indexes.php'; $checks=0;
function imc($ok,$m) { global $checks; if (!$ok) { throw new Exception($m); } $checks++; }
function rowSnapshot9() { $out=array(); foreach (Yii::app()->db->schema->tableNames as $t) { $out[$t]=Yii::app()->db->createCommand('SELECT * FROM '.$t)->queryAll(); } return $out; }
function indexSnapshot9() { $out=array(); foreach (array('crm_account','crm_task') as $t) { $rows=Yii::app()->db->createCommand('SHOW INDEX FROM '.$t)->queryAll(); $out[$t]=array_map(function($r){return array($r['Key_name'],$r['Seq_in_index'],$r['Column_name'],$r['Non_unique'],$r['Sub_part']);},$rows); } return $out; }
$before=rowSnapshot9(); ob_start();
try {
    (new m261120_110000_prepare_pilot_indexes)->down(); $oldIndexes=indexSnapshot9(); (new m261120_110000_prepare_pilot_indexes)->up();
    imc(rowSnapshot9()===$before,'Index up/down must not change any rows, roles, flags or schema journals');
    $new=indexSnapshot9(); foreach (array('ix_account_pilot_list','ix_task_pilot_dates','ix_task_pilot_due') as $name) { $found=false; foreach ($new as $rows) { foreach ($rows as $r) { if ($r[0]===$name) { $found=true; } } } imc($found,'Added '.$name); }
    (new m261120_110000_prepare_pilot_indexes)->down(); imc(indexSnapshot9()===$oldIndexes,'Down restores previous indexes'); (new m261120_110000_prepare_pilot_indexes)->up();
    $db->createCommand('ALTER TABLE crm_task DROP INDEX ix_task_pilot_due, ADD INDEX ix_task_pilot_due (id,deadline_at_utc)')->execute();
    imc($app->commandRunner->run(array('yiic','pilot','check','--actor=1'))===1,'Preflight rejects changed index definition');
    try { (new m261120_110000_prepare_pilot_indexes)->down(); throw new Exception('Changed index down accepted'); } catch (CDbException $e) { imc(strpos($e->getMessage(),'changed/missing')!==false,'Protect changed index'); }
    finally { $db->createCommand('ALTER TABLE crm_task DROP INDEX ix_task_pilot_due, ADD INDEX ix_task_pilot_due (deadline_at_utc,id)')->execute(); }
    imc(rowSnapshot9()===$before,'Rollback guard preserves all data');
    $snapshot=rowSnapshot9(); $code=$app->commandRunner->run(array('yiic','pilot','check','--actor=1')); imc($code===0 || $code===null,'Read-only pilot preflight schema'); imc(rowSnapshot9()===$snapshot,'Pilot preflight writes no data');
} finally { ob_end_clean(); }
echo "PASS: $checks WP09 index migration/preflight assertions; up/down/up, no data changes, edited index guard and read-only check.\n";
