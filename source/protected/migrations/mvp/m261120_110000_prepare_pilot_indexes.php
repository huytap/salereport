<?php
/** Measured WP09 read paths; additive indexes only, guarded to test DBs. */
class m261120_110000_prepare_pilot_indexes extends CDbMigration
{
    private function db()
    {
        $db=$this->getDbConnection();
        if (!($db instanceof IsolatedTestDbConnection) || $db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString) || !$db->schema->getTable('crm_cutover')) { throw new CDbException('WP09 indexes require WP08 on an isolated test DB.'); }
        return $db;
    }
    private function indexes() { return array('ix_account_pilot_list'=>array('crm_account','status,name,id'),'ix_task_pilot_dates'=>array('crm_task','kind,due_mode,due_date,id'),'ix_task_pilot_due'=>array('crm_task','deadline_at_utc,id')); }
    public function up()
    {
        $db=$this->db();
        foreach ($this->indexes() as $name=>$v) { if ($db->createCommand('SHOW INDEX FROM '.$v[0].' WHERE Key_name=:name')->queryAll(true,array(':name'=>$name))) { throw new CDbException('WP09 index already exists; inspect migration state.'); } }
        foreach ($this->indexes() as $name=>$v) { $this->createIndex($name,$v[0],$v[1]); }
        $db->schema->refresh();
    }
    public function down()
    {
        $db=$this->db();
        // Refuse to remove a manually changed index with the same name.
        foreach ($this->indexes() as $name=>$v) {
            $rows=$db->createCommand('SHOW INDEX FROM '.$v[0].' WHERE Key_name=:name')->queryAll(true,array(':name'=>$name));
            usort($rows,function($a,$b){ return (int)$a['Seq_in_index']-(int)$b['Seq_in_index']; });
            if (implode(',',array_column($rows,'Column_name'))!==$v[1] || array_filter($rows,function($r){ return !(int)$r['Non_unique'] || $r['Sub_part']!==null; })) { throw new CDbException('WP09 index changed/missing; inspect before rollback.'); }
        }
        foreach ($this->indexes() as $name=>$v) { $this->dropIndex($name,$v[0]); } $db->schema->refresh();
    }
}
