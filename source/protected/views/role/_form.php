<?php
$arrList= Yii::app()->params['all_roles'];
$arrRoles=array();
$arrAction=array();
foreach($arrList as $k => $v){	
	$arrRoles[$k]=$k;
}
$v2 = array();

foreach ($v as $val) {
	$v2[$val] = $val;
}?>

<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'role-form',
	'enableAjaxValidation'=>false,
	'enableClientValidation' => true,
	'htmlOptions' => array('class'=>'no-margin'),
)); ?>
<?php //echo $form->errorSummary($model); ?>
<div class="panel panel-default">
    <div class="panel-body">
        <h3>Condition</h3>
        <hr>
		<div class="form-group">
			<?php echo $form->labelEx($model,'role_name'); ?>
			<?php echo $form->textField($model,'role_name', array('class'=>'form-control')); ?>
			<span class="help-inline"><?php echo $form->error($model,'role_name'); ?></span>
		</div>   
		<div class="form-group">
			<?php echo $form->labelEx($model,'remark'); ?>
			<?php echo $form->textField($model,'remark',array('class'=>'form-control')); ?>
			<span class="help-inline"><?php echo $form->error($model,'remark'); ?></span>
		</div>
		<div class="form-group">
			<?php echo $form->labelEx($model,'access_rights'); ?>
			<span class="help-inline"><?php echo $form->error($model,'access_rights'); ?></span>
		</div>
		<table id="sample-table-1" class="table table-striped table-bordered table-hover">
			<thead>
				<tr>				
					<th>Modules</th>
					<th>Action</th>
					<th class="center"></th>
				</tr>
			</thead>
			<tbody>
			<?php 
			$module = Yii::app()->params['module'];
	 		$view = Yii::app()->params['list_view_module'];
			foreach($arrList as $x=>$y):
				if(isset($module[$x])){?>
	            <tr>
	              	<td width="150px" class="center">
	              		<?php echo $module[$x];?>
	       		 	</td>
	              <td width="750px" class="list">     
	                <?php
					   	$arr_selected = array();
					   	if($model->role_name){
						   	$arrRoles = Role::model()->getNameRoleById($model->role_name);
		           			foreach ($arrRoles as $kk => $vv) {
		               			foreach ($vv as $ac) {
		                       		$arr_selected[] = "$kk" . "_" . $ac;
		                   		}
							} 
						}
	                	for($j=0;$j<count($y);$j++){
	                		if(isset($view[$y[$j]])){
	                        $checked = '';
	                        $ac_val = $x."_".$y[$j];
	                        if (in_array($ac_val, $arr_selected)) {
	                        	$checked = 'checked'; 
	                    	}?>
		                    <label class="labelEx-checkbox inline">
								<input class="check_act" type="checkbox" name="checkbox[]" value="<?php echo $ac_val; ?>" <?php echo $checked; ?>/>
								<span class="custom-checkbox"></span> <?php echo $view[$y[$j]] ?>
							</label>
	                	<?php }
	                	}?>                         
	              	</td>
	              	<td width="150px">
	              		<label class="labelEx-checkbox inline">
							<input type="checkbox" class="chonhet" />
							<span class="custom-checkbox"></span> Check All
						</label>
	              	</td>  
	            	</tr>
	            <?php
	        	}
            endforeach; ?>
			</tbody>
		</table>
		<div class="row" style="margin-top:10px;">
            <div class="center"><button type="submit" class="btn btn-success" name="submit">Save</button></div>
        </div>
    </div>
</div>
<?php $this->endWidget(); ?>
<script>
$(document).ready(function(e) {
	$('#sample-table-1').find('tr').each(function(i, row){
		$(row).find(".chonhet").click(function(){
			var checked_status = '';
			if($(this).is(':checked')){
				checked_status = true;
			}else{
				checked_status = false;
			}
			$(row).find('.list').find("input[type=checkbox]").each(function(j, jrow){
				$(jrow).attr('checked', checked_status)
			})
			
		});
	})
   
});
</script>
</div>