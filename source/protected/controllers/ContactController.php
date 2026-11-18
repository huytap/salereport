<?php

class ContactController extends ManagementController
{
    protected $modelClass = 'Contact';

    public function actionCreate()
    {
        $this->editContact(new Contact('create'), 'create');
    }

    public function actionUpdate($id)
    {
        $this->editContact($this->loadModel($id), 'update');
    }

    protected function editContact($model, $view)
    {
        if (isset($_POST['Contact']) && is_array($_POST['Contact'])) {
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
        $this->finishDelete($this->loadModel($id));
    }

    public function actionImport()
    {
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
        if (Yii::app()->request->isPostRequest && Yii::app()->request->getPost('do_export') === 'yes') {
            $this->exportExcel();
            return;
        }
        $this->render('report', array('datalist' => ReportHelper::dataContact()));
    }

    protected function exportExcel()
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-contact-export-');
        try {
            ContactExcel::write($path, Contact::model()->findAll(array('order' => 'id ASC')));
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
