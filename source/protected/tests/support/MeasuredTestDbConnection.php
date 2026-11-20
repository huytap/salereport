<?php
/** Counts actual command executions without retaining a profiler log for every SQL. Test-only. */
class MeasuredTestDbConnection extends IsolatedTestDbConnection
{
    public $statementCount=0;
    public function createCommand($query=null) { $this->active=true; return new MeasuredTestCommand($this,$query); }
}
class MeasuredTestCommand extends CDbCommand
{
    private function counted() { $this->connection->statementCount++; }
    public function execute($params=array()) { $this->counted(); return parent::execute($params); }
    public function query($params=array()) { $this->counted(); return parent::query($params); }
    public function queryAll($fetchAssociative=true,$params=array()) { $this->counted(); return parent::queryAll($fetchAssociative,$params); }
    public function queryRow($fetchAssociative=true,$params=array()) { $this->counted(); return parent::queryRow($fetchAssociative,$params); }
    public function queryScalar($params=array()) { $this->counted(); return parent::queryScalar($params); }
    public function queryColumn($params=array()) { $this->counted(); return parent::queryColumn($params); }
}
