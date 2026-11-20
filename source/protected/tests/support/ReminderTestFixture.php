<?php
/** Committed synthetic records, deleted only by owned IDs. Worker tests never share the UI schema. */
class ReminderTestFixture
{
    public $prefix; public $accounts=array(); public $hotels=array(); public $runs=array(); public $users=array();
    public function __construct()
    {
        $db=ReminderDispatchService::testDb(); $name=$db->createCommand('SELECT DATABASE()')->queryScalar();
        if (!preg_match('/^sale_report_mvp_test_reminder(?:_[a-z0-9]+)*$/D',$name) || $db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Use a dedicated synthetic sale_report_mvp_test_reminder schema.'); }
        foreach (array('crm_account','crm_task','crm_task_reminder','crm_notification','crm_reminder_dispatch_run') as $table) { if ($db->createCommand('SELECT COUNT(*) FROM '.$table)->queryScalar()) { throw new Exception('Worker fixture requires an empty dedicated schema; no existing data was changed.'); } }
        $this->prefix='WP07 '.bin2hex(random_bytes(6));
    }
    public function hotel($suffix)
    {
        $h=new Hotel; $h->code='R7-'.bin2hex(random_bytes(6)); $h->name=$this->prefix.' '.$suffix; if (!$h->save()) { throw new Exception(CJSON::encode($h->errors)); } $this->hotels[]=$h->id; return $h;
    }
    public function account($hotelId,$owner,$suffix)
    {
        $a=AccountService::create(array('name'=>$this->prefix.' '.$suffix),$hotelId,$owner,null,array(),bin2hex(random_bytes(16))); $this->accounts[]=$a['account_id']; return $a['account_id'];
    }
    public function task($accountId,$hotelId,$title,$extra=array())
    {
        $fields=array_merge(array('title'=>$this->prefix.' '.$title,'kind'=>'task','due_mode'=>'datetime','due_date'=>null,'due_at_utc'=>gmdate('Y-m-d H:i:s',time()+1200),'starts_at_utc'=>null,'ends_at_utc'=>null,'timezone'=>'Asia/Ho_Chi_Minh'),$extra);
        $r=TaskService::create($accountId,$hotelId,$fields,bin2hex(random_bytes(16))); return Task::model()->findByPk($r['task_id']);
    }
    public function due(Task $task)
    {
        $r=TaskReminder::model()->find("task_id=:id AND state='pending' AND reminder_kind='before'",array(':id'=>$task->id)); if (!$r) { throw new Exception('Expected immediate before slot.'); } return $r;
    }
    public function dispatch($limit=200,$dry=false)
    {
        $result=ReminderDispatchService::dispatch($limit,$dry); if (!$dry) { $this->runs[]=$result['run_id']; } return $result;
    }
    public function cleanup()
    {
        $db=Yii::app()->db; $tx=$db->beginTransaction();
        try {
            foreach ($this->accounts as $accountId) {
                foreach ($db->createCommand('SELECT id FROM crm_task WHERE account_id=:id')->queryColumn(array(':id'=>$accountId)) as $id) {
                    foreach (array('crm_notification','crm_task_reminder') as $table) { $db->createCommand()->delete($table,'task_id=:id',array(':id'=>$id)); }
                    $db->createCommand()->delete('crm_audit_event',"entity_type='task' AND entity_id=:id",array(':id'=>(string)$id));
                }
                $db->createCommand()->delete('crm_task','account_id=:id',array(':id'=>$accountId));
                foreach ($db->createCommand('SELECT id FROM crm_contact_person WHERE account_id=:id')->queryColumn(array(':id'=>$accountId)) as $id) { $db->createCommand()->delete('crm_audit_event',"entity_type='contact_person' AND entity_id=:id",array(':id'=>(string)$id)); }
                $db->createCommand()->delete('crm_audit_event',"(entity_type='account' AND entity_id=:id) OR (entity_type='account_hotel' AND entity_id LIKE :pair)",array(':id'=>(string)$accountId,':pair'=>$accountId.':%'));
                foreach (array('crm_customer_request','crm_contact_person','crm_account_market','crm_account_hotel') as $table) { $db->createCommand()->delete($table,'account_id=:id',array(':id'=>$accountId)); } $db->createCommand()->delete('crm_account','id=:id',array(':id'=>$accountId));
            }
            foreach ($this->hotels as $id) { $db->createCommand()->delete('crm_user_hotel_access','hotel_id=:id',array(':id'=>$id)); $db->createCommand()->delete('crm_hotel','id=:id',array(':id'=>$id)); }
            foreach (array_unique($this->runs) as $id) { $db->createCommand()->delete('crm_reminder_dispatch_run','id=:id',array(':id'=>$id)); }
            $tx->commit();
            foreach ($this->users as $id) { $db->createCommand()->delete('users','id=:id',array(':id'=>$id)); }
        } catch (Exception $e) { $tx->rollback(); throw $e; }
    }
}
