<?php
class ReminderController extends ScopedController
{
    public function run($actionID)
    {
        try { parent::run($actionID); }
        catch (CHttpException $e) {
            if (Yii::app()->request->getParam('format')!=='json') { throw $e; }
            $codes=array(400=>'INVALID_REQUEST',403=>'FORBIDDEN',404=>'NOT_FOUND',405=>'METHOD_NOT_ALLOWED',409=>'CONFLICT',503=>'NOT_READY'); http_response_code($e->statusCode);
            $this->json(array('error_code'=>isset($codes[$e->statusCode])?$codes[$e->statusCode]:'REQUEST_ERROR','message'=>$e->getMessage()));
        } catch (Exception $e) {
            if (Yii::app()->request->getParam('format')!=='json') { throw $e; }
            http_response_code(500); $this->json(array('error_code'=>'REMINDER_UNAVAILABLE','message'=>'Không thể xử lý thông báo. Hãy thử lại sau.'));
        }
    }
    private function json($value) { header('Content-Type: application/json; charset=UTF-8'); echo CJSON::encode($value); }
    public function actionAdmin()
    {
        $data=ReminderService::inbox(Yii::app()->request->getQuery('filter','unread'),Yii::app()->request->getQuery('page','1'));
        if (Yii::app()->request->getQuery('format')==='json') { $this->json($data); return; } $this->render('admin',$data);
    }
    public function actionSummary() { $this->json(ReminderService::summary()); }
    public function actionRead($id)
    {
        $this->requirePost(); $result=ReminderService::read($id);
        if (Yii::app()->request->getParam('format')==='json') { $this->json($result); return; }
        $this->redirect(array('admin','filter'=>Yii::app()->request->getPost('filter','unread')));
    }
    public function actionMonitor()
    {
        $data=ReminderService::monitor(); if (Yii::app()->request->getQuery('format')==='json') { $this->json($data); return; } $this->render('monitor',array('health'=>$data));
    }
}
