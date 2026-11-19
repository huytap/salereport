<?php
class TaskController extends ScopedController
{
    public function run($actionID)
    {
        try { parent::run($actionID); }
        catch (CHttpException $e) {
            if (Yii::app()->request->getParam('format')!=='json') { throw $e; }
            $codes=array(400=>'INVALID_REQUEST',403=>'FORBIDDEN',404=>'NOT_FOUND',405=>'METHOD_NOT_ALLOWED',409=>'CONFLICT',422=>'VALIDATION_ERROR',503=>'FEATURE_DISABLED');
            http_response_code($e->statusCode); $this->json(array('error_code'=>isset($codes[$e->statusCode])?$codes[$e->statusCode]:'REQUEST_ERROR','message'=>$e->getMessage(),'field_errors'=>$e instanceof CustomerValidationException?$e->errors:new stdClass));
        } catch (Exception $e) {
            if (Yii::app()->request->getParam('format')!=='json') { throw $e; }
            http_response_code(500); $this->json(array('error_code'=>'SAVE_FAILED','message'=>'Không thể xử lý công việc. Hãy thử lại với cùng mã yêu cầu.','field_errors'=>new stdClass));
        }
    }
    private function json($value) { header('Content-Type: application/json; charset=UTF-8'); echo CJSON::encode($value); }
    private function filtersInput()
    {
        $f=array(); foreach (array('q','hotel_id','assignee_user_id','account_id','status','priority') as $name) { if (isset($_GET[$name])) { $f[$name]=$_GET[$name]; } } return $f;
    }
    private function fields()
    {
        $value=Yii::app()->request->getPost('Task',array()); if (!is_array($value)) { throw new CHttpException(400,'Biểu mẫu công việc không hợp lệ.'); }
        foreach ($value as $v) { if ($v!==null && !is_scalar($v)) { throw new CHttpException(400,'Trường công việc không hợp lệ.'); } } return $value;
    }
    private function done($result)
    {
        if (Yii::app()->request->getParam('format')==='json') { $this->json($result); }
        else { $this->redirect(array('view','id'=>$result['task_id'])); }
    }
    private function errors(CustomerValidationException $e)
    {
        if (Yii::app()->request->getParam('format')==='json') { throw $e; }
        http_response_code(422); return $e->errors;
    }
    public function actionAdmin()
    {
        $this->render('admin',TaskReadService::listing($this->filtersInput(),Yii::app()->request->getQuery('tab','mine'),Yii::app()->request->getQuery('page','1'),Yii::app()->request->getQuery('timezone','Asia/Ho_Chi_Minh')));
    }
    public function actionView($id) { $data=TaskReadService::detail($id,Yii::app()->request->getQuery('page','1')); $data['errors']=array(); $this->render('view',$data); }
    public function actionCalendar()
    {
        $timezone=Yii::app()->request->getQuery('timezone','Asia/Ho_Chi_Minh'); $tz=TaskSchedule::timezone($timezone); $today=new DateTimeImmutable('today',$tz); $monday=$today->modify('monday this week');
        $from=Yii::app()->request->getQuery('from',$monday->format('Y-m-d')); TaskReadService::date($from);
        $to=Yii::app()->request->getQuery('to',(new DateTimeImmutable($from,$tz))->modify('+7 days')->format('Y-m-d'));
        $data=TaskReadService::calendar($from,$to,$timezone,$this->filtersInput());
        if (Yii::app()->request->getQuery('format')==='json') { $this->json($data); return; }
        $data['hotels']=AccountReadService::hotels(); $data['owners']=Users::model()->findAll(CustomerPolicy::visibleUsersCriteria()); $this->render('calendar',$data);
    }
    private function values(Task $task)
    {
        return array_merge($task->attributes,array('due_at_local'=>TaskSchedule::local($task->due_at_utc,$task->timezone),'starts_at_local'=>TaskSchedule::local($task->starts_at_utc,$task->timezone),'ends_at_local'=>TaskSchedule::local($task->ends_at_utc,$task->timezone)));
    }
    public function actionCreate()
    {
        TaskService::authorize('create'); $hotels=AccountReadService::hotels(); $hotelId=Yii::app()->request->getParam('hotel_id',$hotels?$hotels[0]->id:null);
        if ($hotelId!==null) { $hotelId=CustomerWriteSupport::id($hotelId); CustomerWriteSupport::hotel($hotelId); }
        $accountQ=Yii::app()->request->getQuery('account_q',''); $accounts=$hotelId?TaskReadService::choices($hotelId,$accountQ):array();
        $accountId=Yii::app()->request->getParam('account_id',$accounts?$accounts[0]->id:null); $assignment=null; $contacts=array();
        if ($accountId!==null && $accountId!=='') {
            $accountId=CustomerWriteSupport::id($accountId); CustomerPolicy::assertAccountHotel($accountId,$hotelId,'task','create');
            $account=Account::model()->findByPk($accountId); $assignment=AccountHotel::model()->findByPk(array('account_id'=>$accountId,'hotel_id'=>$hotelId));
            if (!in_array($accountId,CHtml::listData($accounts,'id','id'))) { $accounts[]=$account; }
            $contacts=ContactPerson::model()->findAll("account_id=:id AND status='active'",array(':id'=>$accountId));
        }
        $m=new Task; $m->due_mode='date'; $m->due_date=(new DateTimeImmutable('today',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d'); $m->timezone='Asia/Ho_Chi_Minh';
        $values=$this->values($m); $errors=array(); $key=bin2hex(random_bytes(16)); $reportId=Yii::app()->request->getParam('report_id');
        if ($reportId!==null && $reportId!=='') { $report=CustomerPolicy::load('Report','report','report','view',CustomerWriteSupport::id($reportId)); if ((string)$report->account_id!==(string)$accountId || (string)$report->hotel_id!==(string)$hotelId || $report->identity_state!=='linked') { throw new CHttpException(404,'Báo cáo không thuộc hồ sơ/khách sạn đã chọn.'); } }
        if (Yii::app()->request->isPostRequest) {
            $fields=$this->fields(); $values=array_merge($values,$fields); $key=Yii::app()->request->getPost('request_key');
            try { $this->done(TaskService::create($accountId,$hotelId,$fields,$key,Yii::app()->request->getPost('allow_overdue')==='1',Yii::app()->request->getPost('reason',''),Yii::app()->request->getPost('assignee_user_id'),$reportId)); return; }
            catch (CustomerValidationException $e) { $errors=$this->errors($e); }
        }
        $this->render('form',array('mode'=>'create','task'=>$m,'values'=>$values,'errors'=>$errors,'key'=>$key,'version'=>null,'hotels'=>$hotels,'hotelId'=>$hotelId,'accounts'=>$accounts,'accountId'=>$accountId,'accountQ'=>$accountQ,'assignment'=>$assignment,'contacts'=>$contacts,'reportId'=>$reportId));
    }
    private function edit($id,$mode)
    {
        TaskService::authorize($mode); $task=CustomerPolicy::load('Task','task','task',$mode,$id); $values=$this->values($task); $errors=array(); $key=bin2hex(random_bytes(16)); $version=$task->lock_version;
        if ($mode!=='reopen' && !in_array($task->status,array('open','in_progress'),true)) { throw new CHttpException(409,'Công việc đã đóng.'); }
        if ($mode==='reopen' && !in_array($task->status,array('completed','cancelled'),true)) { throw new CHttpException(409,'Công việc chưa đóng.'); }
        if (Yii::app()->request->isPostRequest) {
            $fields=$this->fields(); $values=array_merge($values,$fields); $key=Yii::app()->request->getPost('request_key'); $version=Yii::app()->request->getPost('expected_version');
            try {
                $result=$mode==='update'?TaskService::update($id,$fields,$version,$key):call_user_func(array('TaskService',$mode),$id,$fields,$version,Yii::app()->request->getPost('reason',''),$key,Yii::app()->request->getPost('allow_overdue')==='1');
                $this->done($result); return;
            } catch (CustomerValidationException $e) { $errors=$this->errors($e); }
        }
        $contacts=ContactPerson::model()->findAll("account_id=:id AND status='active'",array(':id'=>$task->account_id));
        $this->render('form',array('mode'=>$mode,'task'=>$task,'values'=>$values,'errors'=>$errors,'key'=>$key,'version'=>$version,'contacts'=>$contacts));
    }
    public function actionUpdate($id) { $this->edit($id,'update'); }
    public function actionReschedule($id) { $this->edit($id,'reschedule'); }
    public function actionReopen($id) { $this->edit($id,'reopen'); }
    private function transition($id,$action)
    {
        $this->requirePost(); $expected=Yii::app()->request->getPost('expected_version'); $key=Yii::app()->request->getPost('request_key');
        try { $this->done($action==='start'?TaskService::start($id,$expected,$key):call_user_func(array('TaskService',$action),$id,$expected,Yii::app()->request->getPost('note',''),$key)); }
        catch (CustomerValidationException $e) { $data=TaskReadService::detail($id); $data['errors']=$this->errors($e); $this->render('view',$data); }
    }
    public function actionStart($id) { $this->transition($id,'start'); }
    public function actionComplete($id) { $this->transition($id,'complete'); }
    public function actionCancel($id) { $this->transition($id,'cancel'); }
}
