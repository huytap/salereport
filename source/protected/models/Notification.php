<?php

class Notification extends MvpActiveRecord
{
    protected $enumFields = array('kind'=>array('before', 'due_day', 'overdue'));
    protected $referenceFields = array('task_id'=>'Task', 'reminder_id'=>'TaskReminder');

    public function tableName() { return 'crm_notification'; }

    public function relations()
    {
        return array(
            'task' => array(self::BELONGS_TO, 'Task', array('task_id'=>'id')),
            'reminder' => array(self::BELONGS_TO, 'TaskReminder', array('reminder_id'=>'id')),
            'recipient' => array(self::BELONGS_TO, 'Users', array('recipient_user_id'=>'id')),
        );
    }

    protected function prepareValidation()
    {
        if ($this->reminder_id) {
            $reminder = TaskReminder::model()->findByPk($this->reminder_id);
            if ($reminder && ((string)$reminder->task_id !== (string)$this->task_id || (string)$reminder->recipient_user_id !== (string)$this->recipient_user_id || (string)$reminder->schedule_version !== (string)$this->schedule_version || $reminder->reminder_kind !== $this->kind)) {
                $this->addError('reminder_id','Notification context does not match its reminder.');
            }
        }
    }
}
