<?php
class m261119_130000_prepare_reminder_dispatch extends CDbMigration
{
    private function db()
    {
        $db=$this->getDbConnection();
        if (!($db instanceof IsolatedTestDbConnection) || $db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CDbException('WP07 requires the isolated test DB.'); }
        return $db;
    }
    public function up()
    {
        $db=$this->db();
        if (!$db->schema->getTable('crm_report_schema_seed') || $db->schema->getTable('crm_reminder_dispatch_run',true) || $db->schema->getTable('crm_reminder_action_seed',true)) { throw new CDbException('Apply WP06 first; WP07 tables must not already exist.'); }
        $this->execute("CREATE TABLE crm_reminder_dispatch_run (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            run_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            started_at_utc DATETIME NOT NULL, heartbeat_at_utc DATETIME NOT NULL, finished_at_utc DATETIME NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'running',
            selected_count INT UNSIGNED NOT NULL DEFAULT 0, processed_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_count INT UNSIGNED NOT NULL DEFAULT 0, recovered_count INT UNSIGNED NOT NULL DEFAULT 0,
            cancelled_count INT UNSIGNED NOT NULL DEFAULT 0, skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
            error_count INT UNSIGNED NOT NULL DEFAULT 0, duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            error_code VARCHAR(64) NULL,
            UNIQUE KEY uq_dispatch_run_key(run_key), KEY ix_dispatch_heartbeat(status,heartbeat_at_utc,id), KEY ix_dispatch_success(status,finished_at_utc,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->createTable('crm_reminder_action_seed',array('role_name'=>'VARCHAR(64) NOT NULL PRIMARY KEY','before_rights'=>'TEXT NOT NULL','after_rights'=>'TEXT NOT NULL'),'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $all=array_map(array('ActionAccess','names'),Yii::app()->params['mvp']['globalScopeRoles']); $names=array(); foreach ($all as $list) { $names=array_merge($names,$list); }
        foreach ($db->createCommand('SELECT role_name,access_rights FROM roles')->queryAll() as $row) {
            $rights=json_decode($row['access_rights'],true);
            if (!in_array(mb_strtolower(trim($row['role_name']),'UTF-8'),$names,true) || !isset($rights['reminder']) || !is_array($rights['reminder']) || !in_array('admin',$rights['reminder'],true)) { continue; }
            $rights['reminder']=array_values(array_unique(array_merge($rights['reminder'],array('monitor')))); $after=json_encode($rights); if ($after===$row['access_rights']) { continue; }
            $db->createCommand()->insert('crm_reminder_action_seed',array('role_name'=>$row['role_name'],'before_rights'=>$row['access_rights'],'after_rights'=>$after));
            if (!$db->createCommand()->update('roles',array('access_rights'=>$after),'role_name=:name AND access_rights=:before',array(':name'=>$row['role_name'],':before'=>$row['access_rights']))) { throw new CDbException('Reminder rights changed during seed; inspect journal.'); }
        }
        $db->schema->refresh();
    }
    public function down()
    {
        $db=$this->db(); if ($db->createCommand('SELECT COUNT(*) FROM crm_reminder_dispatch_run')->queryScalar()) { throw new CDbException('Refusing rollback: dispatch history exists.'); }
        $rows=$db->createCommand('SELECT * FROM crm_reminder_action_seed')->queryAll();
        foreach ($rows as $row) { if ($db->createCommand('SELECT access_rights FROM roles WHERE role_name=:name')->queryScalar(array(':name'=>$row['role_name']))!==$row['after_rights']) { throw new CDbException('Refusing rollback: reminder rights changed.'); } }
        foreach ($rows as $row) { $db->createCommand()->update('roles',array('access_rights'=>$row['before_rights']),'role_name=:name',array(':name'=>$row['role_name'])); }
        $this->dropTable('crm_reminder_action_seed'); $this->dropTable('crm_reminder_dispatch_run'); $db->schema->refresh();
    }
}
