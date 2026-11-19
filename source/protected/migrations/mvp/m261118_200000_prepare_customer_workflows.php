<?php

class m261118_200000_prepare_customer_workflows extends CDbMigration
{
    private function db()
    {
        $db=$this->getDbConnection();
        if (!($db instanceof IsolatedTestDbConnection) || $db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CDbException('WP04 requires the isolated test DB.'); }
        return $db;
    }
    public function up()
    {
        $db=$this->db();
        foreach (array('crm_customer_request','crm_customer_action_seed') as $table) { if ($db->schema->getTable($table,true)) { throw new CDbException('Pre-existing WP04 table: '.$table); } }
        $this->execute("CREATE TABLE crm_customer_request (
            actor_user_id INT UNSIGNED NOT NULL, request_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            operation VARCHAR(32) NOT NULL, payload_hash CHAR(64) NOT NULL, account_id INT UNSIGNED NOT NULL,
            result_json MEDIUMTEXT NOT NULL, created_at_utc DATETIME NOT NULL,
            PRIMARY KEY(actor_user_id,request_key), KEY(account_id),
            CONSTRAINT fk_customer_request_account FOREIGN KEY(account_id) REFERENCES crm_account(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->createTable('crm_customer_action_seed',array('role_name'=>'VARCHAR(64) NOT NULL PRIMARY KEY','before_rights'=>'TEXT NOT NULL','after_rights'=>'TEXT NOT NULL'),'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        foreach ($db->createCommand('SELECT role_name,access_rights FROM roles')->queryAll() as $row) {
            $rights=json_decode($row['access_rights'],true);
            if (!isset($rights['account']) || !is_array($rights['account'])) { continue; }
            foreach (array('create'=>'contactcreate','update'=>'contactupdate','archive'=>'contactarchive','assign'=>'assignpreview') as $base=>$extra) {
                if (in_array($base,$rights['account'],true) && !in_array($extra,$rights['account'],true)) { $rights['account'][]=$extra; }
            }
            $after=json_encode($rights);
            if ($after===$row['access_rights']) { continue; }
            $db->createCommand()->insert('crm_customer_action_seed',array('role_name'=>$row['role_name'],'before_rights'=>$row['access_rights'],'after_rights'=>$after));
            if (!$db->createCommand()->update('roles',array('access_rights'=>$after),'role_name=:name AND access_rights=:before',array(':name'=>$row['role_name'],':before'=>$row['access_rights']))) { throw new CDbException('Role changed during WP04 seed; inspect journal.'); }
        }
        $db->schema->refresh();
    }
    public function down()
    {
        $db=$this->db();
        if ($db->createCommand('SELECT COUNT(*) FROM crm_customer_request')->queryScalar()) { throw new CDbException('Refusing rollback: customer requests exist.'); }
        $rows=$db->createCommand('SELECT * FROM crm_customer_action_seed')->queryAll();
        foreach ($rows as $row) { if ($db->createCommand('SELECT access_rights FROM roles WHERE role_name=:name')->queryScalar(array(':name'=>$row['role_name']))!==$row['after_rights']) { throw new CDbException('Refusing rollback: customer rights changed.'); } }
        foreach ($rows as $row) { $db->createCommand()->update('roles',array('access_rights'=>$row['before_rights']),'role_name=:name',array(':name'=>$row['role_name'])); }
        $this->dropTable('crm_customer_request'); $this->dropTable('crm_customer_action_seed'); $db->schema->refresh();
    }
}
