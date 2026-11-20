<?php
/** Real dump/restore of synthetic data into a newly owned disposable test schema. */
if (PHP_SAPI!=='cli') { exit(2); }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
$config=require __DIR__.'/../config/console.php'; foreach (array('customerWrite','reportWrite','taskWrite') as $f) { $config['params']['mvp'][$f]=true; } $app=Yii::createConsoleApplication($config); $db=$app->db; $db->active=true;
class RecoveryActor extends CApplicationComponent { public $id=1; public function getIsGuest() { return false; } } $app->setComponent('user',new RecoveryActor);
if ($db->connectionString!=='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_pilot' || $db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1' || Account::model()->count() || Task::model()->count()) { throw new Exception('Empty local synthetic pilot schema required.'); }
$checks=0; function bc($ok,$m) { global $checks; if (!$ok) { throw new Exception($m); } $checks++; }
function snapshot9($db) {
    $hashes=array(); $tables=$db->schema->tableNames; sort($tables);
    foreach ($tables as $t) { $pk=$db->schema->getTable($t)->primaryKey; $order=$pk?' ORDER BY '.implode(',',array_map(array($db,'quoteColumnName'),(array)$pk)):''; $hashes[$t]=hash('sha256',CJSON::encode($db->createCommand('SELECT * FROM '.$db->quoteTableName($t).$order)->queryAll())); }
    return $hashes;
}
function cli9(array $cmd,$input,$output) {
    $p=proc_open($cmd,array(0=>$input,1=>$output,2=>array('pipe','w')),$pipes); if (!is_resource($p)) { throw new Exception('Backup subprocess failed'); } $err=stream_get_contents($pipes[2]); fclose($pipes[2]); $code=proc_close($p); if ($code!==0) { throw new Exception('Backup/restore subprocess failed; inspect local test setup (exit '.$code.').'); }
}
$target='sale_report_mvp_test_pilot_recovery_'.bin2hex(random_bytes(5)); $created=false; $peer=null; $aid=null; $hid=null; $dump=tempnam(sys_get_temp_dir(),'wp09-dump-'); $cnf=tempnam(sys_get_temp_dir(),'wp09-client-'); chmod($dump,0600); chmod($cnf,0600); $auditStart=(int)$db->createCommand('SELECT COALESCE(MAX(id),0) FROM crm_audit_event')->queryScalar(); $original=snapshot9($db); $report=null;
try {
    $h=new Hotel; $h->code='RECOVERY-'.bin2hex(random_bytes(5)); $h->name='Synthetic recovery'; CustomerWriteSupport::save($h); $hid=$h->id;
    $a=AccountService::create(array('name'=>'Hồ sơ phục hồi 😀'),$hid,1,array('first_name'=>'Liên hệ Đỗ'),array('VN'),bin2hex(random_bytes(16))); $aid=$a['account_id'];
    $r=ReportService::create($aid,$hid,$a['contact_id'],array('date'=>date('Y-m-d'),'sales_type'=>'Visit','objective'=>'Follow up','expected_result'=>'Groups','report'=>'Báo cáo phục hồi – kiểm tra 😀'),array(array('fields'=>array('title'=>'Synthetic recovery task','kind'=>'task','due_mode'=>'date','due_date'=>'2035-01-08','timezone'=>'Asia/Ho_Chi_Minh'))),bin2hex(random_bytes(16)));
    $before=snapshot9($db); IsolatedTestDbConnection::databaseName('mysql:host=127.0.0.1;dbname='.$target); $db->createCommand('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')->execute(); $created=true;
    // Credentials are never shell arguments or output. This file is mode 0600 and removed in finally.
    $quote=function($v){ return '"'.str_replace(array('\\','"',"\n","\r"),array('\\\\','\\"','\\n','\\r'),$v).'"'; };
    file_put_contents($cnf,"[client]\nhost=127.0.0.1\nprotocol=TCP\nuser=".$quote($db->username)."\npassword=".$quote($db->password)."\n");
    $start=microtime(true); cli9(array('/opt/homebrew/bin/mysqldump','--defaults-extra-file='.$cnf,'--default-character-set=utf8mb4','--single-transaction','--skip-lock-tables','--no-tablespaces','--set-gtid-purged=OFF','--skip-add-drop-table','sale_report_mvp_test_pilot'),array('file','/dev/null','r'),array('file',$dump,'w')); $dumpMs=(int)round((microtime(true)-$start)*1000);
    $peer=new IsolatedTestDbConnection('mysql:host=127.0.0.1;dbname='.$target,$db->username,$db->password); $peer->charset='utf8mb4'; $peer->emulatePrepare=false; $peer->active=true; bc(!$peer->schema->tableNames,'Recovery target starts empty');
    $start=microtime(true); cli9(array('/opt/homebrew/bin/mysql','--defaults-extra-file='.$cnf,'--default-character-set=utf8mb4',$target),array('file',$dump,'r'),array('file','/dev/null','w')); $restoreMs=(int)round((microtime(true)-$start)*1000); $peer->schema->refresh();
    $restored=snapshot9($peer); bc($restored===$before,'All restored tables match source row hashes: '.implode(',',array_keys(array_diff_assoc($before,$restored)))) ; bc(snapshot9($db)===$before,'Backup did not mutate source');
    bc($peer->createCommand('SELECT snapshot_json FROM reports WHERE id=:id')->queryScalar(array(':id'=>$r['report_id']))===Report::model()->findByPk($r['report_id'])->snapshot_json,'Immutable Report snapshot restored');
    bc((int)$peer->createCommand('SELECT COUNT(*) FROM crm_task_reminder WHERE task_id=:id')->queryScalar(array(':id'=>$r['tasks'][0]['task_id']))===2,'Reminder plans restored without dispatch');
    bc(!(int)$peer->createCommand('SELECT COUNT(*) FROM crm_notification')->queryScalar(),'Restore creates no notification');
    $report=array('result'=>'PASS','assertions'=>null,'tables'=>count($before),'dump_bytes'=>filesize($dump),'dump_ms'=>$dumpMs,'restore_ms'=>$restoreMs,'scope'=>'local synthetic quiescent DB; not production RPO/RTO','target'=>'new disposable isolated schema; dropped after verification');
} finally {
    if ($peer) { $peer->active=false; }
    if ($created) { if (!preg_match('/^sale_report_mvp_test_pilot_recovery_[a-f0-9]{10}$/D',$target)) { throw new Exception('Refusing cleanup target'); } $db->createCommand('DROP DATABASE `'.$target.'`')->execute(); }
    foreach (array($dump,$cnf) as $file) { if (is_file($file)) { unlink($file); } }
    $tx=$db->beginTransaction();
    try {
        $db->createCommand()->delete('crm_audit_event','id>:id',array(':id'=>$auditStart));
        if ($aid) {
            foreach ($db->createCommand('SELECT id FROM crm_task WHERE account_id=:id')->queryColumn(array(':id'=>$aid)) as $id) { foreach (array('crm_notification','crm_task_reminder') as $t) { $db->createCommand()->delete($t,'task_id=:id',array(':id'=>$id)); } }
            foreach (array('crm_task','reports','crm_customer_request','crm_contact_person','crm_account_market','crm_account_hotel') as $t) { $db->createCommand()->delete($t,'account_id=:id',array(':id'=>$aid)); } $db->createCommand()->delete('crm_account','id=:id',array(':id'=>$aid));
        }
        if ($hid) { $db->createCommand()->delete('crm_hotel','id=:id',array(':id'=>$hid)); } $tx->commit();
    } catch (Exception $e) { $tx->rollback(); throw $e; }
}
bc(snapshot9($db)===$original,'Original source fixture restored after rehearsal'); $report['assertions']=$checks; echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
