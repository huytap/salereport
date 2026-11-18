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

	public function actionTrackinglist(){
		if(isset($_REQUEST['data'])){
			$id = json_decode($_REQUEST['data']);
			$model = $this->loadModel($id);
			if($model){
				$tracking= json_decode($model->tracking);
				echo CJSON::encode($tracking);
			}else{
				echo CJSON::encode(0);
			}
		}
	}

	public function actionTracking($id){
		/*$data = json_decode($_REQUEST['data']);*/
		$arr_tracking = new stdClass();
		$data=array();
		$model = $this->loadModel($id);
		$flag = false;
		if(isset($_POST['ShippingOrder'])){
			$model->attributes = $_POST['ShippingOrder'];
			if($model->cod > 0){
				$model->cod_datetime = date('Y-m-d H:m:i');
				$arr_tracking->cod_amount = $model->cod_amount;
				$arr_tracking->cod_notes = $model->cod_notes;
				$arr_tracking->cod_datetime = $model->cod_datetime;
				$arr_tracking->cod_user = $model->cod_user;
			}
			
			$arr_tracking->shipping_status = $model->shipping_status;
			$arr_tracking->location = $model->location;
			$arr_tracking->last_location = $arr_tracking->location;
			
			if($model->tracking){
				$tmp = json_decode($model->tracking);
				array_unshift($tmp, $arr_tracking);
				$model->tracking = json_encode($tmp);	
			}else{
				$model->tracking = json_encode(array($arr_tracking));
			}

			if($model->update()){
				$flag = true;	
			}
		}

		$this->render('tracking', compact(array('model','flag')));
	}

	
	public function actionDelete($id){
			$model = $this->loadModel($id);
			if($model->delete())
				//echo CJSON::encode(1);
			$this->redirect(Yii::app()->createUrl('report/admin'));
	}

	public function actionReport(){

		$status = '';
		$filter_data = array(
			'industry' => '',
			'category'=>'',
			'engage_status' => ''
		);
		if(isset($_POST['filter'])){
			$filter_data = $_POST['filter'];
		}

		$data = ReportHelper::dataContact();
		

		if (isset($_REQUEST['do_export']) && $_REQUEST['do_export']=='yes') {
			$this->export_excel('csv');
			/*$arrReport = array(
				'template' => 'contact.xls',
				'baserow' => 3,
				'filename' => 'Contact Sheet.xls'
			);
			$arrCells = array(
				'A1' => 'Contact list on ' . date('j F Y'),
			);
			ReportHelper::doExport($arrReport, $data, $arrCells);*/
		} else {
			$this->render('report',array(
				'datalist'=>$data
			));
		}
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

	public function export_excel($type = 'excel') {

        if(isset(Yii::app()->user->id)){
        	set_time_limit(0);
			ini_set('memory_limit','2500M');
            $date=date('d-m-Y');

            $exportTypeAllow = array('csv', 'excel');

            if (in_array($type, $exportTypeAllow)) {
                $criteria = new CDbCriteria;

                $dataProvidder = new CActiveDataProvider('Contact', array(
					'criteria' => $criteria,
					'sort' => array('defaultOrder' => 'added_date asc')));
                $dataProvidder->setPagination(false);

                if (count($dataProvidder->getData()) == 0) {

                    Yii::app()->user->setFlash('error', 'There is no record to export.');

                    $this->redirect($this->createUrl('index'));

                }


                $phpExcelPath = Yii::getPathOfAlias('application.libs.phpexcel.Classes');

                spl_autoload_unregister(array('YiiBase', 'autoload'));

                include($phpExcelPath . DIRECTORY_SEPARATOR . 'PHPExcel.php');

                spl_autoload_register(array('YiiBase', 'autoload'));

                $objPHPExcel = new PHPExcel();

                $objPHPExcel->getProperties()->setCreator(Yii::app()->name)

                        ->setLastModifiedBy(Yii::app()->name)

                        ->setTitle("Booked-{$date}")

                        ->setSubject("Booked-{$date}");

                $base = 2;

                //set title

                $objPHPExcel->setActiveSheetIndex(0)

                        ->setCellValue('A' . $base, 'Biz Scope')

                        ->setCellValue('B' . $base, 'Account Type')

                        ->setCellValue('C' . $base, 'Managed By')

                        ->setCellValue('D' . $base, 'City')

                        ->setCellValue('E' . $base, 'Tel')

                        ->setCellValue('F' . $base, 'First Name')

                        ->setCellValue('G' . $base, 'Last Name')

                        ->setCellValue('H' . $base, 'Company')

                        ->setCellValue('I' . $base, 'Fax')

                        ->setCellValue('J' . $base, 'Sal')

                        ->setCellValue('K' . $base, 'Address')

                        ->setCellValue('L' . $base, 'District')

                        ->setCellValue('M' . $base, 'Country')

                        ->setCellValue('N' . $base, 'Position')

                        ->setCellValue('O' . $base, 'DOB')

                        ->setCellValue('P' . $base, 'Email')
                        ->setCellValue('Q' . $base, 'Remarks')
                        ->setCellValue('R' . $base, 'Hotel Produced')
                        ->setCellValue('S' . $base, 'FIT/Series')
                        ->setCellValue('T' . $base, 'Validity From')
                        ->setCellValue('U' . $base, 'Validity To')

                        ->setCellValue('V' . $base, 'Rate code')
                        ->setCellValue('W' . $base, 'Remark FOR RATE ')
                        ->setCellValue('X' . $base, 'Market')
                        ->setCellValue('Y' . $base, 'DMC')
                        ->setCellValue('Z' . $base, 'Comments')
                        ->setCellValue('AA' . $base, 'WEBSITE');

                $objPHPExcel->getActiveSheet()

                        ->getStyle('A' . $base . ':AA' . $base)

                        ->applyFromArray(array(

                            'fill' => array(

                                'type' => PHPExcel_Style_Fill::FILL_SOLID,

                                'color' => array('rgb' => '538DD5'),

                            ),

                            'font' => array(

                                'bold' => true,

                                'color' => array('rgb' => 'FFFFFF'),

                            )

                        ));
                //Next line
                //echo"<pre>";print_r($dataProvidder->getData());die;
                $base++;
                foreach ($dataProvidder->getData() as $model) {                   
                    $objPHPExcel->setActiveSheetIndex(0)                        

                    ->setCellValue('A' . $base, $model->biz_scope)

                    ->setCellValue('B' . $base, $model->account_type)

                    ->setCellValue('C' . $base, $model->managed_by)

                    ->setCellValue('D' . $base, $model->city)

                    ->setCellValue('E' . $base, $model->tel)

                    ->setCellValue('F' . $base, $model->first_name)

                    ->setCellValue('G' . $base, $model->last_name)

                    ->setCellValue('H' . $base, $model->company)

                    ->setCellValue('I' . $base, $model->fax)

                    ->setCellValue('J' . $base, $model->sal)

                    ->setCellValue('K' . $base, $model->address_1)

                    ->setCellValue('L' . $base, $model->address_2)

                    ->setCellValue('M' . $base, $model->country)

                    ->setCellValue('N' . $base, $model->position)

                    ->setCellValue('O' . $base, $model->dob)

                    ->setCellValue('P' . $base, $model->email)
                    ->setCellValue('Q' . $base, $model->remarks)
                    ->setCellValue('R' . $base, $model->hotel_produced)
                    ->setCellValue('S' . $base, $model->fit_series)
                    ->setCellValue('T' . $base, $model->validity_from)
                    ->setCellValue('U' . $base, $model->validity_to)

                    ->setCellValue('V' . $base, $model->rate_code)
                    ->setCellValue('W' . $base, $model->remark_for_rate)
                    ->setCellValue('X' . $base, $model->market)
                    ->setCellValue('Y' . $base, $model->dmc)
                    ->setCellValue('Z' . $base, $model->comment)
                    ->setCellValue('AA' . $base, $model->website);
                    $base++;
                }

                $objPHPExcel->getActiveSheet()->getStyle('A1:AA1')->getFont()->setBold(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('L')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('M')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('N')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('O')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('P')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('Q')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('R')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('S')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('T')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('U')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('V')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('W')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('X')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('Y')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('Z')->setAutoSize(true);

                $objPHPExcel->getActiveSheet()->getColumnDimension('AA')->setAutoSize(true);

                ob_end_clean();

                ob_start();



                if ($type == 'excel') {

                    header('Content-Type: application/vnd.ms-excel');

                    header('Content-Disposition: attachment;filename="' . $date . '.xlsx"');

                    header('Cache-Control: max-age=0');

                    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');

                    $objWriter->save('php://output');

                } elseif ($type == 'csv') {

                    $fileName = $date . '.csv';

                    header('Content-Type: text/csv');

                    header('Content-Disposition: attachment;filename="' . $fileName . '"');

                    header('Cache-Control: max-age=0');

                    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');

                    $objWriter->save('php://output');

                }

            }
        }

    }

	public function loadModel($id)
	{
		$model=Report::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}
}
