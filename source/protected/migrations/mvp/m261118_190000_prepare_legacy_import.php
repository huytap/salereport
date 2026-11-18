<?php

/** Reviewable import snapshots and action grants; test schemas only in this release. */
class m261118_190000_prepare_legacy_import extends CDbMigration
{
    private function db()
    {
        $db=$this->getDbConnection();
        if (!($db instanceof IsolatedTestDbConnection)) { throw new CDbException('WP03 requires the isolated test DB connection.'); }
        if ($db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CDbException('Database mismatch.'); }
        return $db;
    }
    public function up()
    {
        $db=$this->db();
        foreach (array('crm_import_batch'=>array('approval_hash','lock_version'),'crm_import_row'=>array('source_hash','decision_json','result_json','lock_version'),'crm_legacy_link'=>array('source_hash')) as $table=>$columns) {
            $schema=$db->schema->getTable($table,true);
            if (!$schema) { throw new CDbException('Apply WP01 first.'); }
            foreach ($columns as $column) { if (isset($schema->columns[$column])) { throw new CDbException('Pre-existing import column: '.$column); } }
        }
        if ($db->schema->getTable('crm_import_action_seed',true)) { throw new CDbException('Pre-existing import action journal.'); }
        $this->execute('ALTER TABLE crm_import_batch ADD approval_hash VARCHAR(64) NULL, ADD lock_version INT UNSIGNED NOT NULL DEFAULT 1');
        $this->execute('ALTER TABLE crm_import_row ADD source_hash VARCHAR(64) NULL, ADD decision_json MEDIUMTEXT NULL, ADD result_json MEDIUMTEXT NULL, ADD lock_version INT UNSIGNED NOT NULL DEFAULT 1');
        $this->execute('ALTER TABLE crm_legacy_link ADD source_hash VARCHAR(64) NULL');
        $this->createTable('crm_import_action_seed',array('role_name'=>'VARCHAR(64) NOT NULL PRIMARY KEY','before_rights'=>'TEXT NOT NULL','after_rights'=>'TEXT NOT NULL'),'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        foreach ($db->createCommand('SELECT role_name,access_rights FROM roles')->queryAll() as $row) {
            $rights=json_decode($row['access_rights'],true);
            if (!is_array($rights) || !isset($rights['customerimport']) || !is_array($rights['customerimport']) || !in_array('commit',$rights['customerimport'],true)) { continue; }
            $rights['customerimport']=array_values(array_unique(array_merge($rights['customerimport'],array('map','approve','reconcile'))));
            $after=json_encode($rights);
            $db->createCommand()->insert('crm_import_action_seed',array('role_name'=>$row['role_name'],'before_rights'=>$row['access_rights'],'after_rights'=>$after));
            $changed=$db->createCommand()->update('roles',array('access_rights'=>$after),'role_name=:name AND access_rights=:before',array(':name'=>$row['role_name'],':before'=>$row['access_rights']));
            if (!$changed && $row['access_rights']!==$after) { throw new CDbException('Role changed during WP03 seed; inspect the action journal.'); }
        }
        $db->schema->refresh();
    }
    public function down()
    {
        $db=$this->db();
        if ($db->createCommand('SELECT COUNT(*) FROM crm_import_batch')->queryScalar()>0 || $db->createCommand('SELECT COUNT(*) FROM crm_legacy_link WHERE source_hash IS NOT NULL')->queryScalar()>0) {
            throw new CDbException('Refusing rollback: import snapshots or source fingerprints exist.');
        }
        $rows=$db->createCommand('SELECT * FROM crm_import_action_seed')->queryAll();
        foreach ($rows as $row) {
            if ($db->createCommand('SELECT access_rights FROM roles WHERE role_name=:name')->queryScalar(array(':name'=>$row['role_name']))!==$row['after_rights']) { throw new CDbException('Refusing rollback: import rights changed.'); }
        }
        foreach ($rows as $row) { $db->createCommand()->update('roles',array('access_rights'=>$row['before_rights']),'role_name=:name',array(':name'=>$row['role_name'])); }
        $this->execute('ALTER TABLE crm_legacy_link DROP COLUMN source_hash');
        $this->execute('ALTER TABLE crm_import_row DROP COLUMN source_hash, DROP COLUMN decision_json, DROP COLUMN result_json, DROP COLUMN lock_version');
        $this->execute('ALTER TABLE crm_import_batch DROP COLUMN approval_hash, DROP COLUMN lock_version');
        $this->dropTable('crm_import_action_seed');
        $db->schema->refresh();
    }
}
