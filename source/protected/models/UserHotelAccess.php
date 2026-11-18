<?php

class UserHotelAccess extends MvpActiveRecord
{
    protected $enumFields = array('scope_level'=>array('own', 'hotel'));
    protected $referenceFields = array('hotel_id'=>'Hotel');

    public function tableName() { return 'crm_user_hotel_access'; }

    public function relations()
    {
        return array(
            'user' => array(self::BELONGS_TO, 'Users', array('user_id'=>'id')),
            'hotel' => array(self::BELONGS_TO, 'Hotel', array('hotel_id'=>'id')),
        );
    }

}
