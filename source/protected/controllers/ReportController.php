<?php

class ReportController extends Controller{
    public function run($actionID)
    {
        try { parent::run($actionID); }
        catch (CHttpException $e) {
            if (Yii::app()->request->getParam('format')!=='json') { throw $e; }
            $codes=array(400=>'INVALID_REQUEST',403=>'FORBIDDEN',404=>'NOT_FOUND',405=>'METHOD_NOT_ALLOWED',409=>'CONFLICT',410=>'LEGACY_DISABLED',422=>'VALIDATION_ERROR',503=>'FEATURE_DISABLED');
            http_response_code($e->statusCode); $this->workflowJson(array('error_code'=>isset($codes[$e->statusCode])?$codes[$e->statusCode]:'REQUEST_ERROR','message'=>$e->getMessage(),'field_errors'=>$e instanceof CustomerValidationException?$e->errors:new stdClass));
        } catch (Exception $e) {
            if (Yii::app()->request->getParam('format')!=='json') { throw $e; }
            http_response_code(500); $this->workflowJson(array('error_code'=>'SAVE_FAILED','message'=>'Không thể lưu báo cáo. Hãy thử lại với cùng mã yêu cầu.','field_errors'=>new stdClass));
        }
    }
    private function workflowJson($value) { header('Content-Type: application/json; charset=UTF-8'); echo CJSON::encode($value); }
    private function postArray($name,$default=array())
    {
        $value=Yii::app()->request->getPost($name,$default); if (!is_array($value)) { throw new CHttpException(400,'Biểu mẫu không hợp lệ: '.$name); } return $value;
    }
    private function workflowForm($model=null)
    {
        $new=$model===null; $action=$new?'create':'update'; ReportService::authorize($action);
        if (isset($_POST['company']) || isset($_GET['company'])) { throw new CHttpException(410,'Luồng sao chép theo tên công ty đã đóng. Hãy chọn hồ sơ khách hàng bằng ID.'); }
        if (!$new && ($model->identity_state!=='linked' || !$model->account_id || !$model->hotel_id)) { throw new CHttpException(409,'Cần mapping Report cũ trước khi chỉnh sửa.'); }
        if (!$new && !ReportService::canEdit($model)) {
            if (Yii::app()->request->isPostRequest) { throw new CHttpException(409,'Báo cáo đã khóa sau 7 ngày; công việc vẫn xử lý độc lập.'); }
            $this->render('unifiedView',ReportService::detail($model)); return;
        }
        $hotels=AccountReadService::hotels(); $accounts=array(); $contacts=array(); $accountQ=Yii::app()->request->getQuery('account_q','');
        $hotelId=$new?Yii::app()->request->getParam('hotel_id',$hotels?$hotels[0]->id:null):$model->hotel_id;
        $accountId=$new?Yii::app()->request->getParam('account_id'): $model->account_id;
        if ($hotelId!==null && $hotelId!=='') { CustomerWriteSupport::hotel($hotelId); $accounts=TaskReadService::choices($hotelId,$accountQ); }
        if ($new && $accountId===null) { $accountId=$accounts?$accounts[0]->id:null; }
        $contactId=$new?Yii::app()->request->getParam('contact_person_id'): $model->contact_person_id;
        $snapshot=null;
        if ($accountId!==null && $accountId!=='') {
            ReportService::context($accountId,$hotelId,$action); $account=Account::model()->findByPk($accountId);
            if (!in_array($accountId,CHtml::listData($accounts,'id','id'))) { $accounts[]=$account; }
            $contacts=ContactPerson::model()->findAll("account_id=:id AND status='active'",array(':id'=>$accountId));
            if ($new) { $snapshot=ReportService::preview($accountId,$hotelId,$contactId); }
        }
        $key=bin2hex(random_bytes(16)); $version=$new?null:$model->lock_version;
        $values=$new?array('date'=>(new DateTimeImmutable('today',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d'),'sales_type'=>'','objective'=>'','expected_result'=>'','report'=>'','follow_up'=>'','action'=>'','new_account'=>'','comments'=>'','highlight'=>0):$model->attributes;
        $rows=array(); $newContact=array(); $errors=array();
        if (Yii::app()->request->isPostRequest) {
            $fields=$this->postArray('Report'); $rows=$this->postArray('tasks');
            if (count($rows)>20 || ($rows && array_keys($rows)!==range(0,count($rows)-1))) { throw new CHttpException(400,'Danh sách việc phải có thứ tự liên tục, tối đa 20 dòng.'); }
            foreach ($rows as $row) { if (!is_array($row) || !isset($row['fields']) || !is_array($row['fields'])) { throw new CHttpException(400,'Dòng việc không hợp lệ.'); } foreach ($row['fields'] as $value) { if ($value!==null && !is_scalar($value)) { throw new CHttpException(400,'Trường công việc không hợp lệ.'); } } } $key=Yii::app()->request->getPost('request_key'); $version=Yii::app()->request->getPost('expected_version');
            foreach ($fields as $value) { if ($value!==null && !is_scalar($value)) { throw new CHttpException(400,'Trường Report không hợp lệ.'); } } $values=array_merge($values,$fields);
            $newContact=$this->postArray('new_contact');
            if (!$new && (isset($_POST['account_id']) || isset($_POST['hotel_id']) || isset($_POST['contact_person_id']) || $newContact)) { throw new CHttpException(400,'Không đổi định danh hoặc snapshot của báo cáo đã lập.'); }
            if (Yii::app()->request->getPost('form_action')==='add_task') {
                TaskService::authorize('create'); if (count($rows)>=20) { throw new CHttpException(400,'Tối đa 20 việc mới.'); }
                $rows[]=array('fields'=>array('title'=>'','description'=>'','kind'=>'task','action_type'=>'other','priority'=>'normal','due_mode'=>'date','due_date'=>$values['date'],'due_at_local'=>'','starts_at_local'=>'','ends_at_local'=>'','timezone'=>'Asia/Ho_Chi_Minh','location'=>'','contact_person_id'=>'inherit'),'reason'=>'');
            } elseif (Yii::app()->request->getPost('remove_task')!==null) {
                $remove=CustomerWriteSupport::id(Yii::app()->request->getPost('remove_task'),true); if ($remove>=count($rows)) { throw new CHttpException(400,'Dòng việc không hợp lệ.'); } array_splice($rows,$remove,1);
            } else {
                $dto=$rows;
                if (Yii::app()->request->getPost('ui')==='1') { foreach ($dto as &$row) { if (is_array($row) && isset($row['fields']['contact_person_id']) && $row['fields']['contact_person_id']==='inherit') { unset($row['fields']['contact_person_id']); } } unset($row); }
                $quick=$newContact ?: null;
                if (Yii::app()->request->getPost('ui')==='1' && Yii::app()->request->getPost('new_contact_enabled')!=='1') { $quick=null; }
                try {
                    $result=$new?ReportService::create($accountId,$hotelId,$contactId,$fields,$dto,$key,$quick):ReportService::update($model->id,$fields,$dto,$version,$key);
                    if (Yii::app()->request->getParam('format')==='json') { $this->workflowJson($result); } else { $this->redirect(array('view','id'=>$result['report_id'])); } return;
                } catch (CustomerValidationException $e) {
                    if (Yii::app()->request->getParam('format')==='json') { throw $e; } http_response_code(422); $errors=$e->errors;
                }
            }
        }
        $this->render('unifiedForm',array('model'=>$model,'isNew'=>$new,'values'=>$values,'snapshot'=>$snapshot,'rows'=>$rows,'newContact'=>$newContact,'errors'=>$errors,'requestKey'=>$key,'version'=>$version,
            'hotels'=>$hotels,'hotelId'=>$hotelId,'accounts'=>$accounts,'accountId'=>$accountId,'contacts'=>$contacts,'contactId'=>$contactId,'accountQ'=>$accountQ));
    }
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			'postOnly + delete', // we only allow deletion via POST request
		);
	}

	public function accessRules()
	{
		return array(
			
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>RoleHelper::GetRole(),
				'users'=>array('@'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

	public function actionStaffasst(){
		$this->redirect(Yii::app()->createUrl('staffasst/admin'));
	}
	
	public function actionView($id){
		$model = $this->loadModel($id);
        if (CustomerPolicy::ready() && $model->hasAttribute('account_id') && $model->account_id && $model->identity_state==='linked') { $this->render('unifiedView',ReportService::detail($model,Yii::app()->request->getQuery('page','1'),Yii::app()->request->getQuery('audit_page','1'))); return; }
		
		$this->render('view', compact(array('model')));
	}

	public function actionCreate(){
        if (!empty(Yii::app()->params['mvp']['reportWrite'])) { $this->workflowForm(); return; }
        CustomerPolicy::requireLegacyWrite('report','create');

		if(isset($_POST['company'])){
			$model = Report::model()->getByCompany($_POST['company']);
			$model->ent = $model->sales = $model->site_ins = $model->comments = $model->report = $model->follow_up = '';
			$model->highlight=0;
			$model->date = date('d-M-Y');
			$this->render('_form', compact('model'));
			return;
		}

		$model = new Report;
		$model->date = date('d-m-Y');
		if(isset($_POST['Report'])){
			$model->attributes = $_POST['Report'];
			
			$date = ExtraHelper::date_2_save($_POST['Report']['date']);
			if($_POST['Report']['ent']){
				$ent = ExtraHelper::date_2_save($_POST['Report']['ent']);
				$model->ent = $ent['date'];
			}
			if($_POST['Report']['sales']){
				$sales = ExtraHelper::date_2_save($_POST['Report']['sales']);
				$model->sales = $sales['date'];
			}
			if($_POST['Report']['site_ins']){
				$site_ins = ExtraHelper::date_2_save($_POST['Report']['site_ins']);
				$model->site_ins = $site_ins['date'];
			}
			$model->date = $date['date'];

			ExtraHelper::update_tracking_data($model, 'create');
			$model->added_by = Yii::app()->user->id;
			if(isset($_POST['Report']['hotels'])){
				$hotels = implode(',', $_POST['Report']['hotels']);
				$model->hotels = $hotels;
			}
			$model->validate();

			if($model->save()){
				$this->redirect(Yii::app()->createUrl('report/admin'));
			}
		}
		if($model->hotels){
			$model->hotels = explode(',', $model->hotels);
		}
		if($model->date){
			$model->date = ExtraHelper::date_2_show($model->date);
		}
		if($model->ent){
			$model->ent = ExtraHelper::date_2_show($model->ent);
		}
		if($model->sales){
			$model->sales = ExtraHelper::date_2_show($model->sales);
		}
		if($model->site_ins){
			$model->site_ins = ExtraHelper::date_2_show($model->site_ins);
		}
		$this->render('create', compact('model'));
	}

	public function actionUpdate($id){
        if (!empty(Yii::app()->params['mvp']['reportWrite'])) { $this->workflowForm($this->loadModel($id)); return; }
		$model = $this->loadModel($id);
        CustomerPolicy::requireLegacyWrite('report','update',$model);
		$now = date('Y-m-d');
        $date = date('Y-m-d', strtotime($model->added_date));
        $new = date('Y-m-d', strtotime("$date +7days"));
        if(strtotime($new) >= strtotime($now)){

			if(isset($_POST['Report'])){
				$model->attributes = $_POST['Report'];
				$date = ExtraHelper::date_2_save($_POST['Report']['date']);
				$model->date = $date['date'];
				if(isset($_POST['Report']['hotels'])){
					$hotels = implode(',', $_POST['Report']['hotels']);
					$model->hotels = $hotels;
				}

				if($_POST['Report']['ent']){
					$ent = ExtraHelper::date_2_save($_POST['Report']['ent']);
					$model->ent = $ent['date'];
				}
				if($_POST['Report']['sales']){
					$sales = ExtraHelper::date_2_save($_POST['Report']['sales']);
					$model->sales = $sales['date'];
				}
				if($_POST['Report']['site_ins']){
					$site_ins = ExtraHelper::date_2_save($_POST['Report']['site_ins']);
					$model->site_ins = $site_ins['date'];
				}

				ExtraHelper::update_tracking_data($model, 'update');
				$model->validate();
				if(!$model->hasErrors() && $model->update()){
					$this->redirect(Yii::app()->createUrl('report/admin'));
				}
			}

			if($model->hotels){
				$model->hotels = explode(',', $model->hotels);
			}
			if($model->date){
				$model->date = ExtraHelper::date_2_show($model->date);
			}
			if($model->ent){
				$model->ent = ExtraHelper::date_2_show($model->ent);
			}
			if($model->sales){
				$model->sales = ExtraHelper::date_2_show($model->sales);
			}
			if($model->site_ins){
				$model->site_ins = ExtraHelper::date_2_show($model->site_ins);
			}
			$this->render('update', compact('model'));
		}else{
			$this->render('message');
		}
	}

	public function actionImport(){
        CustomerPolicy::requireLegacyWrite('report','import');
		$row = 1;
		$html_result = '';
		$model=new Report();
		$flag='';
		if(isset($_POST['Report'])){
			$model->attributes=$_POST['Report'];
			$file = CUploadedFile::getInstance($model, 'attachment');
            if ($file !== null) {
                $fileName1 = date('Y-m-d').date('H-m-i').$file->name;
                $file->saveAs(Yii::app()->basePath . "/../media/files/$fileName1");
            
				$model->validate();
				if (($handle = fopen("media/files/".$fileName1, "r")) !== FALSE) {
					$html='';
				    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
				    	if($row>=4 && isset($data[1]) && $data[1] !== ''){
					        	$model = new Report;
					        	if($data[0]){
					        		$dob = ExtraHelper::date_2_save($data[0]);
					        		$model->date=$dob['date'];
					        	}
					        	$model->company_name=$data[1];
					        	$model->phone = $data[2];
					        	$model->biz_scope = $data[3];
					        	$model->hotels = $data[4];
					        	$model->title = $data[5];
					        	$model->first_name = $data[6];
					        	$model->last_name = $data[7];
					        	$model->position=$data[8];
					        	$model->account_type=$data[9];
					        	$model->sales_type=$data[10];
					        	$model->objective=$data[11];
					        	$model->expected_result=$data[12];
					        	$model->market=$data[13];
					        	$model->report=$data[14];
					        	$model->follow_up=$data[15];
					        	$model->action=$data[16];
					        	$model->done=$data[17];
					        	$model->new_account = $data[18];
					        	
					        	if($data[19]){
					        		$ent = ExtraHelper::date_2_save($data[19]);
					        		$model->ent = $ent['date'];
					        	}
					        	if($data[20]){
					        		$sales = ExtraHelper::date_2_save($data[20]);
					        		$model->sales = $sales['date'];
					        	}
					        	if($data[21]){
					        		$site_ins = ExtraHelper::date_2_save($data[21]);
					        		$model->site_ins = $site_ins['date'];
					        	}
					        	$model->company_address = $data[22];
					        	$model->email = $data[23];
					        	ExtraHelper::update_tracking_data($model, 'create');
					        	//$model->added_by = Yii::app()->user->id;
					        	$user = Users::model()->getByUsername($data[24]);
					        	if($user){
					        		$model->added_by = $user->id;
					        	}
					        	$model->comments = $data[25];
					        	$model->highlight = $data[26];
					        	$model->city = $data[27];
					        	$model->country = $data[28];
					        	$model->validate();
					        	if($model->save()){
							        $html_result .='Record is imported<br>';
							    }else{
							    	
							    	$html_result .='Record save error '.$row.'<br>';
							    }
					    }
					    $row++;
				    }
				    fclose($handle);
				    $flag = $html_result;
				}
			}
		}
		$this->render('import', compact(array('model','flag')));
    }

    public function actionTrackinglist() { throw new CHttpException(410,'Unsupported legacy tracking endpoint.'); }
    public function actionTracking($id) { throw new CHttpException(410,'Unsupported legacy tracking endpoint.'); }

	public function actionDelete($id){
            CustomerPolicy::requireLegacyWrite('report','delete',$this->loadModel($id));
			$model = $this->loadModel($id);
			if($model->delete())
				//echo CJSON::encode(1);
			$this->redirect(Yii::app()->createUrl('report/admin'));
	}

    public function actionReport()
    {
        // This historical route exports Contacts, so Report permission alone is insufficient.
        ActionAccess::requireAction('contact','report');
        if (!Yii::app()->request->isPostRequest) { throw new CHttpException(405,'POST is required for this legacy export.'); }
        $this->export_excel();
    }

	public function actionAdmin(){
		$filter = array('from_date' => '', 'to_date' => '', 'by' => '', 'contact' => '');
		$model = new Report('search');
		$model->unsetAttributes();
		if(isset($_GET['Report'])){
			$model->attributes = $_GET['Report'];
		}
		
		if(isset($_GET['filter'])){
			$filter = $_GET['filter'];
		}

		if(isset($_GET['filter']['by'])){
			$filter['by'] = $_GET['filter']['by'];
		}else{
			$filter['by'] = '';
		}

		if (Yii::app()->getRequest()->getIsAjaxRequest()) {
            header( 'Content-type: application/json' );
            $this->renderPartial('_grid', compact(array('model')));
            Yii::app()->end();
        }

		$this->render('admin', compact(array('model', 'filter')));
	}

    public function export_excel($type = 'excel')
    {
        ActionAccess::requireAction('contact','report');
        if (!Yii::app()->request->isPostRequest) { throw new CHttpException(405,'POST is required.'); }
        $criteria = CustomerPolicy::criteria('contact','contact','report');
        $criteria->order = 't.id ASC';
        $path = tempnam(sys_get_temp_dir(),'crm-scoped-export-');
        try {
            ContactExcel::write($path,Contact::model()->findAll($criteria));
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="contacts.xlsx"');
            readfile($path);
        } finally { if (is_file($path)) { unlink($path); } }
    }

    public function loadModel($id)
    {
        return CustomerPolicy::load('Report','report','report',$this->action ? $this->action->id : 'view',$id);
    }
}
