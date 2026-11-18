<?php
$this->breadcrumbs = array(
    'Users' => array('admin'),
    'Import'
);
?>
<div class="page-header position-relative">
    <h1>List email import</h1>
</div>
<ul>
	<?php
	$form = $this->beginWidget('CActiveForm', array(
	    'id' => 'userimport-form',
	    'enableAjaxValidation' => false,
	    'enableClientValidation' => true,
	    'htmlOptions' => array(
	        'class' => 'form-inline'
	    ),
	));
		$j=0;
		foreach($Inbox as $i){		
			$array = explode('<', $i['from']);

			if(isset($array[1]) && strpos($array[1], '@')){
				$email = explode('>',$array[1]);
				$check = Users::model()->checkByEmail($email[0]);
				if(!$check){
					echo '<li><input type="checkbox" name="Users['.$j.'][email]" value="'.$email[0].'"/><span class="lbl"> '.$array[0] .' ['.$email[0].']</span></li>';
					echo CHtml::hiddenField("Users[$j][fullname]", $array[0]);
					$j++;
				}else{
					echo '<li>'.$array[0].' ['.$email[0].'] <b>is exists in database</b></li>';
				}
			}else{
				echo '<li>'.$array[0].'</li>';
			}
		}
		foreach ($Sent as $value) {
			if(isset($value['to'])){
				foreach($value['to'] as $to){
					$array = explode('<', str_replace(' ', '',$to));
					$mail_ = explode('>', $array[1]);
					$check = Users::model()->checkByEmail($mail_[0]);
					if(!$check){
						echo '<li><input type="checkbox" name="Users['.$j.'][email]" value="'.$mail_[0].'"/><span class="lbl"> '.$array[0].' ['.$mail_[0].']</span></li>';
						echo CHtml::hiddenField("Users[$j][fullname]", $array[0]);
						$j++;
					}else{
						echo '<li>'.$array[0].' ['.$mail_[0].'] <b>is exists in database</b></li>';
					}
				}
			}
		}
		?>
		
		<div class=""><input type="submit" class="btn btn-small btn-success" value="Import" name="submit"></div>
	<?php $this->endWidget(); ?>
</ul>