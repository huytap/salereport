<?php

abstract class ScopedController extends Controller
{
    public function filters() { return array('accessControl'); }
    public function accessRules()
    {
        return array(array('allow','actions'=>RoleHelper::GetRole($this->id),'users'=>array('@')),array('deny','users'=>array('*')));
    }
    protected function loadScoped($class, $type, $action, $id, $manage = false)
    {
        return CustomerPolicy::load($class,$type,$this->id,$action,$id,$manage);
    }
    protected function requirePost()
    {
        if (!Yii::app()->request->isPostRequest) { throw new CHttpException(405,'POST is required.'); }
    }
}
