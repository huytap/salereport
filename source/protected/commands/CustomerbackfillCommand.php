<?php

/** Test-only CLI. Mapping is reviewed through the same web/service workflow. */
class CustomerbackfillCommand extends CConsoleCommand
{
    private function actor($id)
    {
        // A console actor has no session; use a small explicit component instead.
        $actor=new LegacyImportConsoleActor;
        $actor->id=$id;
        Yii::app()->setComponent('user',$actor);
    }
    public function actionPreview($source,$ids,$actor)
    {
        $this->actor($actor);
        $id=CustomerImportService::preview($source,preg_split('/[\s,;]+/',trim($ids),-1,PREG_SPLIT_NO_EMPTY));
        echo CJSON::encode(array('batch_id'=>$id,'counts'=>CustomerImportService::view($id)['counts']))."\n";
    }
    public function actionCommit($id,$actor,$limit=100)
    {
        $this->actor($actor); $result=CustomerImportService::commit($id,$limit);
        echo CJSON::encode(array('batch_id'=>$id,'counts'=>$result['counts'],'consistent'=>$result['consistent']))."\n";
    }
    public function actionReconcile($id,$actor)
    {
        $this->actor($actor); echo CJSON::encode(CustomerImportService::reconcile($id))."\n";
    }
}
class LegacyImportConsoleActor extends CApplicationComponent
{
    public $id;
    public function getIsGuest() { return $this->id===null; }
}
