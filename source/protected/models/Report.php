<?php

class Report extends CrmActiveRecord
{
    protected $requiredFields = array('date', 'company_name');
    protected $listFields = array('hotels');

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'reports';
    }

    public function rules()
    {
        $rules = parent::rules();
        // Schema may not yet be migrated on the live web database. Never mass-assign MVP IDs/state.
        $protected = array_intersect(array('account_id','contact_person_id','hotel_id','identity_state','lock_version','client_request_key','snapshot_json','created_at_utc','updated_at_utc'), $this->attributeNames());
        if ($protected) { $rules[] = array(implode(',', $protected), 'unsafe'); }
        return $rules;
    }

    public function relations()
    {
        return array(
            'account' => array(self::BELONGS_TO, 'Account', array('account_id'=>'id')),
            'contactPerson' => array(self::BELONGS_TO, 'ContactPerson', array('contact_person_id'=>'id')),
            'hotel' => array(self::BELONGS_TO, 'Hotel', array('hotel_id'=>'id')),
            'tasks' => array(self::HAS_MANY, 'Task', array('report_id'=>'id')),
            'user' => array(self::BELONGS_TO, 'Users', array('added_by'=>'id')),
            'addedBy' => array(self::BELONGS_TO, 'Users', array('added_by'=>'id')),
            'updatedBy' => array(self::BELONGS_TO, 'Users', array('updated_by'=>'id')),
        );
    }

    public function getByCompany($company)
    {
        $criteria = CustomerPolicy::criteria('report','report','create');
        $criteria->compare('t.company_name',$company);
        $criteria->order = 't.date DESC, t.id DESC';
        $previous = $this->find($criteria);
        $report = new self;
        if ($previous) {
            // Copy company/contact details, never the previous row's identity or audit fields.
            foreach (array('company_name', 'company_address', 'phone', 'email', 'biz_scope', 'hotels',
                'title', 'first_name', 'last_name', 'position', 'account_type', 'sales_type',
                'objective', 'expected_result', 'market', 'city', 'country') as $name) {
                $report->$name = $previous->$name;
            }
        } else {
            $report->company_name = $company;
        }
        return $report;
    }

    protected function searchCriteria()
    {
        $criteria = parent::searchCriteria();
        $criteria->mergeWith(CustomerPolicy::criteria('report','report','admin'));
        $filter = Yii::app()->request->getParam('filter', array());
        if (!is_array($filter)) {
            return $criteria;
        }
        foreach (array('from_date' => '>=', 'to_date' => '<=') as $name => $operator) {
            if (isset($filter[$name]) && is_string($filter[$name]) && trim($filter[$name]) !== '') {
                $value = trim($filter[$name]);
                $date = DateTime::createFromFormat('!Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) {
                    $date = DateTime::createFromFormat('!d-M-Y', $value);
                    if (!$date || $date->format('d-M-Y') !== $value) {
                        $date = DateTime::createFromFormat('!d-m-Y', $value);
                        if (!$date || $date->format('d-m-Y') !== $value) {
                            $criteria->addCondition('1=0');
                            continue;
                        }
                    }
                }
                $criteria->compare('t.date', $operator.$date->format('Y-m-d'));
            }
        }
        if (isset($filter['by']) && is_scalar($filter['by'])) {
            $criteria->compare('t.added_by', $filter['by']);
        }
        if (isset($filter['contact']) && is_string($filter['contact']) && $filter['contact'] !== '') {
            $contact = new CDbCriteria;
            $contact->compare('t.first_name', $filter['contact'], true);
            $contact->compare('t.last_name', $filter['contact'], true, 'OR');
            $criteria->mergeWith($contact);
        }
        return $criteria;
    }
}
