<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
$app=Yii::createConsoleApplication(require __DIR__.'/../config/console.php'); $db=$app->db; $db->setActive(true);
if ($db->createCommand('SELECT DATABASE()')->queryScalar()!=='sale_report_mvp_test_restore' || $db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Only synthetic test_restore may run this migration test.'); }
$checks=0; $version='m261119_130000_prepare_reminder_dispatch'; $runId=null;
function mc($ok,$message) { global $checks; if (!$ok) { throw new Exception($message); } $checks++; }
function migrateReminder($direction) {
    $args=array('yiic','migrate',$direction); if ($direction==='down') { $args[]='1'; } $args[]='--interactive=0'; ob_start();
    try { $code=Yii::app()->commandRunner->run($args); $out=ob_get_contents(); } finally { ob_end_clean(); }
    if ($code!==0 && $code!==null) { throw new Exception('Migration failed: '.$out); }
}
function reminderColumns() { return Yii::app()->db->createCommand("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('crm_task','crm_task_reminder','crm_notification') ORDER BY TABLE_NAME,ORDINAL_POSITION")->queryAll(); }
$last=$db->createCommand('SELECT version FROM crm_migration ORDER BY apply_time DESC,version DESC LIMIT 1')->queryScalar();
if ($last===$version) { migrateReminder('down'); }
elseif ($last!=='m261119_110000_prepare_report_workflows') { throw new Exception('Prepare exactly WP00–WP06 first.'); }
$sourceTables=array('users','roles','contact','reports','incident','staff_asst','trackings','user_history','crm_user_hotel_access','crm_task','crm_task_reminder','crm_notification'); $before=array();
foreach ($sourceTables as $table) { $before[$table]=$db->createCommand('SELECT * FROM '.$table)->queryAll(); } $columns=reminderColumns();
$tables=$db->schema->getTableNames(); sort($tables);
try {
    migrateReminder('up'); mc($db->schema->getTable('crm_reminder_dispatch_run',true)!==null && $db->schema->getTable('crm_reminder_action_seed',true)!==null,'New telemetry and seed journals');
    mc(strtolower($db->createCommand("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='crm_reminder_dispatch_run'")->queryScalar())==='innodb','Telemetry must be transactional');
    mc(reminderColumns()===$columns,'Migration changed Task/Reminder/Notification columns');
    $rights=$db->createCommand("SELECT access_rights FROM roles WHERE role_name='superadmin'")->queryScalar(); $decoded=json_decode($rights,true); mc(in_array('monitor',$decoded['reminder'],true),'Explicit All monitor permission');
    $admin=json_decode($db->createCommand("SELECT access_rights FROM roles WHERE role_name='admin'")->queryScalar(),true); mc(!in_array('monitor',$admin['reminder'],true),'Hotel admin must not automatically receive monitor action');
    require_once __DIR__.'/../migrations/mvp/m261119_130000_prepare_reminder_dispatch.php';
    $modified=$decoded; $modified['reminder'][]='manual_test_marker'; $modifiedJson=json_encode($modified); $db->createCommand()->update('roles',array('access_rights'=>$modifiedJson),"role_name='superadmin'");
    try { try { (new m261119_130000_prepare_reminder_dispatch)->down(); throw new Exception('Edited role rollback accepted'); } catch (CDbException $e) { mc(strpos($e->getMessage(),'rights changed')!==false,'Rollback preserves post-seed role edits'); } }
    finally { $db->createCommand()->update('roles',array('access_rights'=>$rights),'role_name=:role AND access_rights=:ours',array(':role'=>'superadmin',':ours'=>$modifiedJson)); }
    $now=gmdate('Y-m-d H:i:s'); $db->createCommand()->insert('crm_reminder_dispatch_run',array('run_key'=>'m7'.bin2hex(random_bytes(15)),'started_at_utc'=>$now,'heartbeat_at_utc'=>$now,'finished_at_utc'=>$now,'status'=>'completed')); $runId=$db->lastInsertID;
    try { (new m261119_130000_prepare_reminder_dispatch)->down(); throw new Exception('Dispatch history rollback accepted'); } catch (CDbException $e) { mc(strpos($e->getMessage(),'history')!==false,'Rollback preserves dispatch history'); }
    mc((int)$db->createCommand('SELECT COUNT(*) FROM crm_reminder_dispatch_run WHERE id=:id')->queryScalar(array(':id'=>$runId))===1,'Guard deleted run history');
    $db->createCommand()->delete('crm_reminder_dispatch_run','id=:id',array(':id'=>$runId)); $runId=null;
    migrateReminder('down'); $actualTables=$db->schema->getTableNames(); sort($actualTables); mc($actualTables===$tables,'Round-trip original tables');
    foreach ($before as $table=>$rows) { mc($db->createCommand('SELECT * FROM '.$table)->queryAll()===$rows,'Round-trip changed original rows/rights: '.$table); }
    mc(reminderColumns()===$columns,'Round-trip original data columns');
    migrateReminder('up'); mc($db->createCommand('SELECT version FROM crm_migration ORDER BY apply_time DESC,version DESC LIMIT 1')->queryScalar()===$version,'Reapply migration history');
} finally { if ($runId && $db->schema->getTable('crm_reminder_dispatch_run',true)) { $db->createCommand()->delete('crm_reminder_dispatch_run','id=:id',array(':id'=>$runId)); } }
echo "PASS: $checks reminder migration assertions; up/down/up, unchanged source/task/notification data, rights restoration, All-only monitoring and rollback guards.\n";
