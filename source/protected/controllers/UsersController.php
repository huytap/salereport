<?php

class UsersController extends ManagementController
{
    protected $modelClass = 'Users';

    public function actionCreate()
    {
        if (!ActionAccess::isAll()) { throw new CHttpException(403,'All scope is required to create accounts.'); }
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
        if (!ActionAccess::isAll()) {
            unset($attributes['roles'], $attributes['hotel'], $attributes['group'], $attributes['status'], $attributes['username']);
        }
        if (isset($attributes['status']) && $attributes['status'] !== 'A' && !$model->isNewRecord && CustomerPolicy::ready()) {
            $open = Yii::app()->db->createCommand("SELECT COUNT(*) FROM crm_task WHERE assignee_user_id=:id AND status IN ('open','in_progress')")->queryScalar(array(':id'=>$model->id));
            $owned = Yii::app()->db->createCommand("SELECT COUNT(*) FROM crm_account_hotel WHERE owner_user_id=:id AND status='active'")->queryScalar(array(':id'=>$model->id));
            if ($open || $owned) { throw new CHttpException(409,'Hand over accounts and open tasks before deactivation.'); }
        }
        $model->attributes = $attributes;
    }

    public function actionDelete($id)
    {
        $model = $this->loadModel($id);
        if ($model->id == 1 || $model->id == Yii::app()->user->id) {
            throw new CHttpException(403, 'The system administrator and your own account cannot be deleted.');
        }
        if (!ActionAccess::isAll()) { throw new CHttpException(403,'All scope is required.'); }
        if (CustomerPolicy::hasUserReferences($model->id)) { throw new CHttpException(409,'This user is referenced by MVP data; hand over and deactivate instead.'); }
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

    public function actionHotelaccess($id)
    {
        if (!ActionAccess::isAll()) { throw new CHttpException(403,'All scope is required.'); }
        if (!is_scalar($id) || !ctype_digit((string)$id) || !ActionAccess::user($id)) { throw new CHttpException(404,'Active user not found.'); }
        if (Yii::app()->request->isPostRequest) {
            $grants=Yii::app()->request->getPost('grants',array());
            if (!is_array($grants)) { throw new CHttpException(400,'Invalid grants.'); }
            HotelAccessService::replace($id,$grants,Yii::app()->request->getPost('reason',''));
        }
        header('Content-Type: application/json; charset=UTF-8');
        $rows=Yii::app()->db->createCommand('SELECT hotel_id,scope_level FROM crm_user_hotel_access WHERE user_id=:id ORDER BY hotel_id')->queryAll(true,array(':id'=>$id));
        echo CJSON::encode($rows);
    }
}
