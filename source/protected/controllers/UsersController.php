<?php

class UsersController extends ManagementController
{
    protected $modelClass = 'Users';

    public function actionCreate()
    {
        $model = new Users('create');
        if (isset($_POST['Users']) && is_array($_POST['Users'])) {
            $this->assignUser($model, $_POST['Users']);
            if ($model->validate()) {
                // Hash raw input here as well, including passwords that happen to be 40 hex characters.
                $model->password = sha1(md5($model->password));
                if ($model->save(false)) {
                    $this->redirect(array('admin'));
                }
            }
        }
        $model->password = '';
        $this->render('create', array('model' => $model));
    }

    public function actionUpdate($id)
    {
        $model = $this->loadModel($id);
        if ($model->id == 1 && Yii::app()->user->id != 1) {
            throw new CHttpException(403, 'Only the system administrator can edit this account.');
        }
        $model->scenario = 'update';
        if (isset($_POST['Users']) && is_array($_POST['Users'])) {
            $this->assignUser($model, $_POST['Users']);
            if ($model->save()) {
                $this->redirect(array('admin'));
            }
        }
        $model->new_password = '';
        $this->render('update', array('model' => $model));
    }

    protected function assignUser($model, $attributes)
    {
        if (Yii::app()->user->id != 1 && Yii::app()->user->getState('type') !== 'superadmin') {
            unset($attributes['roles'], $attributes['hotel'], $attributes['group']);
        }
        $model->attributes = $attributes;
    }

    public function actionDelete($id)
    {
        $model = $this->loadModel($id);
        if ($model->id == 1 || $model->id == Yii::app()->user->id) {
            throw new CHttpException(403, 'The system administrator and your own account cannot be deleted.');
        }
        $this->finishDelete($model);
    }

    public function actionHistory()
    {
        $model = new UserHistory('search');
        $model->unsetAttributes();
        if (isset($_GET['UserHistory']) && is_array($_GET['UserHistory'])) {
            $model->attributes = $_GET['UserHistory'];
        }
        if (Yii::app()->request->isAjaxRequest) {
            header('Content-Type: application/json; charset=UTF-8');
            $this->renderPartial('_grid2', array('model' => $model));
            return;
        }
        $this->render('history', array('model' => $model));
    }
}
