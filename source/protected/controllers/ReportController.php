<?php

class ReportController extends Controller{
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
		
		$this->render('view', compact(array('model')));
	}

	public function actionCreate(){
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
