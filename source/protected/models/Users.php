<?php

class Users extends CrmActiveRecord
{
    public $new_password;
    public $old_password;
    public $confirm_new_password;
    protected $requiredFields = array('username', 'fullname');
    protected $listFields = array('roles', 'hotel', 'group');

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'users';
    }

    public function rules()
    {
        return array_merge(parent::rules(), array(
            array('username', 'unique', 'except' => 'search'),
            array('email', 'email', 'except' => 'search'),
            array('status', 'in', 'range' => array('P', 'A', 'I'), 'except' => 'search'),
            array('password', 'required', 'on' => 'insert, create'),
            array('password', 'unsafe', 'on' => 'update, changepassword, search'),
            array('new_password, old_password, confirm_new_password', 'validateScalar', 'except' => 'search'),
            array('new_password, old_password, confirm_new_password', 'length', 'max' => 128, 'skipOnError' => true, 'except' => 'search'),
            array('new_password', 'safe', 'on' => 'update'),
            array('old_password, new_password, confirm_new_password', 'required', 'on' => 'changepassword'),
            array('confirm_new_password', 'compare', 'compareAttribute' => 'new_password', 'on' => 'changepassword'),
            array('old_password', 'validateOldPassword', 'on' => 'changepassword'),
        ));
    }

    public function validateOldPassword($attribute, $params)
    {
        if (!is_string($this->$attribute) || !hash_equals((string)$this->password, sha1(md5($this->$attribute)))) {
            $this->addError($attribute, 'Old password is incorrect.');
        }
    }

    protected function beforeSave()
    {
        if (!parent::beforeSave()) {
            return false;
        }
        if (is_string($this->new_password) && $this->new_password !== '') {
            $this->password = sha1(md5($this->new_password));
        } elseif (is_string($this->password) && !preg_match('/^[a-f0-9]{40}$/i', $this->password)) {
            // Existing UsersController already hashes passwords before calling save().
            $this->password = sha1(md5($this->password));
        }
        return true;
    }

    public function relations()
    {
        return array(
            'history' => array(self::HAS_MANY, 'UserHistory', 'user_id'),
            'reports' => array(self::HAS_MANY, 'Report', 'added_by'),
            'assessments' => array(self::HAS_MANY, 'StaffAsst', 'user_id'),
            'trackings' => array(self::HAS_MANY, 'Trackings', 'user_id'),
        );
    }

    protected function searchCriteria()
    {
        $criteria = parent::searchCriteria();
        if (!ActionAccess::isAll()) { $criteria->compare('t.id',Yii::app()->user->id ?: -1); }
        return $criteria;
    }

    public function getList()
    {
        return CHtml::listData($this->findAll($this->scopedOptionsCriteria('username')), 'id', 'username');
    }

    private function scopedOptionsCriteria($order)
    {
        $criteria = CustomerPolicy::visibleUsersCriteria();
        $criteria->order = 't.'.$order.' ASC';
        return $criteria;
    }

    public function getList2()
    {
        return CHtml::listData($this->findAll($this->scopedOptionsCriteria('fullname')), 'id', 'fullname');
    }

    public function getByUsername($username)
    {
        return $this->findByAttributes(array('username' => trim($username)));
    }

    public function getUsernameById($id)
    {
        $user = $this->findByPk($id);
        return $user ? $user->username : '';
    }

    public function getUsernameById2($id)
    {
        $user = $this->findByPk($id);
        return $user ? ($user->fullname !== '' ? $user->fullname : $user->username) : '';
    }

    public function checkByEmail($email)
    {
        return $this->exists('email = :email', array(':email' => trim($email)));
    }

    public function attributeLabels()
    {
        return array_merge(parent::attributeLabels(), array(
            'fullname' => 'Full name', 'cellphone' => 'Cellphone',
            'new_password' => 'New password', 'old_password' => 'Old password',
            'confirm_new_password' => 'Confirm new password',
        ));
    }
}
