<?php

class Trackings extends CrmActiveRecord
{
    protected $requiredFields = array('type', 'object_id', 'object_name', 'date', 'user_id');
    protected $listFields = array();

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'trackings';
    }

    public function relations()
    {
        return array(
            'user' => array(self::BELONGS_TO, 'Users', 'user_id'),
        );
    }
}
