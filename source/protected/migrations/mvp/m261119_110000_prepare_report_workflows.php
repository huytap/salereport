<?php
/** WP06 is restricted to isolated schemas; preserve legacy values and schema metadata. */
class m261119_110000_prepare_report_workflows extends CDbMigration
{
    private function db()
    {
        $db=$this->getDbConnection();
        if (!($db instanceof IsolatedTestDbConnection) || $db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CDbException('WP06 requires the isolated test DB.'); }
        return $db;
    }
    private function ddl($sql)
    {
        $db=$this->db(); $mode=$db->createCommand('SELECT @@SESSION.sql_mode')->queryScalar();
        try { $db->createCommand('SET SESSION sql_mode=:mode')->execute(array(':mode'=>implode(',',array_diff(explode(',',$mode),array('NO_ZERO_DATE','NO_ZERO_IN_DATE'))))); $this->execute($sql); }
        finally { $db->createCommand('SET SESSION sql_mode=:mode')->execute(array(':mode'=>$mode)); $db->schema->refresh(); }
    }
    public function up()
    {
        $db=$this->db();
        if (!$db->schema->getTable('crm_task_action_seed') || $db->schema->getTable('crm_report_schema_seed',true) || $db->schema->getTable('crm_report_action_seed',true)) { throw new CDbException('Apply WP05 first; WP06 journals must not exist.'); }
        $schema=$db->schema->getTable('reports',true);
        foreach (array('snapshot_json','created_at_utc','updated_at_utc') as $column) { if (isset($schema->columns[$column])) { throw new CDbException('Pre-existing Report column: '.$column); } }
        $columns=$db->createCommand("SELECT COLUMN_NAME,COLUMN_TYPE,CHARACTER_SET_NAME,COLLATION_NAME,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND (CHARACTER_SET_NAME IS NOT NULL OR COLUMN_NAME IN ('sales','site_ins')) ORDER BY ORDINAL_POSITION")->queryAll();
        $collation=$db->createCommand("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND ENGINE='InnoDB'")->queryScalar();
        if (!$collation) { throw new CDbException('reports must use InnoDB.'); }
        $this->createTable('crm_report_schema_seed',array('id'=>'INT NOT NULL PRIMARY KEY','columns_json'=>'MEDIUMTEXT NOT NULL','table_collation'=>'VARCHAR(64) NOT NULL'),'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->createCommand()->insert('crm_report_schema_seed',array('id'=>1,'columns_json'=>json_encode($columns),'table_collation'=>$collation));
        $this->ddl("ALTER TABLE reports CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
            MODIFY company_name VARCHAR(255) NULL, MODIFY last_name VARCHAR(128) NULL, MODIFY position VARCHAR(128) NULL,
            MODIFY email VARCHAR(200) NOT NULL, MODIFY company_address VARCHAR(1024) NOT NULL, MODIFY market VARCHAR(1300) NULL,
            MODIFY sales DATE NULL DEFAULT NULL, MODIFY site_ins DATE NULL DEFAULT NULL,
            ADD snapshot_json MEDIUMTEXT NULL, ADD created_at_utc DATETIME NULL, ADD updated_at_utc DATETIME NULL");
        $this->ddl('ALTER TABLE reports MODIFY client_request_key VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL');
        $this->createTable('crm_report_action_seed',array('role_name'=>'VARCHAR(64) NOT NULL PRIMARY KEY','before_rights'=>'TEXT NOT NULL','after_rights'=>'TEXT NOT NULL'),'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        foreach ($db->createCommand('SELECT role_name,access_rights FROM roles')->queryAll() as $row) {
            $rights=json_decode($row['access_rights'],true);
            if (!isset($rights['report'],$rights['account']) || !is_array($rights['report']) || !is_array($rights['account']) || !in_array('view',$rights['report'],true)) { continue; }
            foreach (array('create','update') as $action) { if (in_array($action,$rights['account'],true) && !in_array($action,$rights['report'],true)) { $rights['report'][]=$action; } }
            $after=json_encode($rights); if ($after===$row['access_rights']) { continue; }
            $db->createCommand()->insert('crm_report_action_seed',array('role_name'=>$row['role_name'],'before_rights'=>$row['access_rights'],'after_rights'=>$after));
            if (!$db->createCommand()->update('roles',array('access_rights'=>$after),'role_name=:name AND access_rights=:before',array(':name'=>$row['role_name'],':before'=>$row['access_rights']))) { throw new CDbException('Report rights changed during seed; inspect journal.'); }
        }
        $db->schema->refresh();
    }
    public function down()
    {
        $db=$this->db();
        if ($db->createCommand("SELECT COUNT(*) FROM crm_customer_request WHERE operation LIKE 'report\\_%'")->queryScalar() || $db->createCommand('SELECT COUNT(*) FROM reports WHERE snapshot_json IS NOT NULL OR created_at_utc IS NOT NULL OR updated_at_utc IS NOT NULL')->queryScalar()) { throw new CDbException('Refusing rollback: Report workflow data exists.'); }
        $roles=$db->createCommand('SELECT * FROM crm_report_action_seed')->queryAll();
        foreach ($roles as $row) { if ($db->createCommand('SELECT access_rights FROM roles WHERE role_name=:name')->queryScalar(array(':name'=>$row['role_name']))!==$row['after_rights']) { throw new CDbException('Refusing rollback: Report rights changed.'); } }
        $journal=$db->createCommand('SELECT * FROM crm_report_schema_seed WHERE id=1')->queryRow(); $parts=array();
        foreach (json_decode($journal['columns_json'],true) as $column) {
            $name=$db->quoteColumnName($column['COLUMN_NAME']);
            if ($column['IS_NULLABLE']==='NO' && $db->createCommand('SELECT COUNT(*) FROM reports WHERE '.$name.' IS NULL')->queryScalar()) { throw new CDbException('Refusing rollback: null values in '.$column['COLUMN_NAME']); }
            if ($column['CHARACTER_SET_NAME']) {
                $charset=$column['CHARACTER_SET_NAME']; $oldCollation=$column['COLLATION_NAME'];
                if (!preg_match('/^[a-zA-Z0-9_]+$/D',$charset.$oldCollation)) { throw new CDbException('Invalid schema journal.'); }
                if ($db->createCommand('SELECT COUNT(*) FROM reports WHERE BINARY '.$name.'<>BINARY CONVERT(CONVERT('.$name.' USING '.$charset.') USING utf8mb4)')->queryScalar()) { throw new CDbException('Refusing rollback: character conversion would lose Report data.'); }
                if (preg_match('/^(?:var)?char\((\d+)\)$/i',$column['COLUMN_TYPE'],$m) && $db->createCommand('SELECT COUNT(*) FROM reports WHERE CHAR_LENGTH('.$name.')>'.(int)$m[1])->queryScalar()) { throw new CDbException('Refusing rollback: Report snapshot exceeds old width.'); }
                $definition=$column['COLUMN_TYPE'].' CHARACTER SET '.$charset.' COLLATE '.$oldCollation;
            } else { $definition=$column['COLUMN_TYPE']; }
            $definition.=$column['IS_NULLABLE']==='YES'?' NULL':' NOT NULL';
            if ($column['COLUMN_DEFAULT']!==null) { $definition.=' DEFAULT '.$db->quoteValue($column['COLUMN_DEFAULT']); }
            elseif ($column['IS_NULLABLE']==='YES') { $definition.=' DEFAULT NULL'; }
            $parts[]='MODIFY '.$name.' '.$definition;
        }
        $old=$journal['table_collation']; if (!preg_match('/^[a-zA-Z0-9_]+$/D',$old)) { throw new CDbException('Invalid table collation journal.'); }
        $charset=explode('_',$old)[0];
        $this->ddl('ALTER TABLE reports DEFAULT CHARACTER SET '.$charset.' COLLATE '.$old.', '.implode(', ',$parts).', DROP snapshot_json, DROP created_at_utc, DROP updated_at_utc');
        foreach ($roles as $row) { $db->createCommand()->update('roles',array('access_rights'=>$row['before_rights']),'role_name=:name',array(':name'=>$row['role_name'])); }
        $this->dropTable('crm_report_action_seed'); $this->dropTable('crm_report_schema_seed'); $db->schema->refresh();
    }
}
