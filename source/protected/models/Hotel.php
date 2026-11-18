<?php

class Hotel extends MvpActiveRecord
{
    protected $editableFields = array('code', 'name', 'timezone');
    protected $requiredFields = array('code', 'name');
    protected $enumFields = array('status'=>array('active', 'archived'));

    public function tableName() { return 'crm_hotel'; }

    public function relations()
    {
        return array(
            'accountHotels' => array(self::HAS_MANY, 'AccountHotel', array('hotel_id'=>'id')),
        );
    }

}
