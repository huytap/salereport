<?php

class Role extends CrmActiveRecord
{
    protected $requiredFields = array('role_name', 'access_rights');
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'roles';
    }

    public function getNameUser($id)
    {
        $user = Users::model()->findByPk($id);
        return $user ? $user->roles : '';
    }

    public function getRoleName($name)
    {
        $role = $this->findByAttributes(array('role_name' => trim($name)));
        return $role ? $role->access_rights : '{}';
    }

    public function getNameRoleById($names)
    {
        $rights = array();
        foreach (explode(',', $names) as $name) {
            $decoded = json_decode($this->getRoleName($name), true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $controller => $actions) {
                if (is_array($actions)) {
                    $rights[$controller] = array_values(array_unique(array_merge(
                        isset($rights[$controller]) ? $rights[$controller] : array(), $actions
                    )));
                }
            }
        }
        return $rights;
    }

    public function rules()
    {
        return array_merge(parent::rules(), array(
            array('role_name', 'unique', 'except' => 'search'),
            array('access_rights', 'validateAccessRights', 'except' => 'search'),
        ));
    }

    public function validateAccessRights($attribute, $params)
    {
        $rights = is_string($this->$attribute) ? json_decode($this->$attribute, true) : null;
        if (!is_array($rights)) {
            $this->addError($attribute, 'Access rights must be a JSON object of action lists.');
            return;
        }
        foreach ($rights as $controller => $actions) {
            if (!is_string($controller) || !is_array($actions)) {
                $this->addError($attribute, 'Each controller must contain a list of actions.');
                return;
            }
            foreach ($actions as $action) {
                if (!is_string($action)) {
                    $this->addError($attribute, 'Action names must be strings.');
                    return;
                }
            }
        }
    }

    public function getAllRoleName()
    {
        return CHtml::listData($this->findAll(array('order' => 'role_name ASC')), 'role_name', 'role_name');
    }

    public function getNameRole()
    {
        return $this->getNameRoleById($this->role_name ?: Yii::app()->user->getState('type', ''));
    }

    public function relations()
    {
        return array('updatedBy' => array(self::BELONGS_TO, 'Users', 'updated_by'));
    }
}
