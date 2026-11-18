<?php

/** Read endpoints for WP02; full customer editors are implemented in WP04. */
class AccountController extends ScopedController
{
    private function json($value)
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo CJSON::encode($value);
    }

    public function actionLookup()
    {
        $q = Yii::app()->request->getQuery('q','');
        if (!is_string($q) || mb_strlen($q)>255) { throw new CHttpException(400,'Invalid query.'); }
        $criteria = CustomerPolicy::criteria('account','account','lookup');
        $criteria->compare('t.name',$q,true);
        $criteria->compare('t.status','active');
        $hotel = Yii::app()->request->getQuery('hotel_id');
        if ($hotel !== null) {
            if (!is_scalar($hotel) || !ctype_digit((string)$hotel)) { throw new CHttpException(400,'Invalid hotel.'); }
            $criteria->addCondition('EXISTS (SELECT 1 FROM crm_account_hotel ahq WHERE ahq.account_id=t.id AND ahq.hotel_id=:lookupHotel AND ahq.status=\'active\')');
            $criteria->params[':lookupHotel']=(int)$hotel;
            // Narrow the grant to the requested Hotel, not any other Hotel of the Account.
            if (!ActionAccess::isAll()) {
                $criteria->addCondition("EXISTS (SELECT 1 FROM crm_account_hotel ahq INNER JOIN crm_user_hotel_access gq ON gq.hotel_id=ahq.hotel_id WHERE ahq.account_id=t.id AND ahq.hotel_id=:scopeHotel AND gq.user_id=:scopeUser AND (gq.scope_level='hotel' OR (gq.scope_level='own' AND ahq.owner_user_id=:scopeOwner)))");
                $criteria->params += array(':scopeHotel'=>(int)$hotel,':scopeUser'=>(int)Yii::app()->user->id,':scopeOwner'=>(int)Yii::app()->user->id);
            }
        }
        $criteria->limit=20; $criteria->order='t.name ASC, t.id ASC';
        $data=array();
        foreach (Account::model()->findAll($criteria) as $account) { $data[]=array('id'=>$account->id,'code'=>$account->code,'name'=>$account->name); }
        $this->json($data);
    }

    public function actionContacts($id)
    {
        $account=$this->loadScoped('Account','account','contacts',$id);
        $criteria=CustomerPolicy::scopeCriteria('person');
        $criteria->compare('t.account_id',$account->id); $criteria->compare('t.status','active');
        $data=array();
        foreach (ContactPerson::model()->findAll($criteria) as $person) { $data[]=array('id'=>$person->id,'first_name'=>$person->first_name,'last_name'=>$person->last_name,'position'=>$person->position,'email'=>$person->email,'phone'=>$person->phone); }
        $this->json($data);
    }
}
