<?php

class Account extends MvpActiveRecord
{
    protected $editableFields = array('name', 'segment', 'biz_scope', 'website', 'phone', 'email', 'building', 'address_1', 'address_2', 'address_3', 'city', 'country', 'source', 'fit_series', 'dmc', 'notes');
    protected $requiredFields = array('code', 'name');
    protected $enumFields = array('segment'=>array('', 'LEISURE', 'CORPORATE', 'OTA'), 'status'=>array('active', 'archived'));

    public function tableName() { return 'crm_account'; }

    public function relations()
    {
        return array(
            'contacts' => array(self::HAS_MANY, 'ContactPerson', array('account_id'=>'id')),
            'assignments' => array(self::HAS_MANY, 'AccountHotel', array('account_id'=>'id')),
            'markets' => array(self::HAS_MANY, 'AccountMarket', array('account_id'=>'id')),
            'reports' => array(self::HAS_MANY, 'Report', array('account_id'=>'id')),
            'tasks' => array(self::HAS_MANY, 'Task', array('account_id'=>'id')),
        );
    }

    protected function prepareValidation()
    {
        $this->name = trim($this->name);
        $this->normalized_name = mb_strtolower(preg_replace('/\s+/u', ' ', $this->name), 'UTF-8');
    }
}
