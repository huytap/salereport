<?php

/** Real commits + independent connections, using only run-owned synthetic records. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
$app=Yii::createConsoleApplication(require __DIR__.'/../config/console.php');
class DurabilityActor extends CApplicationComponent { public $id=1; public function getIsGuest() { return false; } }
$app->setComponent('user',new DurabilityActor);
$db=$app->db; $db->setActive(true);
if ($db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Synthetic test schema required.'); }
$checks=0; $sourceIds=array(); $hotelId=null; $batchId=null; $peer=null;
function durableCheck($ok,$message) { global $checks; if (!$ok) { throw new Exception($message); } $checks++; }
$label='WP03 durable '.bin2hex(random_bytes(6));
$base=$db->createCommand('SELECT * FROM contact ORDER BY id LIMIT 1')->queryRow();
try {
    $hotel=new Hotel; $hotel->code='DUR-'.bin2hex(random_bytes(6)); $hotel->name=$label; durableCheck($hotel->save(),'Hotel fixture failed.'); $hotelId=(int)$hotel->id;
    foreach (array('first','second') as $part) {
        $row=$base; unset($row['id']); $row['company']=$label.' '.$part; $row['first_name']='Synthetic'; $row['last_name']=$part;
        $row['email']=$part==='first'?'durable@example.test':'invalid-address'; $row['hotel_produced']=$label;
        $db->createCommand()->insert('contact',$row); $sourceIds[]=(int)$db->lastInsertID;
    }
    $batchId=CustomerImportService::preview('contact',$sourceIds);
    foreach (CustomerImportService::view($batchId)['rows'] as $row) {
        CustomerImportService::map($batchId,$row['row_no'],$row['lock_version'],array('mode'=>'map','account_kind'=>'create','contact_kind'=>'create','hotels'=>array(array('hotel_id'=>$hotelId,'owner_user_id'=>null)),'reason'=>'Durability fixture review'));
    }
    $view=CustomerImportService::view($batchId); CustomerImportService::approve($batchId,$view['batch']['lock_version']);
    $result=CustomerImportService::commit($batchId,1); durableCheck($result['counts']['created']===1 && $result['batch']['status']==='running','First row did not commit independently.');
    $accountId=CustomerImportService::view($batchId)['rows'][0]['result_account_id'];
    $peer=Yii::createComponent(require __DIR__.'/../config/db-test.php'); $peer->setActive(true);
    durableCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_account WHERE id=:id')->queryScalar(array(':id'=>$accountId))===1,'Peer connection cannot see committed first account.');
    durableCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_legacy_link WHERE source_type=\'contact\' AND source_id=:id')->queryScalar(array(':id'=>(string)$sourceIds[0]))===1,'Peer cannot see committed source link.');
    $db->setActive(false); $db->setActive(true); // Simulate loss of request/connection after bounded progress.
    $result=CustomerImportService::commit($batchId);
    durableCheck($result['counts']['created']===1 && $result['counts']['error']===1,'Resume after reconnect lost progress.');
    durableCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_account WHERE code=:code')->queryScalar(array(':code'=>'IMP-'.$batchId.'-2'))===0,'Failed row left a durable orphan account.');
    $view=CustomerImportService::view($batchId);
    CustomerImportService::map($batchId,2,$view['rows'][1]['lock_version'],array('mode'=>'map','account_kind'=>'create','contact_kind'=>'create','contact_fields'=>array('email'=>'repaired@example.test'),
        'hotels'=>array(array('hotel_id'=>$hotelId,'owner_user_id'=>null)),'reason'=>'Repair synthetic invalid email'));
    $view=CustomerImportService::view($batchId); CustomerImportService::approve($batchId,$view['batch']['lock_version']);
    $result=CustomerImportService::commit($batchId);
    durableCheck($result['counts']['created']===2 && $result['batch']['status']==='completed' && $result['consistent'],'Repaired row did not finish durably.');
    $before=(int)$peer->createCommand('SELECT COUNT(*) FROM crm_audit_event WHERE import_batch_id=:id')->queryScalar(array(':id'=>$batchId));
    CustomerImportService::commit($batchId);
    durableCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_audit_event WHERE import_batch_id=:id')->queryScalar(array(':id'=>$batchId))===$before,'Repeated durable commit duplicated events.');
    foreach (CustomerImportService::view($batchId)['rows'] as $row) {
        durableCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_account WHERE id=:id')->queryScalar(array(':id'=>$row['result_account_id']))===1,'Peer cannot see repaired result.');
        durableCheck((int)$peer->createCommand('SELECT COUNT(*) FROM crm_contact_person WHERE id=:id')->queryScalar(array(':id'=>$row['result_contact_id']))===1,'Peer cannot see repaired contact.');
    }
} finally {
    // Delete only this run's records, never reset/truncate the test schema or legacy fixtures.
    $cleanup=$db->beginTransaction();
    try {
        if ($batchId) {
            $accounts=$db->createCommand('SELECT id FROM crm_account WHERE code IN (:one,:two)')->queryColumn(array(':one'=>'IMP-'.$batchId.'-1',':two'=>'IMP-'.$batchId.'-2'));
            $db->createCommand()->delete('crm_audit_event','import_batch_id=:id',array(':id'=>$batchId));
            $db->createCommand()->delete('crm_legacy_link','import_batch_id=:id',array(':id'=>$batchId));
            $db->createCommand()->delete('crm_import_row','batch_id=:id',array(':id'=>$batchId));
            $db->createCommand()->delete('crm_import_batch','id=:id',array(':id'=>$batchId));
            foreach ($accounts as $id) {
                foreach (array('crm_account_market','crm_entity_alias','crm_contact_person','crm_account_hotel') as $table) {
                    if ($table==='crm_entity_alias') { $db->createCommand()->delete($table,"entity_type='account' AND entity_id=:id",array(':id'=>$id)); }
                    else { $db->createCommand()->delete($table,'account_id=:id',array(':id'=>$id)); }
                }
                $db->createCommand()->delete('crm_account','id=:id',array(':id'=>$id));
            }
        }
        foreach ($sourceIds as $id) { $db->createCommand()->delete('contact','id=:id',array(':id'=>$id)); }
        if ($hotelId) { $db->createCommand()->delete('crm_hotel','id=:id',array(':id'=>$hotelId)); }
        $cleanup->commit();
    } catch (Exception $e) { $cleanup->rollback(); throw $e; }
    if ($peer) { $peer->setActive(false); }
}
echo "PASS: $checks durability assertions; independently visible commits, reconnect/resume, row rollback, idempotent retry and run-owned cleanup.\n";
