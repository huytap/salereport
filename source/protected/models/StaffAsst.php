<?php

class StaffAsst extends CrmActiveRecord
{
    protected $requiredFields = array('user_id', 'week', 'month', 'year');
    protected $listFields = array();

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'staff_asst';
    }

    public function relations()
    {
        return array(
            'user' => array(self::BELONGS_TO, 'Users', 'user_id'),
            'updatedBy' => array(self::BELONGS_TO, 'Users', 'updated_by'),
        );
    }

    public function rules()
    {
        return array_merge(parent::rules(), array(
            array('user_id', 'exist', 'className' => 'Users', 'attributeName' => 'id', 'except' => 'search'),
        ));
    }

    public function init()
    {
        parent::init();
        if ($this->isNewRecord && $this->scenario !== 'search') {
            $this->year = (int)date('Y');
        }
    }
}
