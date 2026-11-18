<?php

class EntityAlias extends MvpActiveRecord
{
    protected $requiredFields = array('normalized_alias');
    protected $enumFields = array('entity_type'=>array('account', 'hotel', 'user'));

    public function tableName() { return 'crm_entity_alias'; }

    public function relations()
    {
        return array(

        );
    }

    protected function prepareValidation()
    {
        $classes = array('account'=>'Account','hotel'=>'Hotel','user'=>'Users');
        if (isset($classes[$this->entity_type]) && $this->entity_id) {
            $class = $classes[$this->entity_type];
            if (!$class::model()->exists('id=:id',array(':id'=>$this->entity_id))) {
                $this->addError('entity_id','Alias target does not exist.');
            }
        }
    }
}
