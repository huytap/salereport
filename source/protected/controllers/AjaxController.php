<?php
/** Old report-date reminder endpoints must never dispatch or infer new Tasks. */
class AjaxController extends Controller
{
    public function filters() { return array('accessControl'); }
    public function accessRules() { return array(array('allow','users'=>array('@')),array('deny','users'=>array('*'))); }
    public function actionReminderdaily() { throw new CHttpException(410,'Use reminder/summary. Legacy reminder generation is disabled.'); }
    public function actionReminder() { $this->actionReminderdaily(); }
    public function actionReminder1() { $this->actionReminderdaily(); }
}
