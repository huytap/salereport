<?php
/** Durable, whole-dataset cutover. No deployment or flag changes are performed here. */
class CutoverService
{
    private static $maintenance=0;
    public static function maintenance($callback)
    {
        self::admin('commit'); self::testDb(); self::$maintenance++;
        try { return $callback(); } finally { self::$maintenance--; }
    }
    public static function state()
    {
        $db=Yii::app()->db;
        if (!$db->schema->getTable('crm_cutover')) { return array('stage'=>'preparing','canonical_latched'=>0,'lock_version'=>0); }
        $s=$db->createCommand('SELECT * FROM crm_cutover WHERE id=1 LOCK IN SHARE MODE')->queryRow();
        if (!$s || !in_array($s['stage'],array('preparing','frozen','canonical','closed'),true)) { throw new CHttpException(503,'Chốt chuyển đổi không hợp lệ.'); }
        return $s;
    }
    public static function canonicalWrite()
    {
        $s=self::state();
        if ($s['stage']==='closed' || ($s['stage']==='frozen' && !self::$maintenance)) { throw new CHttpException(503,'Đang đóng ghi để chuyển đổi/khắc phục.'); }
    }
    public static function legacyBlocked()
    {
        $s=self::state(); return $s['stage']!=='preparing' || $s['canonical_latched'];
    }
    public static function legacyWrite()
    {
        // Hold the same mutex as canonical writes until request end, including direct CSV entry points.
        $db=Yii::app()->db; $key=CustomerImportService::lockName();
        if ((int)$db->createCommand('SELECT GET_LOCK(:key,0)')->queryScalar(array(':key'=>$key))!==1) { throw new CHttpException(409,'Đang chuyển đổi hồ sơ.'); }
        if (self::legacyBlocked()) { $db->createCommand('SELECT RELEASE_LOCK(:key)')->queryScalar(array(':key'=>$key)); throw new CHttpException(410,'Đường ghi cũ đã đóng; dùng hồ sơ thống nhất.'); }
        // MySQL named locks are connection-bound. One acquisition is released for each guard call.
        register_shutdown_function(function()use($db,$key){ if ($db->active) { $db->createCommand('SELECT RELEASE_LOCK(:key)')->queryScalar(array(':key'=>$key)); } });
    }
    public static function sourceImport()
    {
        if (!in_array(self::state()['stage'],array('preparing','frozen'),true)) { throw new CHttpException(410,'Backfill nguồn cũ đã đóng sau cutover.'); }
    }
    public static function testDb()
    {
        $db=Yii::app()->db;
        if (!($db instanceof IsolatedTestDbConnection) || $db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString) || !$db->schema->getTable('crm_cutover')) { throw new CHttpException(503,'WP08 chỉ ghi trên DB test đã migrate.'); }
        return $db;
    }
    public static function admin($action)
    {
        ActionAccess::requireAction('customermove',$action);
        if (!ActionAccess::isAll()) { throw new CHttpException(403,'Cần quyền All cho chuyển đổi nguồn cũ.'); }
    }
    public static function exclusive($callback)
    {
        $db=self::testDb(); $key=CustomerImportService::lockName();
        if ((int)$db->createCommand('SELECT GET_LOCK(:key,0)')->queryScalar(array(':key'=>$key))!==1) { throw new CHttpException(409,'Có thao tác hồ sơ đang chạy.'); }
        $outer=$db->getCurrentTransaction(); $owns=!$outer || !$outer->active; $sp='move_'.bin2hex(random_bytes(8));
        try {
            if ($owns) { $tx=$db->beginTransaction(); } else { $db->createCommand('SAVEPOINT '.$sp)->execute(); }
            try { $value=$callback(); if ($owns) { $tx->commit(); } else { $db->createCommand('RELEASE SAVEPOINT '.$sp)->execute(); } return $value; }
            catch (Exception $e) { if ($owns && $tx->active) { $tx->rollback(); } elseif (!$owns && $db->getPdoInstance()->inTransaction()) { $db->createCommand('ROLLBACK TO SAVEPOINT '.$sp)->execute(); } throw $e; }
        } finally { $db->createCommand('SELECT RELEASE_LOCK(:key)')->queryScalar(array(':key'=>$key)); }
    }
    public static function readiness()
    {
        self::admin('cutover'); $db=self::testDb(); $issues=array();
        foreach (array('customerWrite','reportWrite','taskWrite') as $flag) { if (empty(Yii::app()->params['mvp'][$flag])) { $issues[]='FLAG_OFF_'.$flag; } }
        $counts=array(); $quarantine=array('contact'=>array(),'reports'=>array());
        foreach ($db->createCommand("SELECT source_table,source_id,source_hash FROM crm_import_row WHERE result_state='skipped' AND source_table IN ('contact','reports')")->queryAll() as $row) {
            if (!ctype_digit((string)$row['source_id']) || (int)$row['source_id']<1) { continue; }
            $raw=$db->createCommand('SELECT * FROM '.$row['source_table'].' WHERE id=:id')->queryRow(true,array(':id'=>$row['source_id']));
            $type=$row['source_table']==='contact'?'contact':'report';
            if ($raw && $row['source_hash'] && LegacyImportSource::hash($raw,$type)===$row['source_hash']) { $quarantine[$row['source_table']][(int)$row['source_id']]=(int)$row['source_id']; }
        }
        $contactSkip=$quarantine['contact']?' AND c.id NOT IN ('.implode(',',$quarantine['contact']).')':'';
        $reportSkip=$quarantine['reports']?' AND id NOT IN ('.implode(',',$quarantine['reports']).')':'';
        $reportAliasSkip=$quarantine['reports']?' AND r.id NOT IN ('.implode(',',$quarantine['reports']).')':'';
        $counts['quarantined_contacts']=count($quarantine['contact']); $counts['quarantined_reports']=count($quarantine['reports']);
        $queries=array(
            'unmapped_contacts'=>"SELECT COUNT(*) FROM contact c WHERE NOT EXISTS (SELECT 1 FROM crm_legacy_link l WHERE l.source_type='contact' AND l.source_id=(CAST(c.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci) AND l.resolution_state='linked' AND l.account_id IS NOT NULL)".$contactSkip,
            'unmapped_reports'=>"SELECT COUNT(*) FROM reports WHERE (account_id IS NULL OR hotel_id IS NULL OR identity_state<>'linked')".$reportSkip,
            'unverified_reports'=>"SELECT COUNT(*) FROM reports r WHERE r.snapshot_json IS NULL AND NOT EXISTS (SELECT 1 FROM crm_legacy_link l WHERE l.source_type='report' AND l.source_id=(CAST(r.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci) AND l.resolution_state='linked' AND l.account_id=r.account_id AND l.contact_person_id <=> r.contact_person_id)".$reportAliasSkip,
            'invalid_report_assignments'=>"SELECT COUNT(*) FROM reports r WHERE r.account_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_account_hotel ah WHERE ah.account_id=r.account_id AND ah.hotel_id=r.hotel_id)",
            'open_import_rows'=>"SELECT COUNT(*) FROM crm_import_row WHERE result_state NOT IN ('created','linked','skipped')",
            'open_transfer_rows'=>"SELECT COUNT(*) FROM crm_transfer_row WHERE state NOT IN ('created','linked','skipped')",
            'unassigned'=>"SELECT COUNT(*) FROM crm_account_hotel WHERE status='active' AND owner_user_id IS NULL",
            'running_dispatch'=>"SELECT COUNT(*) FROM crm_reminder_dispatch_run WHERE status='running'",
        );
        foreach ($queries as $key=>$sql) { $counts[$key]=(int)$db->createCommand($sql)->queryScalar(); if ($counts[$key]) { $issues[]=$key; } }
        $invalidOwners=0;
        foreach ($db->createCommand("SELECT owner_user_id,hotel_id FROM crm_account_hotel WHERE status='active' AND owner_user_id IS NOT NULL")->queryAll() as $ah) { try { CustomerWriteSupport::owner($ah['owner_user_id'],$ah['hotel_id']); } catch (CHttpException $e) { $invalidOwners++; } catch (CustomerValidationException $e) { $invalidOwners++; } }
        $counts['invalid_owners']=$invalidOwners; if ($invalidOwners) { $issues[]='invalid_owners'; }
        // Every nonempty legacy schedule must have a reviewed terminal row, even invalid/past dates.
        $missing=0; $reviewed=array();
        foreach ($db->createCommand("SELECT payload_json FROM crm_transfer_row r JOIN crm_transfer_batch b ON b.id=r.batch_id WHERE b.kind='schedule' AND r.state IN ('created','linked','skipped')")->queryColumn() as $json) { $p=json_decode($json,true); $reviewed[$p['legacy_key']][]=$p['source_hash'].':'.$p['account_id'].':'.$p['hotel_id'].':'.$p['contact_id']; }
        foreach ($db->createCommand('SELECT * FROM reports WHERE snapshot_json IS NULL')->queryAll() as $r) {
            $hash=LegacyImportSource::hash($r,'report');
            foreach (array('ent','sales','site_ins') as $f) { if ($r[$f]!==null && trim($r[$f])!=='') {
                $key='report:'.$r['id'].':field:'.$f;
                if (!isset($reviewed[$key]) || !in_array($hash.':'.$r['account_id'].':'.$r['hotel_id'].':'.$r['contact_person_id'],$reviewed[$key],true)) { $missing++; }
            } }
        }
        foreach ($db->createCommand("SELECT * FROM crm_legacy_link WHERE resolution_state='linked'")->queryAll() as $l) {
            $type=$l['source_type']; if (!in_array($type,array('contact','report'),true)) { continue; }
            $source=$db->createCommand('SELECT * FROM '.LegacyImportSource::table($type).' WHERE id=:id')->queryRow(true,array(':id'=>$l['source_id']));
            if (!$source || !$l['source_hash'] || LegacyImportSource::hash($source,$type)!==$l['source_hash']) { $issues[]='LEGACY_SOURCE_DRIFT'; break; }
        }
        foreach ($db->createCommand('SELECT id FROM crm_transfer_batch')->queryColumn() as $id) { if (!CustomerTransferService::reconcile($id,'cutover')['consistent']) { $issues[]='TRANSFER_RECONCILIATION_FAILED'; break; } }
        $counts['unreviewed_schedules']=$missing; if ($missing) { $issues[]='unreviewed_schedules'; }
        return array('state'=>self::state(),'ready'=>!$issues,'issues'=>$issues,'counts'=>$counts,'reminders_enabled'=>!empty(Yii::app()->params['mvp']['reminders']));
    }
    public static function transition($target,$expected,$reason)
    {
        self::admin('cutover'); CustomerWriteSupport::reason($reason);
        return self::exclusive(function()use($target,$expected,$reason){
            $db=Yii::app()->db; $s=self::state(); CustomerWriteSupport::id($expected);
            if ((int)$s['lock_version']!==(int)$expected) { throw new CHttpException(409,'Chốt đã thay đổi.'); }
            $allowed=array('preparing'=>array('frozen'),'frozen'=>array('preparing','canonical','closed'),'canonical'=>array('closed'),'closed'=>array('canonical'));
            if (!isset($allowed[$s['stage']]) || !in_array($target,$allowed[$s['stage']],true)) { throw new CHttpException(409,'Chuyển trạng thái không hợp lệ.'); }
            if ($target==='preparing' && ($s['canonical_latched'] || $db->createCommand('SELECT COUNT(*) FROM crm_customer_request')->queryScalar() || $db->createCommand('SELECT COUNT(*) FROM crm_task')->queryScalar())) { throw new CHttpException(409,'Đã có phát sinh MVP; chỉ được đóng ghi, không mở lại ghi cũ.'); }
            if ($target==='canonical') { $check=self::readiness(); if (!$check['ready']) { throw new CHttpException(409,'Chưa đạt đối soát: '.implode(', ',$check['issues'])); } }
            $values=array('stage'=>$target,'canonical_latched'=>($s['canonical_latched'] || $target==='canonical')?1:0,'lock_version'=>(int)$expected+1,'changed_by'=>Yii::app()->user->id,'changed_at_utc'=>gmdate('Y-m-d H:i:s'),'reason'=>$reason);
            $db->createCommand()->update('crm_cutover',$values,'id=1'); $audit=$values; if (isset($check)) { $audit['readiness']=$check; } CustomerWriteSupport::audit('cutover',1,'transition',$s,$audit,$reason); return self::state();
        });
    }
}
