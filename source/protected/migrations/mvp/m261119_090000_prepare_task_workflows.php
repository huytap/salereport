<?php
class m261119_090000_prepare_task_workflows extends CDbMigration
{
    private function db()
    {
        $db=$this->getDbConnection();
        if (!($db instanceof IsolatedTestDbConnection) || $db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CDbException('WP05 requires the isolated test DB.'); }
        return $db;
    }
    public function up()
    {
        $db=$this->db();
        if (!$db->schema->getTable('crm_customer_request') || $db->schema->getTable('crm_task_action_seed',true)) { throw new CDbException('Apply WP04 first; WP05 journal must not already exist.'); }
        if ($db->createCommand("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='crm_task' AND COLUMN_NAME='client_request_key'")->queryScalar()!=='utf8mb4_unicode_ci') { throw new CDbException('Unexpected task request-key collation; inspect before migrating.'); }
        $this->execute('ALTER TABLE crm_task MODIFY client_request_key VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
        $this->createTable('crm_task_action_seed',array('role_name'=>'VARCHAR(64) NOT NULL PRIMARY KEY','before_rights'=>'TEXT NOT NULL','after_rights'=>'TEXT NOT NULL'),'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        foreach ($db->createCommand('SELECT role_name,access_rights FROM roles')->queryAll() as $row) {
            $rights=json_decode($row['access_rights'],true);
            if (!isset($rights['task']) || !is_array($rights['task']) || !in_array('update',$rights['task'],true)) { continue; }
            $rights['task']=array_values(array_unique(array_merge($rights['task'],array('start','reschedule')))); $after=json_encode($rights);
            if ($after===$row['access_rights']) { continue; }
            $db->createCommand()->insert('crm_task_action_seed',array('role_name'=>$row['role_name'],'before_rights'=>$row['access_rights'],'after_rights'=>$after));
            if (!$db->createCommand()->update('roles',array('access_rights'=>$after),'role_name=:name AND access_rights=:before',array(':name'=>$row['role_name'],':before'=>$row['access_rights']))) { throw new CDbException('Role changed during WP05 seed; inspect journal.'); }
        }
        $db->schema->refresh();
    }
    public function down()
    {
        $db=$this->db();
        if ($db->createCommand("SELECT COUNT(*) FROM crm_customer_request WHERE operation LIKE 'task\\_%'")->queryScalar()) { throw new CDbException('Refusing rollback: task requests exist.'); }
        $rows=$db->createCommand('SELECT * FROM crm_task_action_seed')->queryAll();
        foreach ($rows as $row) { if ($db->createCommand('SELECT access_rights FROM roles WHERE role_name=:name')->queryScalar(array(':name'=>$row['role_name']))!==$row['after_rights']) { throw new CDbException('Refusing rollback: task rights changed.'); } }
        if ($db->createCommand('SELECT COUNT(*) FROM (SELECT created_by,client_request_key COLLATE utf8mb4_unicode_ci AS request_value,COUNT(*) AS n FROM crm_task GROUP BY created_by,request_value HAVING n>1) conflicts')->queryScalar()) { throw new CDbException('Refusing rollback: task keys would collide in old collation.'); }
        $this->execute('ALTER TABLE crm_task MODIFY client_request_key VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
        foreach ($rows as $row) { $db->createCommand()->update('roles',array('access_rights'=>$row['before_rights']),'role_name=:name',array(':name'=>$row['role_name'])); }
        $this->dropTable('crm_task_action_seed'); $db->schema->refresh();
    }
}
