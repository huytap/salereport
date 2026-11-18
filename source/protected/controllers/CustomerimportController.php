<?php

class CustomerimportController extends ScopedController
{
    private function output($value)
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo CJSON::encode($value);
    }
    public function actionAdmin()
    {
        $batches=CustomerImportService::listing();
        if (Yii::app()->request->getQuery('format')==='json') { $this->output($batches); return; }
        $sources=CustomerImportService::browseSources(Yii::app()->request->getQuery('source_type','contact'),Yii::app()->request->getQuery('page',1));
        $this->render('admin',array('batches'=>$batches,'sources'=>$sources));
    }
    public function actionPreview()
    {
        $this->requirePost();
        $ids=Yii::app()->request->getPost('source_ids',array());
        if (is_string($ids)) { $ids=preg_split('/[\s,;]+/',trim($ids),-1,PREG_SPLIT_NO_EMPTY); }
        if (!is_array($ids)) { throw new CHttpException(400,'Invalid source IDs.'); }
        if (Yii::app()->request->getPost('ui')==='1') {
            $manual=Yii::app()->request->getPost('manual_ids','');
            if (!is_string($manual)) { throw new CHttpException(400,'Invalid source IDs.'); }
            $ids=array_merge($ids,preg_split('/[\s,;]+/',trim($manual),-1,PREG_SPLIT_NO_EMPTY));
        }
        $id=CustomerImportService::preview(Yii::app()->request->getPost('source_type'),$ids);
        if (Yii::app()->request->getPost('ui')==='1') { $this->redirect(array('view','id'=>$id)); return; }
        $this->output(CustomerImportService::view($id));
    }
    public function actionView($id)
    {
        $data=CustomerImportService::view($id);
        if (Yii::app()->request->getQuery('format')==='json') { $this->output($data); return; }
        $selected=Yii::app()->request->getQuery('row',1);
        $row=null;
        foreach ($data['rows'] as $item) { if ((string)$item['row_no']===(string)$selected) { $row=$item; break; } }
        if (!$row) { throw new CHttpException(404,'Import row not found.'); }
        $this->render('view',array('data'=>$data,'row'=>$row,'hotels'=>Hotel::model()->findAll("status='active'"),'owners'=>Users::model()->findAll("status='A'")));
    }
    public function actionMap($id,$row)
    {
        $this->requirePost();
        $decision=Yii::app()->request->getPost('decision');
        if (!is_array($decision)) { throw new CHttpException(400,'Invalid mapping decision.'); }
        if (Yii::app()->request->getPost('ui')==='1') {
            // Form submits fields for every mode; only the chosen create mode authors fields.
            if (!isset($decision['account_kind']) || $decision['account_kind']!=='create') { unset($decision['account_fields']); }
            if (!isset($decision['contact_kind']) || $decision['contact_kind']!=='create') { unset($decision['contact_fields']); }
            if (isset($decision['hotels']) && is_array($decision['hotels'])) {
                $decision['hotels']=array_values(array_filter($decision['hotels'],function($h){ return is_array($h) && isset($h['hotel_id']) && $h['hotel_id']!==''; }));
                foreach ($decision['hotels'] as &$h) { if (isset($h['owner_user_id']) && $h['owner_user_id']==='keep') { unset($h['owner_user_id']); } } unset($h);
            }
            $markets=Yii::app()->request->getPost('markets','');
            if (!is_string($markets)) { throw new CHttpException(400,'Invalid markets.'); }
            $decision['markets']=array_values(array_filter(array_map('trim',explode(',',$markets)),'strlen'));
        }
        CustomerImportService::map($id,$row,Yii::app()->request->getPost('expected_version'),$decision);
        if (Yii::app()->request->getPost('ui')==='1') { $this->redirect(array('view','id'=>$id,'row'=>$row)); return; }
        $this->output(CustomerImportService::view($id));
    }
    public function actionApprove($id)
    {
        $this->requirePost();
        $result=CustomerImportService::approve($id,Yii::app()->request->getPost('expected_version'));
        if (Yii::app()->request->getPost('ui')==='1') { $this->redirect(array('view','id'=>$id)); return; }
        $this->output($result);
    }
    public function actionCommit($id)
    {
        $this->requirePost();
        $result=CustomerImportService::commit($id,Yii::app()->request->getPost('limit',100));
        if (Yii::app()->request->getPost('ui')==='1') { $this->redirect(array('view','id'=>$id)); return; }
        $this->output($result);
    }
    public function actionReconcile($id)
    {
        $result=CustomerImportService::reconcile($id);
        if (Yii::app()->request->getQuery('format')==='json') { $this->output($result); return; }
        $this->render('reconcile',array('result'=>$result));
    }
}
