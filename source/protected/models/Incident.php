<?php

class Incident extends CrmActiveRecord
{
    protected $requiredFields = array('date', 'guest_name');
    protected $listFields = array('hotels');

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'incident';
    }

    public function relations()
    {
        return array(
            'updatedBy' => array(self::BELONGS_TO, 'Users', 'updated_by'),
        );
    }
}
