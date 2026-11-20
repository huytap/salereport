<?php
/** Read-only operations preflight. No scheduler, flag, role or data mutation. */
class PilotCommand extends CConsoleCommand
{
    public function actionCheck($actor)
    {
        $db=CutoverService::testDb(); $u=new PilotConsoleActor; $u->id=CustomerWriteSupport::id($actor); Yii::app()->setComponent('user',$u); CutoverService::admin('cutover');
        $expected='m261120_110000_prepare_pilot_indexes'; $latest=$db->createCommand('SELECT version FROM crm_migration ORDER BY apply_time DESC,version DESC LIMIT 1')->queryScalar();
        $indexes=array();
        foreach (array('ix_account_pilot_list'=>array('crm_account','status,name,id'),'ix_task_pilot_dates'=>array('crm_task','kind,due_mode,due_date,id'),'ix_task_pilot_due'=>array('crm_task','deadline_at_utc,id')) as $name=>$spec) {
            $rows=$db->createCommand('SHOW INDEX FROM '.$spec[0].' WHERE Key_name=:name')->queryAll(true,array(':name'=>$name)); usort($rows,function($a,$b){return (int)$a['Seq_in_index']-(int)$b['Seq_in_index'];});
            $indexes[$name]=implode(',',array_column($rows,'Column_name'))===$spec[1] && !array_filter($rows,function($r){return !(int)$r['Non_unique'] || $r['Sub_part']!==null;});
        }
        $engines=$db->createCommand("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('reports','crm_account','crm_task','crm_notification','crm_task_reminder','crm_customer_request','crm_cutover') ORDER BY TABLE_NAME")->queryAll();
        $transactional=count($engines)===7; foreach ($engines as $r) { if (strtolower($r['ENGINE'])!=='innodb') { $transactional=false; } }
        $flags=Yii::app()->params['mvp']; $safeFlags=array(); foreach (array('customerWrite','reportWrite','taskWrite','reminders','legacyImportEnabled') as $f) { $safeFlags[$f]=!empty($flags[$f]); }
        $schemaOk=$latest===$expected && !in_array(false,$indexes,true) && $transactional;
        echo CJSON::encode(array('read_only'=>true,'database'=>$db->createCommand('SELECT DATABASE()')->queryScalar(),'schema_ok'=>$schemaOk,'latest_migration'=>$latest,'expected_migration'=>$expected,'indexes'=>$indexes,'transactional_tables'=>$engines,'flags'=>$safeFlags,'timezone'=>isset($flags['timezone'])?$flags['timezone']:null,'runtime_writable'=>is_writable(Yii::app()->runtimePath),'cutover'=>CutoverService::readiness(),'reminder'=>ReminderDispatchService::check(),'scheduler'=>'not_inspected_or_modified','production_pilot'=>'not_authorized_by_this_check'))."\n";
        return $schemaOk?0:1;
    }
}
class PilotConsoleActor extends CApplicationComponent
{
    public $id;
    public function getIsGuest() { return $this->id===null; }
}
