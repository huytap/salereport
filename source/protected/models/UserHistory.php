<?php

class UserHistory extends CrmActiveRecord
{
    protected $requiredFields = array('user_id', 'login_time', 'ip_address');
    protected $listFields = array();

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'user_history';
    }

    public function relations()
    {
        return array(
            'user' => array(self::BELONGS_TO, 'Users', 'user_id'),
        );
    }
    protected function searchCriteria()
    {
        $criteria = parent::searchCriteria();
        if (!ActionAccess::isAll()) { $criteria->compare('t.user_id',Yii::app()->user->id ?: -1); }
        return $criteria;
    }
}
