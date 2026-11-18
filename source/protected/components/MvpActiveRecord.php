<?php

/** WP01 persistence only. Authorization and workflow belong to later services. */
abstract class MvpActiveRecord extends CActiveRecord
{
    protected $editableFields = array();
    protected $requiredFields = array();
    protected $enumFields = array();
    protected $referenceFields = array();
    protected $jsonFields = array();
    private $_auditActorId;
    private $_loadedVersion;

    public static function model($className = null)
    {
        return parent::model($className === null ? get_called_class() : $className);
    }

    public function setAuditActorId($id)
    {
        if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1) {
            throw new CException('An explicit positive audit actor ID is required.');
        }
        $this->_auditActorId = (int)$id;
    }

    public function rules()
    {
        $rules = array();
        $required = $this->requiredFields;
        foreach ($this->getTableSchema()->columns as $name => $column) {
            if (!$column->allowNull && !$column->autoIncrement && $column->defaultValue === null) {
                $required[] = $name;
            }
            if (preg_match('/^int\b/i', $column->dbType)) {
                $rules[] = array($name, 'numerical', 'integerOnly'=>true, 'min'=> $name === 'attempt_count' ? 0 : 1,
                    'max'=>strpos($column->dbType, 'unsigned') !== false ? 4294967295 : 2147483647, 'safe'=>true);
            } elseif (preg_match('/^varchar\((\d+)\)/i', $column->dbType, $m)) {
                $rules[] = array($name, 'length', 'max'=>(int)$m[1], 'safe'=>true);
            } elseif (in_array($column->dbType, array('date','datetime'), true)) {
                $rules[] = array($name, 'validateDateValue', 'safe'=>true);
            }
        }
        if ($required) { $rules[] = array(implode(',', array_unique($required)), 'required', 'safe'=>true); }
        foreach ($this->enumFields as $name => $range) {
            $rules[] = array($name, 'in', 'range'=>$range, 'allowEmpty'=>false, 'safe'=>true);
        }
        foreach ($this->referenceFields as $name => $class) {
            $rules[] = array($name, 'exist', 'className'=>$class, 'attributeName'=>'id', 'safe'=>true);
        }
        foreach ($this->jsonFields as $name) { $rules[] = array($name, 'validateJsonValue', 'safe'=>true); }
        foreach (array('created_by','updated_by','owner_user_id','assignee_user_id','recipient_user_id','user_id','approved_by','reviewed_by','completed_by','actor_user_id') as $name) {
            if ($this->hasAttribute($name)) {
                $rules[] = array($name, 'exist', 'className'=>'Users', 'attributeName'=>'id', 'safe'=>true);
            }
        }
        foreach (array('email') as $name) {
            if ($this->hasAttribute($name)) { $rules[] = array($name, 'email', 'safe'=>true); }
        }
        if ($this->hasAttribute('timezone')) { $rules[] = array('timezone', 'validateTimezone', 'safe'=>true); }
        if ($this->editableFields) { $rules[] = array(implode(',', $this->editableFields), 'safe'); }
        $protected = array_diff($this->attributeNames(), $this->editableFields);
        if ($protected) { $rules[] = array(implode(',', $protected), 'unsafe'); }
        return $rules;
    }

    protected function beforeValidate()
    {
        foreach ($this->getAttributes() as $name => $value) {
            if ($value !== null && !is_scalar($value)) {
                $this->addError($name, 'A scalar value is required.');
            }
        }
        if ($this->hasErrors()) { return false; }
        foreach ($this->getTableSchema()->columns as $name => $column) {
            if ($this->$name === '' && $column->allowNull && (preg_match('/^int\b/', $column->dbType) || in_array($column->dbType, array('date','datetime')))) {
                $this->$name = null;
            }
        }
        $actor = $this->_auditActorId;
        if ($actor === null && Yii::app()->hasComponent('user')) { $actor = Yii::app()->user->id; }
        foreach (array('created_by','updated_by') as $name) {
            if ($this->hasAttribute($name) && ($name === 'updated_by' || $this->isNewRecord) && $actor) {
                $this->$name = $actor;
            }
        }
        foreach (array('created_at_utc','updated_at_utc','occurred_at_utc') as $name) {
            if ($this->hasAttribute($name) && ($name === 'updated_at_utc' || ($this->isNewRecord && !$this->$name))) {
                $this->$name = gmdate('Y-m-d H:i:s');
            }
        }
        $this->prepareValidation();
        return parent::beforeValidate();
    }

    protected function prepareValidation() {}

    public static function validDate($value, $format)
    {
        if (!is_string($value)) { return false; }
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value, new DateTimeZone('UTC'));
        return $date && $date->format($format) === $value;
    }

    public function validateDateValue($attribute, $params)
    {
        $value = $this->$attribute;
        if ($value === null || $value === '') { return; }
        $format = $this->getTableSchema()->columns[$attribute]->dbType === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
        if (!self::validDate($value, $format)) { $this->addError($attribute, 'Invalid date; expected '.$format.'.'); }
    }

    public function validateJsonValue($attribute, $params)
    {
        if ($this->$attribute === null || $this->$attribute === '') { return; }
        json_decode($this->$attribute, true);
        if (json_last_error() !== JSON_ERROR_NONE) { $this->addError($attribute, 'Invalid JSON.'); }
    }

    public function validateTimezone($attribute, $params)
    {
        if (!in_array($this->$attribute, DateTimeZone::listIdentifiers(), true)) {
            $this->addError($attribute, 'An IANA timezone is required.');
        }
    }

    public function delete()
    {
        throw new CDbException('MVP records must be archived/cancelled by a service, not deleted.');
    }

    protected function afterFind()
    {
        if ($this->hasAttribute('lock_version')) { $this->_loadedVersion = $this->lock_version; }
        parent::afterFind();
    }

    protected function afterSave()
    {
        if ($this->hasAttribute('lock_version')) { $this->_loadedVersion = $this->lock_version; }
        parent::afterSave();
    }

    public function refresh()
    {
        if (!parent::refresh()) { return false; }
        if ($this->hasAttribute('lock_version')) { $this->_loadedVersion = $this->lock_version; }
        return true;
    }

    public function update($attributes = null)
    {
        if (!$this->hasAttribute('lock_version')) { return parent::update($attributes); }
        if ($this->isNewRecord || $this->_loadedVersion === null || $attributes !== null) {
            throw new CDbException('Versioned updates require a loaded full MVP record.');
        }
        if (!$this->beforeSave()) { return false; }
        $expected = $this->_loadedVersion;
        $this->lock_version = (int)$expected + 1;
        $builder = $this->getCommandBuilder();
        $table = $this->getTableSchema();
        $criteria = $builder->createPkCriteria($table, $this->getOldPrimaryKey(), 'lock_version=:expected', array(':expected'=>$expected));
        $affected = $builder->createUpdateCommand($table, $this->getAttributes(), $criteria)->execute();
        if ($affected !== 1) {
            $this->lock_version = $expected;
            throw new CDbException('MVP record was changed or removed by another writer.', 409);
        }
        $this->setOldPrimaryKey($this->getPrimaryKey());
        $this->afterSave();
        return true;
    }
}
