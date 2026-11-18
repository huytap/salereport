<?php

abstract class ManagementController extends Controller
{
    public $defaultAction = 'admin';
    protected $modelClass;

    public function filters()
    {
        return array('accessControl', 'postOnly + delete');
    }

    public function accessRules()
    {
        return array(
            array('allow', 'actions' => RoleHelper::GetRole($this->id), 'users' => array('@')),
            array('deny', 'users' => array('*')),
        );
    }

    public function loadModel($id)
    {
        if (!is_scalar($id) || !ctype_digit((string)$id)) {
            throw new CHttpException(404, 'The requested record does not exist.');
        }
        $class = $this->modelClass;
        if ($class === 'Contact') {
            return CustomerPolicy::load('Contact','contact','contact',$this->action->id,$id);
        }
        if ($class === 'Users' && !ActionAccess::isAll() && (string)$id !== (string)Yii::app()->user->id) {
            throw new CHttpException(404,'The requested record does not exist.');
        }
        $model = $class::model()->findByPk($id);
        if (!$model) {
            throw new CHttpException(404, 'The requested record does not exist.');
        }
        return $model;
    }

    public function actionAdmin()
    {
        $class = $this->modelClass;
        $model = new $class('search');
        $model->unsetAttributes();
        if (isset($_GET[$class]) && is_array($_GET[$class])) {
            $model->attributes = $_GET[$class];
        }
        $grid = $class === 'Contact' && ActionAccess::isAll()
            ? '_grid_admin' : '_grid';
        if (Yii::app()->request->isAjaxRequest) {
            header('Content-Type: application/json; charset=UTF-8');
            $this->renderPartial($grid, array('model' => $model));
            return;
        }
        $this->render('admin', array('model' => $model));
    }

    public function actionView($id)
    {
        $this->render('view', array('model' => $this->loadModel($id)));
    }

    protected function finishDelete($model)
    {
        if (!$model->delete()) {
            throw new CHttpException(500, 'The record could not be deleted.');
        }
        if (Yii::app()->request->isAjaxRequest) {
            header('Content-Type: application/json; charset=UTF-8');
            echo CJSON::encode(array('success' => true));
        } else {
            $this->redirect(array('admin'));
        }
    }
}
