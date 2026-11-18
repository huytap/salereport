<?php
class ExtraHelper {
	public static $gender=array(
		'male'=>'Male',
		'female'=>'Female'
	);

	public static $sal=array('Mr'=>'Mr.','Ms'=>'Ms.','Mrs'=>'Mrs.');
	public static $gender_title=array('male'=>'Mr','female'=>'Ms');
	public static $user_status = array(
		'A' => 'Active',
		'I' => 'Inactive',
		'P' => 'Pendding'
	);
	public static function date_2_show($date, $year='') {
		$ret = '';
		if (!$date) {
			return $year ? $year : '';
		}
		if ($date=='0000-00-00' || $date=='0000-00-00 00:00:00') {
			return $year ? $year : '';
		}
		return date("d-M-Y", strtotime($date));
	}

	public static function date_2_save($str) {
		$str = strtolower(trim($str));
		$arr = $arr2 = array();
		if ($str) {
			$arr = explode("-", $str);
			$arr2 = explode("/", $str);
		}
		if (count($arr)<3 && count($arr2)<3) {
			return array('valid' => FALSE, 'error' => 'Invalid date format', 'date' => '');
		}
		if (count($arr)>=3) {
			$the_date = strtotime($arr[2] . "-" . $arr[1] . "-" . $arr[0]);
		} else {
			$the_date = strtotime($arr2[2] . "-" . $arr2[1] . "-" . $arr2[0]);
		}
		$date = date("Y-m-d", $the_date);
		if ($date=="1970-01-01") {
			if (count($arr)>=3) {
				if ((int)($arr[0])==1 && ((int)($arr[1])==1 || $arr[1]=='jan' || $arr[1]=='january')) {
					return array('valid' => TRUE, 'error' => '', 'date' => $date);
				} else {
					return array('valid' => FALSE, 'error' => 'Invalid date', 'date' => '');
				}
			} else {
				if ((int)($arr2[0])==1 && ((int)($arr2[1])==1 || $arr2[1]=='jan' || $arr2[1]=='january')) {
					return array('valid' => TRUE, 'error' => '', 'date' => $date);
				} else {
					return array('valid' => FALSE, 'error' => 'Invalid date', 'date' => '');
				}
			}		
		
		} else {
			return array('valid' => TRUE, 'error' => '', 'date' => $date);
		}
	}
	
	//write log 
    public static function writeLog($type, $object_type, $object_id, $object_name, $old_value, $new_value){
        
        if (Yii::app()->user->id<=0) {
            return FALSE;
        }

        $log = new Trackings;
            $log->type = $type;
            $log->object_id = $object_id;
            $log->object_type = $object_type;
            $log->object_name = $object_name;
            $old_value = ($old_value !== null ? $old_value->attributes : $old_value);
            $new_value = ($new_value !== null ? $new_value->attributes : $new_value);
        if($type !== 'delete'){         
            if($old_value !== null){
                $old_value = array_diff($new_value, $old_value);
                $new_ = '{';
                $i=0;
                foreach ($old_value as $key => $value) {
                    if(isset($new_value[$key])){
                        $new_ .= '"' . $key .'":' . '"' . $new_value[$key] .'"';
                        if($i>= count($old_value)-1){
                            $new_ .='';
                        }else{
                            $new_ .= ',';
                        }
                    }else{
                    }
                    $i++;
                }
                $new_ .= '}';
                $log->value = '{"old_value":['.json_encode($old_value).'], "new_value":[' .$new_ . ']}';
            }else{
                $log->value = '{"old_value":['.json_encode($old_value).'], "new_value":[' .json_encode($new_value) . ']}';
            }
        }else{
            $log->value = '{"old_value":['.json_encode($old_value).'], "new_value":[]}';
        }
        $log->date = date('Y-m-d H:i:s');
        $log->user_id = Yii::app()->user->id;       
        $log->save();

        return TRUE;    
    }
    
	public static function update_tracking_data(&$model, $update_type="") {
		$now = date('Y-m-d H:i:s');
		if (strtolower($update_type)=="create" || $model->isNewRecord) {
			$model->added_date = $now;
		}
		$model->updated_date = $now;
		$model->updated_by = Yii::app()->user->id;
	}
	public static function getListDistinct($object_name, $field_name, $condition=''){
		if (!$field_name) {
			return array();
		}
		$criteria=new CDbCriteria;
		$criteria->distinct = true;
		$criteria->select = array($field_name);
		
		if ($condition) {
			$criteria->addCondition($condition);
		}

		$dataProvider = new CActiveDataProvider($object_name, array(
			'criteria'=>$criteria,
		));
		$dataProvider->setPagination(false);
		$arrTheList = array();
		$arrTheList = $dataProvider->getData();
		$arrCates = array();
		if(is_array($arrTheList)){
			foreach($arrTheList as $k => $v){
				$text = $v[$field_name];
				$tmp = trim($text);
				if ($tmp) {
					$arrCates[$text] = $text;
				}
			}
		}
		asort($arrCates);
        return $arrCates;
	}
}