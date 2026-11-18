<?php

class CustomerImportRow extends MvpActiveRecord
{
    protected $requiredFields = array('raw_json');
    protected $enumFields = array('result_state'=>array('created', 'linked', 'skipped', 'error', 'needs_review'));
    protected $referenceFields = array('batch_id'=>'CustomerImportBatch', 'result_account_id'=>'Account', 'result_contact_id'=>'ContactPerson', 'result_task_id'=>'Task');
    protected $jsonFields = array('raw_json', 'proposed_json', 'decision_json', 'result_json');

    public function tableName() { return 'crm_import_row'; }

    public function relations()
    {
        return array(
            'batch' => array(self::BELONGS_TO, 'CustomerImportBatch', array('batch_id'=>'id')),
        );
    }

    protected function prepareValidation()
    {
        if ($this->result_contact_id) {
            $person = ContactPerson::model()->findByPk($this->result_contact_id);
            if ($person && (string)$person->account_id !== (string)$this->result_account_id) {
                $this->addError('result_contact_id','Import contact/account mismatch.');
            }
        }
        if ($this->result_task_id) {
            $task = Task::model()->findByPk($this->result_task_id);
            if ($task && (string)$task->account_id !== (string)$this->result_account_id) {
                $this->addError('result_task_id','Import task/account mismatch.');
            }
        }
    }

}
