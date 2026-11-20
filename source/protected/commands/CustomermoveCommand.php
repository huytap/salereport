<?php
/** Explicit actor, test DB only. Never installs cron or enables operating flags. */
class CustomermoveCommand extends CConsoleCommand
{
    private function actor($id,$enableTest=false)
    {
        CutoverService::testDb(); $u=new CustomerMoveConsoleActor; $u->id=CustomerWriteSupport::id($id); Yii::app()->setComponent('user',$u);
        if ($enableTest) { $p=Yii::app()->params['mvp']; foreach (array('customerWrite','reportWrite','taskWrite') as $f) { $p[$f]=true; } Yii::app()->params['mvp']=$p; }
    }
    private function output($v) { echo CJSON::encode($v)."\n"; }
    public function actionCheck($actor) { $this->actor($actor); $this->output(CutoverService::readiness()); }
    public function actionSchedules($ids,$actor) { $this->actor($actor); $this->output(array('batch_id'=>CustomerTransferService::previewSchedules(preg_split('/[\s,;]+/',trim($ids),-1,PREG_SPLIT_NO_EMPTY)))); }
    public function actionCsv($file,$hotel,$actor)
    {
        $this->actor($actor,true); if (!is_file($file) || filesize($file)>2097152) { throw new CHttpException(400,'CSV không hợp lệ.'); }
        $s=fopen($file,'rb'); try { $this->output(array('batch_id'=>CustomerTransferService::previewCsv($s,$hotel))); } finally { fclose($s); }
    }
    public function actionCommit($id,$actor,$limit=50,$enableTest=0) { $this->actor($actor,(string)$enableTest==='1'); $this->output(CustomerTransferService::commit($id,$limit)); }
    public function actionReconcile($id,$actor) { $this->actor($actor); $this->output(CustomerTransferService::reconcile($id)); }
    public function actionTransition($target,$version,$reason,$actor,$enableTest=0) { $this->actor($actor,(string)$enableTest==='1'); $this->output(CutoverService::transition($target,$version,$reason)); }
}
class CustomerMoveConsoleActor extends CApplicationComponent
{
    public $id;
    public function getIsGuest() { return $this->id===null; }
}
