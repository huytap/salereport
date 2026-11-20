<?php
class m261120_090000_prepare_customer_cutover extends CDbMigration
{
    private function db()
    {
        $db=$this->getDbConnection();
        if (!($db instanceof IsolatedTestDbConnection) || $db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CDbException('WP08 requires the isolated test DB.'); }
        return $db;
    }
    public function up()
    {
        $db=$this->db();
        if (!$db->schema->getTable('crm_reminder_dispatch_run') || $db->schema->getTable('crm_cutover',true)) { throw new CDbException('Apply WP07 first; WP08 must not exist.'); }
        $options='ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $this->createTable('crm_cutover',array('id'=>'INT NOT NULL PRIMARY KEY','stage'=>"VARCHAR(16) NOT NULL DEFAULT 'preparing'",'canonical_latched'=>'TINYINT NOT NULL DEFAULT 0','lock_version'=>'INT UNSIGNED NOT NULL DEFAULT 1','changed_by'=>'INT UNSIGNED NULL','changed_at_utc'=>'DATETIME NULL','reason'=>'TEXT NULL'),$options);
        $this->insert('crm_cutover',array('id'=>1));
        $this->createTable('crm_transfer_batch',array('id'=>'pk','kind'=>'VARCHAR(16) NOT NULL','hotel_id'=>'INT UNSIGNED NULL','source_hash'=>'CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL','status'=>"VARCHAR(16) NOT NULL DEFAULT 'preview'",'lock_version'=>'INT UNSIGNED NOT NULL DEFAULT 1','created_by'=>'INT UNSIGNED NOT NULL','approved_by'=>'INT UNSIGNED NULL','approval_hash'=>'CHAR(64) NULL','created_at_utc'=>'DATETIME NOT NULL'),$options);
        $this->createIndex('ix_transfer_source','crm_transfer_batch','kind,source_hash,created_by');
        $this->createTable('crm_transfer_row',array('id'=>'pk','batch_id'=>'INT NOT NULL','row_no'=>'INT UNSIGNED NOT NULL','payload_json'=>'MEDIUMTEXT NOT NULL','decision_json'=>'TEXT NULL','state'=>"VARCHAR(16) NOT NULL DEFAULT 'needs_review'",'lock_version'=>'INT UNSIGNED NOT NULL DEFAULT 1','result_json'=>'TEXT NULL','error_code'=>'VARCHAR(64) NULL'),$options);
        $this->createIndex('uq_transfer_row','crm_transfer_row','batch_id,row_no',true);
        $this->addForeignKey('fk_transfer_batch','crm_transfer_row','batch_id','crm_transfer_batch','id','RESTRICT','RESTRICT');
        $this->createTable('crm_cutover_action_seed',array('role_name'=>'VARCHAR(64) NOT NULL PRIMARY KEY','before_rights'=>'TEXT NOT NULL','after_rights'=>'TEXT NOT NULL'),$options);
        foreach ($db->createCommand('SELECT role_name,access_rights FROM roles')->queryAll() as $row) {
            $names=Yii::app()->params['mvp']['globalScopeRoles']; $name=mb_strtolower(trim($row['role_name']),'UTF-8');
            if (!in_array($name,$names,true)) { continue; }
            $rights=json_decode($row['access_rights'],true); if (!is_array($rights)) { continue; }
            $rights['customermove']=array_values(array_unique(array_merge(isset($rights['customermove']) && is_array($rights['customermove'])?$rights['customermove']:array(),ActionAccess::catalog()['customermove']))); $after=json_encode($rights);
            if ($after===$row['access_rights']) { continue; }
            $db->createCommand()->insert('crm_cutover_action_seed',array('role_name'=>$row['role_name'],'before_rights'=>$row['access_rights'],'after_rights'=>$after));
            if (!$db->createCommand()->update('roles',array('access_rights'=>$after),'role_name=:name AND access_rights=:before',array(':name'=>$row['role_name'],':before'=>$row['access_rights']))) { throw new CDbException('Cutover rights changed; inspect seed journal.'); }
        }
        $db->schema->refresh();
    }
    public function down()
    {
        $db=$this->db();
        if ($db->createCommand("SELECT COUNT(*) FROM crm_cutover WHERE stage<>'preparing'")->queryScalar() || $db->createCommand('SELECT COUNT(*) FROM crm_transfer_batch')->queryScalar() || $db->createCommand('SELECT canonical_latched FROM crm_cutover WHERE id=1')->queryScalar() || $db->createCommand("SELECT COUNT(*) FROM crm_audit_event WHERE entity_type='cutover'")->queryScalar()) { throw new CDbException('WP08 history exists: close writes, do not drop or reopen legacy.'); }
        $roles=$db->createCommand('SELECT * FROM crm_cutover_action_seed')->queryAll();
        foreach ($roles as $r) { if ($db->createCommand('SELECT access_rights FROM roles WHERE role_name=:name')->queryScalar(array(':name'=>$r['role_name']))!==$r['after_rights']) { throw new CDbException('Refusing rollback: rights changed.'); } }
        foreach ($roles as $r) { if (!$db->createCommand()->update('roles',array('access_rights'=>$r['before_rights']),'role_name=:name AND access_rights=:after',array(':name'=>$r['role_name'],':after'=>$r['after_rights']))) { throw new CDbException('Refusing rollback: rights changed during restore; inspect journal.'); } }
        $this->dropTable('crm_cutover_action_seed');
        $this->dropTable('crm_transfer_row'); $this->dropTable('crm_transfer_batch'); $this->dropTable('crm_cutover'); $db->schema->refresh();
    }
}
