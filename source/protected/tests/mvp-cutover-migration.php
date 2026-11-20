<?php
if (PHP_SAPI!=='cli') { exit(2); }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
$app=Yii::createConsoleApplication(require __DIR__.'/../config/console.php'); $db=$app->db; $db->active=true;
if ($db->createCommand('SELECT DATABASE()')->queryScalar()!=='sale_report_mvp_test_cutover_restore' || $db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Only synthetic test_cutover_restore allowed.'); }
require_once __DIR__.'/../migrations/mvp/m261120_090000_prepare_customer_cutover.php';
$checks=0; function mc8($ok,$m) { global $checks; if (!$ok) { throw new Exception($m); } $checks++; }
function rejectDown8($message) { try { (new m261120_090000_prepare_customer_cutover)->down(); throw new Exception('Unsafe down accepted'); } catch (CDbException $e) { mc8(strpos($e->getMessage(),$message)!==false,'Down rejected: '.$message); } }
$latest=$db->createCommand('SELECT version FROM crm_migration ORDER BY apply_time DESC,version DESC LIMIT 1')->queryScalar();
if ($latest!=='m261120_090000_prepare_customer_cutover') { throw new Exception('Migrate WP00–08 on the dedicated restore DB first.'); }
ob_start();
try {
    if ($db->schema->getTable('crm_cutover',true)) { (new m261120_090000_prepare_customer_cutover)->down(); }
    // Fresh WP02 seeds use the live catalog; emulate an existing WP07 role with no WP08 rights.
    $preRights=json_decode($db->createCommand("SELECT access_rights FROM roles WHERE role_name='superadmin'")->queryScalar(),true); unset($preRights['customermove']);
    $db->createCommand()->update('roles',array('access_rights'=>json_encode($preRights)),"role_name='superadmin'");
    $before=array(); foreach (array('contact','reports','users','roles','crm_account','crm_task','crm_notification','crm_task_reminder') as $t) { $before[$t]=$db->createCommand('SELECT * FROM '.$t)->queryAll(); }
    $columns=$db->createCommand('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION')->queryAll();
    (new m261120_090000_prepare_customer_cutover)->up();
    foreach (array('crm_cutover','crm_transfer_batch','crm_transfer_row','crm_cutover_action_seed') as $t) { mc8($db->schema->getTable($t,true)!==null,'Table '.$t); mc8(strtolower($db->createCommand('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t')->queryScalar(array(':t'=>$t)))==='innodb','Transactional '.$t); }
    $rights=$db->createCommand("SELECT access_rights FROM roles WHERE role_name='superadmin'")->queryScalar(); $r=json_decode($rights,true); mc8(in_array('cutover',$r['customermove'],true),'All action seed');
    $tx=$db->beginTransaction(); try { $db->createCommand()->update('crm_cutover',array('stage'=>'frozen'),'id=1'); rejectDown8('history'); } finally { $tx->rollback(); }
    $tx=$db->beginTransaction(); try { $db->createCommand()->update('crm_cutover',array('canonical_latched'=>1),'id=1'); rejectDown8('history'); } finally { $tx->rollback(); }
    $tx=$db->beginTransaction(); try { $db->createCommand()->insert('crm_transfer_batch',array('kind'=>'csv','source_hash'=>str_repeat('0',64),'created_by'=>1,'created_at_utc'=>gmdate('Y-m-d H:i:s'))); rejectDown8('history'); } finally { $tx->rollback(); }
    $tx=$db->beginTransaction(); try { $db->createCommand()->insert('crm_audit_event',array('entity_type'=>'cutover','entity_id'=>'1','event_type'=>'test','actor_user_id'=>1,'after_json'=>'{}','request_id'=>str_repeat('a',32),'occurred_at_utc'=>gmdate('Y-m-d H:i:s'))); rejectDown8('history'); } finally { $tx->rollback(); }
    $modified=$r; $modified['customermove'][]='manual_marker'; $modifiedJson=json_encode($modified);
    $db->createCommand()->update('roles',array('access_rights'=>$modifiedJson),"role_name='superadmin'");
    try { rejectDown8('rights changed'); } finally { $db->createCommand()->update('roles',array('access_rights'=>$rights),'role_name=:name AND access_rights=:ours',array(':name'=>'superadmin',':ours'=>$modifiedJson)); }
    (new m261120_090000_prepare_customer_cutover)->down();
    foreach ($before as $t=>$rows) { mc8($db->createCommand('SELECT * FROM '.$t)->queryAll()===$rows,'Restored rows/rights '.$t); }
    mc8($db->createCommand('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION')->queryAll()===$columns,'Original schema intact after down');
    (new m261120_090000_prepare_customer_cutover)->up(); mc8(CutoverService::state()['stage']==='preparing','Reapply leaves gates inactive');
} finally { ob_end_clean(); }
echo "PASS: $checks WP08 migration assertions; up/down/up, source schema/data intact, rights journal and irreversible-history guards.\n";
