<?php

class Contact extends CrmActiveRecord
{
    protected $requiredFields = array('company');
    public $attachment;
    protected $listFields = array('hotel_produced', 'market');

    public function rules()
    {
        return array_merge(parent::rules(), array(
            array('attachment', 'file', 'types' => 'csv', 'maxSize' => 5 * 1024 * 1024,
                'allowEmpty' => false, 'on' => 'import'),
        ));
    }

    protected function beforeValidate()
    {
        foreach (array('validity_from', 'validity_to', 'dob') as $name) {
            $value = $this->$name;
            if (!is_string($value) || $value === '') {
                continue;
            }
            foreach (array('Y-m-d', 'd-M-Y', 'd-m-Y', 'd/m/Y') as $format) {
                $date = DateTime::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value) {
                    $this->$name = $date->format('Y-m-d');
                    break;
                }
            }
        }
        return parent::beforeValidate();
    }

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'contact';
    }

    public function relations()
    {
        return array(
            'updatedBy' => array(self::BELONGS_TO, 'Users', 'updated_by'),
        );
    }

    public function getList()
    {
        $provider = $this->search();
        $provider->setPagination(false);
        return $provider;
    }

    public function checkDataByEmail($email)
    {
        return $this->exists('email = :email', array(':email' => trim($email)));
    }
}
