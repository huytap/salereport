<?php
class CustomermoveController extends ScopedController
{
    public $defaultAction='admin';
    public function actionAdmin() { $this->render('admin',array('batches'=>CustomerTransferService::listing(),'error'=>null)); }
    public function actionView($id) { $this->render('view',array('data'=>CustomerTransferService::view($id),'error'=>null)); }
    public function actionPreview()
    {
        $this->requirePost(); $r=Yii::app()->request;
        if ($r->getPost('kind')==='schedule') { $ids=$r->getPost('ids',''); if (!is_string($ids)) { throw new CHttpException(400,'Danh sách mã sai.'); } $id=CustomerTransferService::previewSchedules(preg_split('/[\s,;]+/',trim($ids),-1,PREG_SPLIT_NO_EMPTY)); }
        else {
            ActionAccess::requireAction('customermove','preview'); $file=CUploadedFile::getInstanceByName('csv');
            if (!$file || $file->error!==UPLOAD_ERR_OK || $file->size>2097152) { throw new CHttpException(400,'Chọn CSV UTF-8 tối đa 2 MB.'); }
            $stream=fopen($file->tempName,'rb'); try { $id=CustomerTransferService::previewCsv($stream,$r->getPost('hotel_id')); } finally { if ($stream) { fclose($stream); } }
        }
        $this->redirect(array('view','id'=>$id));
    }
    public function actionMap($id)
    {
        $this->requirePost(); $r=Yii::app()->request; $d=$r->getPost('decision'); if (!is_array($d)) { throw new CHttpException(400,'Thiếu quyết định.'); }
        CustomerTransferService::map($id,$r->getPost('row_no'),$r->getPost('version'),$d); $this->redirect(array('view','id'=>$id));
    }
    public function actionApprove($id) { $this->requirePost(); CustomerTransferService::approve($id,Yii::app()->request->getPost('version')); $this->redirect(array('view','id'=>$id)); }
    public function actionCommit($id) { $this->requirePost(); $this->render('result',array('result'=>CustomerTransferService::commit($id))); }
    public function actionReconcile($id) { $this->render('result',array('result'=>CustomerTransferService::reconcile($id))); }
    public function actionExport()
    {
        $r=Yii::app()->request; $s=fopen('php://temp','w+');
        try { CustomerTransferCsv::export($s,$r->getQuery('hotel_id'),$r->getQuery('owner_user_id')); rewind($s); header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="customer-profiles.csv"'); header('Cache-Control: private, no-store'); fpassthru($s); } finally { fclose($s); }
    }
    public function actionCutover()
    {
        if (Yii::app()->request->isPostRequest) { $r=Yii::app()->request; CutoverService::transition($r->getPost('target'),$r->getPost('version'),$r->getPost('reason')); }
        $this->render('cutover',array('result'=>CutoverService::readiness()));
    }
}
