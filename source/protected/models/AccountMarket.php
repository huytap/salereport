<?php

class AccountMarket extends MvpActiveRecord
{
    protected $requiredFields = array('market_code');
    protected $referenceFields = array('account_id'=>'Account');

    public function tableName() { return 'crm_account_market'; }

    public function relations()
    {
        return array(
            'account' => array(self::BELONGS_TO, 'Account', array('account_id'=>'id')),
        );
    }

}
