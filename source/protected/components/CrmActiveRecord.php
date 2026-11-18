<?php

/** Shared Yii 1 model support, using the actual database column definitions. */
abstract class CrmActiveRecord extends CActiveRecord
{
    protected $requiredFields = array();
    protected $listFields = array();
    private $_originalAttributes = array();

    public function rules()
    {
        $rules = array();
        $required = $this->requiredFields;
        $protected = array();
        foreach ($this->getTableSchema()->columns as $name => $column) {
            if ($column->autoIncrement || in_array($name, array('added_date', 'updated_date', 'added_by', 'updated_by'))) {
                $protected[] = $name;
            }
            $rules[] = array($name, 'validateScalar', 'except' => 'search');
            if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/i', $column->dbType)) {
                $rule = array($name, 'numerical', 'integerOnly' => true, 'except' => 'search', 'skipOnError' => true);
                if (strpos($column->dbType, 'unsigned') !== false) {
                    $rule['min'] = 0;
                    if (preg_match('/^int\b/i', $column->dbType)) {
                        $rule['max'] = 4294967295;
                    }
                } elseif (preg_match('/^int\b/i', $column->dbType)) {
                    $rule['min'] = -2147483648;
                    $rule['max'] = 2147483647;
                }
                $rules[] = $rule;
            } elseif (preg_match('/^(?:var)?char\((\d+)\)/i', $column->dbType, $match)) {
                $rules[] = array($name, 'length', 'max' => (int)$match[1], 'except' => 'search', 'skipOnError' => true);
            } elseif (in_array($column->dbType, array('date', 'datetime', 'timestamp'))) {
                $rules[] = array($name, 'validateDatabaseDate', 'except' => 'search');
                if (!$column->allowNull && $column->defaultValue === null) {
                    $required[] = $name;
                }
            }
        }
        if ($required) {
            $rules[] = array(implode(', ', array_unique($required)), 'required', 'except' => 'search');
        }
        $rules[] = array(implode(', ', $this->attributeNames()), 'safe', 'on' => 'search');
        // Text columns also need explicit safe rules for ordinary form assignment.
        $rules[] = array(implode(', ', array_diff($this->attributeNames(), $protected)), 'safe', 'except' => 'search');
        if ($protected) {
            $rules[] = array(implode(', ', $protected), 'unsafe', 'except' => 'search');
        }
        return $rules;
    }

    public function validateScalar($attribute, $params)
    {
        if ($this->$attribute !== null && !is_scalar($this->$attribute)) {
            $this->addError($attribute, $this->getAttributeLabel($attribute).' must be a scalar value.');
        }
    }

    public function validateDatabaseDate($attribute, $params)
    {
        $value = $this->$attribute;
        if ($value === null || $value === '') {
            return;
        }
        // Existing dumps contain zero dates; preserve them when updating other fields.
        if (!$this->isNewRecord && isset($this->_originalAttributes[$attribute])
            && $value === $this->_originalAttributes[$attribute]
            && in_array($value, array('0000-00-00', '0000-00-00 00:00:00'))) {
            return;
        }
        $format = $this->getTableSchema()->columns[$attribute]->dbType === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
        $date = is_string($value) ? DateTime::createFromFormat('!'.$format, $value) : false;
        if (!$date || $date->format($format) !== $value) {
            $this->addError($attribute, $this->getAttributeLabel($attribute).' must use '.$format.'.');
        }
    }

    protected function beforeValidate()
    {
        if ($this->scenario !== 'search') {
            foreach ($this->listFields as $name) {
                if (is_array($this->$name)) {
                    foreach ($this->$name as $value) {
                        if (!is_scalar($value)) {
                            $this->addError($name, 'Invalid list value.');
                            return false;
                        }
                    }
                    $this->$name = implode(',', $this->$name);
                }
            }
            foreach ($this->getTableSchema()->columns as $name => $column) {
                $integer = preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/i', $column->dbType);
                $dateColumn = in_array($column->dbType, array('date', 'datetime', 'timestamp'));
                if ($this->$name === '' && $column->allowNull && ($integer || $dateColumn)) {
                    $this->$name = null;
                }
                if (in_array($name, array('added_date', 'updated_date'))) {
                    if ($name === 'updated_date' || $this->isNewRecord) {
                        $this->$name = date($column->dbType === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s');
                    }
                } elseif (in_array($name, array('added_by', 'updated_by'))) {
                    if ($name === 'updated_by' || ($this->isNewRecord && !$this->$name)) {
                        $user = Yii::app()->getComponent('user');
                        $this->$name = $user ? (int)$user->id : 0;
                    }
                } elseif (($this->$name === null || ($integer && $this->$name === ''))
                    && !$column->allowNull && !$column->autoIncrement && $column->defaultValue === null) {
                    // SQL NOT NULL strings may legally be empty. Do not invent dates.
                    if ($integer && !in_array($name, $this->requiredFields)) {
                        $this->$name = 0;
                    } elseif ($column->type === 'string' && !$dateColumn && !$integer) {
                        $this->$name = '';
                    }
                }
            }
        }
        return parent::beforeValidate();
    }

    protected function searchCriteria()
    {
        $criteria = new CDbCriteria;
        foreach ($this->getTableSchema()->columns as $name => $column) {
            if ($name === 'password') {
                continue;
            }
            $value = $this->$name;
            if (!is_scalar($value) && $value !== null) {
                $criteria->addCondition('1=0');
                continue;
            }
            if ($column->dbType === 'date' && $value !== null && $value !== '') {
                $normalized = false;
                foreach (array('Y-m-d', 'd-M-Y', 'd-m-Y', 'd/m/Y') as $format) {
                    $date = DateTime::createFromFormat('!'.$format, (string)$value);
                    if ($date && $date->format($format) === (string)$value) {
                        $value = $date->format('Y-m-d');
                        $normalized = true;
                        break;
                    }
                }
                if (!$normalized) {
                    $criteria->addCondition('1=0');
                    continue;
                }
            }
            $partial = $column->type === 'string' && !preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/i', $column->dbType);
            $criteria->compare('t.'.Yii::app()->db->quoteColumnName($name), $value, $partial);
        }
        return $criteria;
    }

    public function search()
    {
        $key = $this->getTableSchema()->primaryKey;
        return new CActiveDataProvider($this, array(
            'criteria' => $this->searchCriteria(),
            'sort' => array('defaultOrder' => 't.'.Yii::app()->db->quoteColumnName($key).' DESC'),
            'pagination' => array('pageSize' => 20),
        ));
    }

    public function attributeLabels()
    {
        $labels = array();
        foreach ($this->attributeNames() as $name) {
            $labels[$name] = ucwords(str_replace('_', ' ', $name));
        }
        return $labels;
    }

    protected function afterFind()
    {
        $this->_originalAttributes = $this->getAttributes();
        parent::afterFind();
    }

    public function update($attributes = null)
    {
        if ($attributes !== null || !$this->_originalAttributes || $this->isNewRecord) {
            return parent::update($attributes);
        }
        if (!$this->beforeSave()) {
            return false;
        }
        $changed = array();
        foreach ($this->getAttributes() as $name => $value) {
            if (!array_key_exists($name, $this->_originalAttributes) || $value !== $this->_originalAttributes[$name]) {
                $changed[$name] = $value;
            }
        }
        // Do not resubmit unchanged legacy zero dates to a strict MySQL session.
        if ($changed) {
            $this->updateByPk($this->getOldPrimaryKey(), $changed);
        }
        $this->setOldPrimaryKey($this->getPrimaryKey());
        $this->afterSave();
        return true;
    }

    protected function afterSave()
    {
        $this->_originalAttributes = $this->getAttributes();
        parent::afterSave();
    }
}
