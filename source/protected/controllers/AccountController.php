<?php

/** Scoped customer pages and mutation endpoints. */
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
    private function postArray($name)
    {
        $value=Yii::app()->request->getPost($name,array());
        if (!is_array($value)) { throw new CHttpException(400,'Dữ liệu biểu mẫu không hợp lệ.'); }
        return $value;
    }
    private function markets()
    {
        $raw=Yii::app()->request->getPost('markets','');
        if (is_array($raw)) { return $raw; }
        return array_values(array_filter(array_map('trim',explode(',',AccountReadService::text($raw,1300))),'strlen'));
    }
    private function done(array $result)
    {
        if (Yii::app()->request->getParam('format')==='json') { $this->json($result); }
        else { $this->redirect(array('view','id'=>$result['account_id'])); }
    }
    private function formError(CustomerValidationException $e)
    {
        if (Yii::app()->request->getParam('format')==='json') { throw $e; }
        if (!headers_sent()) { http_response_code(422); }
        return $e->errors;
    }
    public function actionAdmin()
    {
        $filters=array(); foreach (array('q','hotel_id','owner_user_id','status','segment') as $f) { if (isset($_GET[$f])) { $filters[$f]=$_GET[$f]; } }
        $this->render('admin',AccountReadService::listing($filters,Yii::app()->request->getQuery('page','1')));
    }
    public function actionView($id)
    {
        $data=AccountReadService::detail($id,Yii::app()->request->getQuery('tab','overview'),Yii::app()->request->getQuery('page','1'));
        $this->render('view',$data);
    }
    public function actionCreate()
    {
        AccountReadService::ready(); CustomerWriteSupport::authorize('create');
        $m=new Account; $person=new ContactPerson; $errors=array(); $markets='';
        $hotels=AccountReadService::hotels();
        $hotelId=Yii::app()->request->getParam('hotel_id',$hotels?$hotels[0]->id:null);
        if ($hotelId!==null) { $hotelId=CustomerWriteSupport::id($hotelId); CustomerWriteSupport::hotel($hotelId); }
        $key=bin2hex(random_bytes(16)); $owner=Yii::app()->user->id;
        if (Yii::app()->request->isPostRequest) {
            $fields=$this->postArray('Account'); $personFields=$this->postArray('Person'); $markets=implode(', ',$this->markets());
            $m->attributes=CustomerWriteSupport::fields($fields,AccountService::accountFields()); $person->attributes=CustomerWriteSupport::fields($personFields,AccountService::personFields());
            $key=Yii::app()->request->getPost('request_key'); $owner=Yii::app()->request->getPost('owner_user_id');
            try { $this->done(AccountService::create($fields,$hotelId,$owner,$personFields,$this->markets(),$key,Yii::app()->request->getPost('allow_duplicate')==='1',Yii::app()->request->getPost('reason',''))); return; }
            catch (CustomerValidationException $e) { $errors=$this->formError($e); }
        }
        $this->render('form',array('model'=>$m,'person'=>$person,'errors'=>$errors,'hotels'=>$hotels,'hotelId'=>$hotelId,'owners'=>$hotelId?AccountReadService::owners($hotelId):array(),'owner'=>$owner,'markets'=>$markets,'requestKey'=>$key,'version'=>null));
    }
    public function actionUpdate($id)
    {
        AccountReadService::ready(); CustomerWriteSupport::authorize('update'); $m=$this->loadScoped('Account','account','update',$id);
        if ($m->status!=='active') { throw new CHttpException(409,'Hồ sơ đã lưu trữ.'); }
        $errors=array(); $key=bin2hex(random_bytes(16)); $version=$m->lock_version;
        $markets=implode(', ',Yii::app()->db->createCommand('SELECT market_code FROM crm_account_market WHERE account_id=:id ORDER BY market_code')->queryColumn(array(':id'=>$id)));
        if (Yii::app()->request->isPostRequest) {
            $fields=$this->postArray('Account'); $m->attributes=CustomerWriteSupport::fields($fields,AccountService::accountFields()); $markets=implode(', ',$this->markets()); $key=Yii::app()->request->getPost('request_key'); $version=Yii::app()->request->getPost('expected_version');
            try { $this->done(AccountService::update($id,$fields,$this->markets(),$version,$key,Yii::app()->request->getPost('allow_duplicate')==='1',Yii::app()->request->getPost('reason',''))); return; }
            catch (CustomerValidationException $e) { $errors=$this->formError($e); }
        }
        $this->render('form',array('model'=>$m,'errors'=>$errors,'markets'=>$markets,'requestKey'=>$key,'version'=>$version));
    }
    private function personForm($id,$personId=null)
    {
        AccountReadService::ready(); $action=$personId===null?'contactcreate':'contactupdate'; CustomerWriteSupport::authorize($action);
        $account=$this->loadScoped('Account','account',$action,$id);
        if ($account->status!=='active') { throw new CHttpException(409,'Hồ sơ đã lưu trữ.'); }
        $m=$personId===null?new ContactPerson:ContactPerson::model()->findByPk(CustomerWriteSupport::id($personId));
        if (!$m || (!$m->isNewRecord && (string)$m->account_id!==(string)$account->id)) { throw new CHttpException(404,'Không tìm thấy người liên hệ.'); }
        if (!$m->isNewRecord && $m->status!=='active') { throw new CHttpException(409,'Người liên hệ đã lưu trữ.'); }
        $errors=array(); $key=bin2hex(random_bytes(16)); $version=$m->lock_version;
        if (Yii::app()->request->isPostRequest) {
            $fields=$this->postArray('Person'); $m->attributes=CustomerWriteSupport::fields($fields,AccountService::personFields()); $key=Yii::app()->request->getPost('request_key'); $version=Yii::app()->request->getPost('expected_version');
            try { $this->done(AccountService::person($id,$personId,$fields,$version,$key)); return; }
            catch (CustomerValidationException $e) { $errors=$this->formError($e); }
        }
        $this->render('person',array('account'=>$account,'model'=>$m,'errors'=>$errors,'requestKey'=>$key,'version'=>$version));
    }
    public function actionContactcreate($id) { $this->personForm($id); }
    public function actionContactupdate($id,$person_id) { $this->personForm($id,$person_id); }
    public function actionArchive($id)
    {
        $this->requirePost(); $this->done(AccountService::archive($id,null,Yii::app()->request->getPost('expected_version'),Yii::app()->request->getPost('reason'),Yii::app()->request->getPost('request_key')));
    }
    public function actionContactarchive($id,$person_id)
    {
        $this->requirePost(); $this->done(AccountService::archive($id,$person_id,Yii::app()->request->getPost('expected_version'),Yii::app()->request->getPost('reason'),Yii::app()->request->getPost('request_key')));
    }
    public function actionAssignpreview($id)
    {
        AccountReadService::ready(); $account=$this->loadScoped('Account','account','assignpreview',$id); $hotels=AccountReadService::hotels(true);
        $hotelId=Yii::app()->request->getQuery('hotel_id',$hotels?$hotels[0]->id:null); $plan=$hotelId?AssignmentService::preview($id,$hotelId):null;
        if (Yii::app()->request->getQuery('format')==='json') { $this->json($plan); return; }
        $this->render('assign',array('account'=>$account,'hotels'=>$hotels,'hotelId'=>$hotelId,'plan'=>$plan,'owners'=>$hotelId?AccountReadService::owners($hotelId):array(),'requestKey'=>bin2hex(random_bytes(16))));
    }
    public function actionAssign($id)
    {
        $this->requirePost(); $this->done(AssignmentService::handover($id,Yii::app()->request->getPost('hotel_id'),Yii::app()->request->getPost('owner_user_id'),Yii::app()->request->getPost('expected_version'),Yii::app()->request->getPost('plan_hash'),Yii::app()->request->getPost('reason'),Yii::app()->request->getPost('request_key')));
    }
}
