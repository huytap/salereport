<?php

class ContactController extends ManagementController
{
    protected $modelClass = 'Contact';

    public function actionAdmin()
    {
        if (CutoverService::legacyBlocked()) { ActionAccess::requireAction('account','admin'); $this->redirect(array('/account/admin')); return; }
        parent::actionAdmin();
    }
    public function actionView($id)
    {
        if (CutoverService::legacyBlocked()) {
            $this->loadModel($id);
            $link=LegacyLink::model()->find("source_type='contact' AND source_id=:id AND resolution_state='linked'",array(':id'=>(string)$id));
            if (!$link || !$link->account_id) { throw new CHttpException(410,'Nguồn chưa mapping; quản trị xem kho import để rà soát.'); }
            CustomerPolicy::load('Account','account','account','view',$link->account_id);
            $this->redirect(array('/account/view','id'=>$link->account_id)); return;
        }
        parent::actionView($id);
    }

    public function actionCreate()
    {
        CustomerPolicy::requireLegacyWrite('contact','create');
        $this->editContact(new Contact('create'), 'create');
    }

    public function actionUpdate($id)
    {
        $model=$this->loadModel($id);
        if (CutoverService::legacyBlocked()) { throw new CHttpException(410,'Dùng hồ sơ khách hàng thống nhất.'); }
        $this->editContact($model, 'update');
    }

    protected function editContact($model, $view)
    {
        if (isset($_POST['Contact']) && is_array($_POST['Contact'])) {
            CustomerPolicy::requireLegacyWrite('contact', $model->isNewRecord ? 'create' : 'update', $model);
            $model->attributes = $_POST['Contact'];
            // An unselected HTML multi-select is omitted from POST.
            foreach (array('hotel_produced', 'market') as $name) {
                if (!isset($_POST['Contact'][$name])) {
                    $model->$name = '';
                }
            }
            if ($model->save()) {
                $this->redirect(array('admin'));
            }
        }
        foreach (array('hotel_produced', 'market') as $name) {
            if (is_string($model->$name)) {
                $model->$name = $model->$name === '' ? array() : explode(',', $model->$name);
            }
        }
        foreach (array('dob', 'validity_from', 'validity_to') as $name) {
            if (is_string($model->$name) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $model->$name)) {
                $model->$name = ExtraHelper::date_2_show($model->$name);
            }
        }
        $this->render($view, array('model' => $model));
    }

    public function actionDelete($id)
    {
        $model = $this->loadModel($id);
        CustomerPolicy::requireLegacyWrite('contact','delete',$model);
        $this->finishDelete($model);
    }

    public function actionImport()
    {
        CustomerPolicy::requireLegacyWrite('contact','import');
        $model = new Contact('import');
        $flag = '';
        if (Yii::app()->request->isPostRequest) {
            $model->attachment = CUploadedFile::getInstance($model, 'attachment');
            if ($model->validate(array('attachment'))) {
                $stream = fopen($model->attachment->tempName, 'rb');
                if (!$stream) {
                    $model->addError('attachment', 'The uploaded file could not be read.');
                } else {
                    try {
                        $result = ContactCsv::importStream($stream);
                        $flag = '<div class="alert alert-success">Imported '.(int)$result['imported'].' contact(s).</div>';
                        if ($result['errors']) {
                            $flag .= '<div class="alert alert-danger">'.implode('<br>', array_map(array('CHtml', 'encode'), $result['errors'])).'</div>';
                        }
                    } catch (InvalidArgumentException $e) {
                        $model->addError('attachment', $e->getMessage());
                    } finally {
                        fclose($stream);
                    }
                }
            }
        }
        $this->render('import', array('model' => $model, 'flag' => $flag));
    }

    public function actionReport()
    {
        ActionAccess::requireAction('contact','report');
        if (CutoverService::legacyBlocked()) { throw new CHttpException(410,'Xuất hồ sơ thống nhất tại customermove/export với hotel_id.'); }
        if (Yii::app()->request->isPostRequest && Yii::app()->request->getPost('do_export') === 'yes') {
            $this->exportExcel();
            return;
        }
        $this->render('report', array('datalist' => ReportHelper::dataContact()));
    }

    protected function exportCriteria()
    {
        $criteria = CustomerPolicy::criteria('contact','contact','report');
        $criteria->order = 't.id ASC';
        return $criteria;
    }

    protected function exportExcel()
    {
        ActionAccess::requireAction('contact','report');
        if (CutoverService::legacyBlocked()) { throw new CHttpException(410,'Xuất hồ sơ thống nhất tại customermove/export với hotel_id.'); }
        $path = tempnam(sys_get_temp_dir(), 'crm-contact-export-');
        try {
            ContactExcel::write($path, Contact::model()->findAll($this->exportCriteria()));
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="contacts.xlsx"');
            header('Cache-Control: private, no-store');
            readfile($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
