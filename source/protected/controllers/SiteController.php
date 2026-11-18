<?php

class SiteController extends Controller
{
    public function filters()
    {
        return array('accessControl');
    }

    public function accessRules()
    {
        return array(
            array('allow', 'actions' => array('index', 'login', 'error'), 'users' => array('*')),
            array('allow', 'actions' => array('admin', 'logout', 'changepassword'), 'users' => array('@')),
            array('deny', 'users' => array('*')),
        );
    }

    public function actionIndex()
    {
        $this->redirect(array(Yii::app()->user->isGuest ? 'login' : 'admin'));
    }

    public function actionLogin()
    {
        if (!Yii::app()->user->isGuest) {
            $this->redirect(array('admin'));
        }
        $model = new LoginForm;
        if (isset($_POST['LoginForm']) && is_array($_POST['LoginForm'])) {
            $model->attributes = $_POST['LoginForm'];
            if ($model->validate() && $model->login()) {
                $history = new UserHistory;
                $history->user_id = Yii::app()->user->id;
                $history->login_time = date('Y-m-d H:i:s');
                $history->ip_address = Yii::app()->request->userHostAddress;
                if ($history->save()) {
                    Yii::app()->user->setState('loginHistoryId', $history->id);
                }
                Users::model()->updateByPk(Yii::app()->user->id, array('last_login_date' => $history->login_time));
                $this->redirect(Yii::app()->user->getReturnUrl(array('site/admin')));
            }
        }
        $this->layout = false;
        $this->render('login', array('model' => $model));
    }

    public function actionAdmin()
    {
        $counts = array('contact' => 0, 'report' => 0, 'staff' => 0);
        if (Yii::app()->user->id == 1 || Yii::app()->user->getState('type') === 'superadmin') {
            foreach (array('contact' => 'contact', 'report' => 'reports', 'staff' => 'staff_asst') as $key => $table) {
                $counts[$key] = Yii::app()->db->createCommand()->select('COUNT(*)')->from($table)->queryScalar();
            }
        }
        $this->render('admin', array('counts' => $counts));
    }

    public function actionLogout()
    {
        $history = UserHistory::model()->findByPk(Yii::app()->user->getState('loginHistoryId'));
        if ($history && $history->user_id == Yii::app()->user->id) {
            $history->logout_time = date('Y-m-d H:i:s');
            $history->save();
        }
        Yii::app()->user->logout();
        $this->redirect(array('login'));
    }

    public function actionChangepassword()
    {
        // Always use the current session's account, regardless of an id in the URL.
        $model = Users::model()->findByPk(Yii::app()->user->id);
        if (!$model) {
            throw new CHttpException(404, 'The account does not exist.');
        }
        $model->scenario = 'changepassword';
        if (isset($_POST['Users']) && is_array($_POST['Users'])) {
            foreach (array('old_password', 'new_password', 'confirm_new_password') as $name) {
                $model->$name = isset($_POST['Users'][$name]) ? $_POST['Users'][$name] : '';
            }
            if ($model->save()) {
                Yii::app()->user->setFlash('change_password', 'Password changed successfully.');
                $this->redirect(array('changepassword'));
            }
        }
        $model->old_password = $model->new_password = $model->confirm_new_password = '';
        $this->render('changepassword', array('model' => $model));
    }

    public function actionError()
    {
        if ($error = Yii::app()->errorHandler->error) {
            if (Yii::app()->request->isAjaxRequest) {
                echo CHtml::encode($error['message']);
            } else {
                $this->layout = false;
                $this->render('error', $error);
            }
        }
    }
}
