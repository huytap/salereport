<?php

class AccountHotel extends MvpActiveRecord
{
    protected $enumFields = array('status'=>array('active', 'archived'));
    protected $referenceFields = array('account_id'=>'Account', 'hotel_id'=>'Hotel');

    public function tableName() { return 'crm_account_hotel'; }

    public function relations()
    {
        return array(
            'account' => array(self::BELONGS_TO, 'Account', array('account_id'=>'id')),
            'hotel' => array(self::BELONGS_TO, 'Hotel', array('hotel_id'=>'id')),
            'owner' => array(self::BELONGS_TO, 'Users', array('owner_user_id'=>'id')),
        );
    }

}
