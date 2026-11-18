<?php

class ContactPerson extends MvpActiveRecord
{
    protected $editableFields = array('title', 'first_name', 'last_name', 'position', 'email', 'phone', 'mobile', 'dob', 'contact_role', 'notes');
    protected $enumFields = array('status'=>array('active', 'archived'));
    protected $referenceFields = array('account_id'=>'Account');

    public function tableName() { return 'crm_contact_person'; }

    public function relations()
    {
        return array(
            'account' => array(self::BELONGS_TO, 'Account', array('account_id'=>'id')),
        );
    }

    protected function prepareValidation()
    {
        if (!trim((string)$this->first_name) && !trim((string)$this->last_name)) {
            $this->addError('first_name','A contact person must have a name.');
        }
    }
}
