<?php

class LegacyLink extends MvpActiveRecord
{
    protected $requiredFields = array('source_type', 'source_id');
    protected $enumFields = array('resolution_state'=>array('linked', 'needs_review', 'skipped'));
    protected $referenceFields = array('account_id'=>'Account', 'contact_person_id'=>'ContactPerson', 'import_batch_id'=>'CustomerImportBatch');

    public function tableName() { return 'crm_legacy_link'; }

    public function relations()
    {
        return array(
            'account' => array(self::BELONGS_TO, 'Account', array('account_id'=>'id')),
            'contactPerson' => array(self::BELONGS_TO, 'ContactPerson', array('contact_person_id'=>'id')),
            'batch' => array(self::BELONGS_TO, 'CustomerImportBatch', array('import_batch_id'=>'id')),
        );
    }

    protected function prepareValidation()
    {
        if ($this->contact_person_id) {
            $person = ContactPerson::model()->findByPk($this->contact_person_id);
            if ($person && (string)$person->account_id !== (string)$this->account_id) {
                $this->addError('contact_person_id','Legacy contact/account mismatch.');
            }
        }
    }
}
