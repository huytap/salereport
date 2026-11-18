<?php
class ReportHelper{

	public static function dataContact() {

		$dataProvider = Contact::model()->getList();
		$arr=array();
		$arrTheList = array();
		$arrTheList = $dataProvider->getData();
		$nCount = 0;
		if(is_array($arrTheList)){
			foreach($arrTheList as $k => $v){
				$nCount ++;
				$tmp = array();
				$tmp[] = $v->biz_scope;
				$tmp[] = $v->account_type;
				$tmp[] = $v->managed_by;
				$tmp[] = $v->city;
				$tmp[] = $v->tel;
				$tmp[] = $v->first_name;
				$tmp[] = $v->last_name;
				$tmp[] = $v->address_1;
				$tmp[] = $v->address_2;
				$tmp[] = $v->address_3;				
				$tmp[] = $v->company;
				$tmp[] = $v->country;
				$tmp[] = $v->cell_phone;				
				$tmp[] = $v->fax;
				$tmp[] = $v->sal;	            
	            $tmp[] = $v->position;	            
	            $tmp[] = ExtraHelper::date_2_show($v->dob);
	            $tmp[] = $v->email;
	            $tmp[] = $v->remarks;
	            $tmp[] = $v->hotel_produced;	            
	            $tmp[] = $v->fit_series;
	            $tmp[] = $v->validity_from;
	            $tmp[] = $v->validity_to;
	            $tmp[] = $v->rate_code;
	            $tmp[] = $v->remark_for_rate;
	            $tmp[] = $v->market;
	            $tmp[] = $v->dmc;
	            $tmp[] = $v->comment;
	            $tmp[] = $v->website;
                $arr[] = $tmp;
			}
		}
		return $arr;
	}

	public static function cell_name($row, $col) {
		$chr_ord = ord('A');
		return chr($chr_ord + $col) . $row;
	}
	
	public static function doExport($arrReport, $data, $arrCells=array(), $formated = FALSE, $array_bold = FALSE) {
		/*set_time_limit ( 30000 );
		ini_set('memory_limit', '1024M');*/
		
       $phpExcelPath = Yii::getPathOfAlias('ext.phpexcel.Classes');
      /** PHPExcel_IOFactory */
      require_once $phpExcelPath . DIRECTORY_SEPARATOR . 'PHPExcel/IOFactory.php';
       // Create new PHPExcel object
      $objReader = PHPExcel_IOFactory::createReader('Excel5');
      $objPHPExcel = $objReader->load("template/" . $arrReport['template']);
   
       // Set properties
       $objPHPExcel->getProperties()->setCreator("Contact Software")
      ->setLastModifiedBy("Contact Software")
      ->setTitle("Contact Software Report")
      ->setSubject("Contact Software Report")
      ->setDescription("Contact Software Report")
      ->setKeywords("Contact Software Report")
      ->setCategory("Contact Software Report");
	  
	  	foreach ($arrCells as $key => $val) {
			$objPHPExcel->getActiveSheet()->setCellValue($key, $val);
	  	}
		
        $baseRow = $arrReport['baserow'];
		$row = $baseRow;
        $spacer_row_number = 0;
		$b_new_date = FALSE;
		foreach($data as $r => $dataRow) {
			$bg_row = '';
			if(isset($dataRow['bg_row'])) {
				$bg_row = $dataRow['bg_row'];
				$bg_row = str_replace('#', '', $bg_row);
				unset($dataRow['bg_row']);
			}else{
				$bg_row = '';
			}
			$column_number = count($dataRow);
			$row = $baseRow + $r + $spacer_row_number;
			
			
			$row = $baseRow + $r + $spacer_row_number;

          	if ($r<count($data)-2) {
				$objPHPExcel->getActiveSheet()->insertNewRowBefore($row+1,1);
			}
			$objPHPExcel->getActiveSheet()->getRowDimension($row+1)->setRowHeight(-1);
			$column_count = 0;

			$flag_bold = false;
			foreach ($dataRow as $k => $val) {
				if($val){
				$cell_name = ReportHelper::cell_names($row, $column_count);
				
				if(is_array($val)){
					if(count($val)>0){
						$kk=0;
						$count_val = count($val);
						foreach($val as $vv){
							$row = $baseRow + $r + $spacer_row_number;
							$cell_name = ReportHelper::cell_names($row+$kk, $column_count);
							$objPHPExcel->getActiveSheet()->getRowDimension($row)->setRowHeight(-1);
							$objPHPExcel->getActiveSheet()->setCellValue($cell_name, $vv);
							$val = $vv;
							$kk++;
						}
					}else{
						$val = '';
						$objPHPExcel->getActiveSheet()->getRowDimension($row + $column_count)->setRowHeight(-1);
						$objPHPExcel->getActiveSheet()->setCellValue($cell_name, $val);						
					}
				}

				$column_count ++;
				$val = str_ireplace("<br/>", "\r\n", $val);
				$val = str_ireplace("<br>", "\r\n", $val);

				$link = '';
				$pos1 = strpos($val, "href='");

				if ($pos1!==FALSE) {
					$pos1 += strlen("href='");
					$pos2 = strpos($val, "'", $pos1);
					if ($pos2!==FALSE) {
						$link = substr($val, $pos1, $pos2-$pos1);
					}
				}
				if ($link) {
					$val = strip_tags($val);
				}		

				$objPHPExcel->getActiveSheet()->setCellValue($cell_name, $val);
				
				if ($bg_row) {
					$objPHPExcel->getActiveSheet()->getStyle($cell_name)->getFill()
					->applyFromArray(array('type' => PHPExcel_Style_Fill::FILL_SOLID, 'startcolor' => array('rgb' => $bg_row)));
				}

				if ($link) {
					$objPHPExcel->getActiveSheet()->getCell($cell_name)->getHyperlink()->setUrl($link);
					$styleArray = array(
						'font'  => array(
							'color' => array('rgb' => '0000FF'),
							'underline' => PHPExcel_Style_Font::UNDERLINE_SINGLE
						));
					$objPHPExcel->getActiveSheet()->getStyle($cell_name)->applyFromArray($styleArray);
				}

				$objPHPExcel->getActiveSheet()->getStyle($cell_name)->getAlignment()->setWrapText(true);
			}
			}
		}
		$filename = $arrReport['filename'];

        // Redirect output to a client’s web browser (Excel2007)
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        Yii::app()->end();
   
         // Once we have finished using the library, give back the 
         // power to Yii... 
         spl_autoload_register(array('YiiBase','autoload'));	
	}

	public static function cell_names($row, $col) {
		$chr_ord = ord('A');
		return chr($chr_ord + $col) . $row;
	}
}?>