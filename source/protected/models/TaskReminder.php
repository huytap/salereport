<?php

class TaskReminder extends MvpActiveRecord
{
    protected $enumFields = array('reminder_kind'=>array('before', 'due_day', 'overdue'), 'state'=>array('pending', 'delivered', 'cancelled'));
    protected $referenceFields = array('task_id'=>'Task');

    public function tableName() { return 'crm_task_reminder'; }

    public function relations()
    {
        return array(
            'task' => array(self::BELONGS_TO, 'Task', array('task_id'=>'id')),
            'recipient' => array(self::BELONGS_TO, 'Users', array('recipient_user_id'=>'id')),
            'notification' => array(self::HAS_ONE, 'Notification', array('reminder_id'=>'id')),
        );
    }

    protected function prepareValidation()
    {
        if ($this->state === 'delivered' && !$this->delivered_at_utc) {
            $this->addError('delivered_at_utc','Delivered reminders require a delivery timestamp.');
        }
        if ($this->task_id && $this->recipient_user_id && $this->schedule_version) {
            $task = Task::model()->findByPk($this->task_id);
            // Old delivered/cancelled reminders legitimately keep their previous recipient/version.
            if ($task && $this->state === 'pending' && ((string)$task->assignee_user_id !== (string)$this->recipient_user_id || (string)$task->schedule_version !== (string)$this->schedule_version)) {
                $this->addError('task_id','Pending reminder context does not match the task.');
            }
        }
    }
}
