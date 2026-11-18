<?php
$this->breadcrumbs = array(
    'Shipping Order'=>Yii::app()->createAbsoluteUrl("shippingorder/admin"),
    'Shipping Order Details' => Yii::app()->createAbsoluteUrl("shippingorder/view",array("id"=>$model['id'])),
    'Tracking Shipping Order'
);
?>
<div class="page-header position-relative">
    <h1>Tracking Shipping Order</h1>
</div><!--/.page-header-->
<?php
$userList = Users::model()->getList();
$status_array = ShippingStatus::model()->getList2();
$location_array = Locations::model()->getList2();
$form = $this->beginWidget('CActiveForm', array(
    'id' => 'category-form',
    'enableAjaxValidation' => false,
    'enableClientValidation' => true,
    'htmlOptions' => array(
        'class' => 'form-inline'
    ),
));
if(isset($_POST['ShippingOrder']) && $flag){
	echo '<div class="red">Update tracking success!</div>';
}
?>
<div class="row-fluid">
    <div class="span12">
        <!--PAGE CONTENT BEGINS-->
        <?php
        if($model->cod>0):?>
        	<div class="row-fluid">
	            <div class="span3">
	                <div class="control-group">
	                    <?php echo $form->labelEx($model, 'cod_amount', array('class' => 'control-label')); ?>
	                    <div class="controls">
	                        <?php echo $form->textField($model, 'cod_amount', array('class'=>'span12')); ?>
	                        <span class="help-inline"><?php echo $form->error($model, 'cod_amount'); ?></span>
	                    </div>
	                </div>
	            </div>
	            <div class="span3">
	                <div class="control-group">
	                    <?php echo $form->labelEx($model, 'cod_notes', array('class' => 'control-label')); ?>
	                    <div class="controls">
	                        <?php echo $form->textField($model, 'cod_notes', array('class'=>'span12')); ?>
	                        <span class="help-inline"><?php echo $form->error($model, 'cod_notes'); ?></span>
	                    </div>
	                </div>
	            </div>
	        </div>
        <?php endif;?>
        <div class="row-fluid">
            <div class="span3">
                <div class="control-group">
                    <?php echo $form->labelEx($model, 'cod_user', array('class' => 'control-label')); ?>
                    <div class="controls">
                        <?php echo $form->dropDownlist($model, 'cod_user', array(0=>'------')+$userList,array('class'=>'span12')); ?>
                        <span class="help-inline"><?php echo $form->error($model, 'cod_user'); ?></span>
                    </div>
                </div>
            </div>
            <div class="span3">
                <div class="control-group">
                    <?php echo $form->labelEx($model, 'shipping_status', array('class' => 'control-label')); ?>
                    <div class="controls">
                        <?php echo $form->dropDownlist($model, 'shipping_status', array(0=>'------')+$status_array, array('class'=>'span12')); ?>
                        <span class="help-inline"><?php echo $form->error($model, 'shipping_status'); ?></span>
                    </div>
                </div>
            </div>
            <div class="span3">
                <div class="control-group">
                    <?php echo $form->labelEx($model, 'location', array('class' => 'control-label')); ?>
                    <div class="controls">
                        <?php echo $form->dropDownlist($model, 'location', array(0=>'------')+$location_array, array('class'=>'span12')); ?>
                        <span class="help-inline"><?php echo $form->error($model, 'location'); ?></span>
                    </div>
                </div>
            </div>
        </div>
	    <div class="row-fluid">
	        <div class="span6">
	            <div class="control-group">
	                <?php echo $form->labelEx($model, 'reason', array('class' => 'control-label')); ?>
	                <div class="controls">
	                    <?php echo $form->textArea($model, 'reason',array('class'=>'span12')); ?>
	                    <span class="help-inline"><?php echo $form->error($model, 'reason'); ?></span>
	                </div>
	            </div>
	        </div>
	        <div class="span3">
	        	<?php if($model['hold_status'] == 'hold'):?>
		        	<a href="javascript:void(0);" style="vertical-align:-32px;" class="btn btn-warning" id="hold">
		        		<?php echo 'Release';?>
		        	</a>
				<?php else:?>
					<a href="javascript:void(0);" style="vertical-align:-32px;" class="btn btn-purple" id="hold">
		        		<?php echo 'Hold';?>
		        	</a>
				<?php endif;?>
	        </div>
	    </div>
	    <hr>
        <div class="form-actions">
            <button class="btn btn-info submit-form-create" type="submit">
                <i class="icon-ok bigger-110"></i>
                Save
            </button>
        </div>
	</div>
</div>
<?php
$this->endWidget();
$trackingList = (array)json_decode($model->tracking);
if(count($trackingList)>0):?>
<table id="sample-table-1" class="table table-striped table-bordered table-hover">
	<thead>
		<tr>
			<th>Datetime</th>
			<th>Location</th>
			<th>Shipping Status</th>
			<th>Notes</th>
			<th>User</th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach($trackingList as $tracking):
			$tracking=(array)$tracking;?>
			<tr>
				<td><?php if(isset($tracking['cod_datetime'])) echo $tracking['cod_datetime']?></td>
				<td><?php echo $tracking['location'];?></td>
				<td><?php echo $tracking['shipping_status'];?></td>
				<td><?php if(isset($tracking['cod_notes']))  echo $tracking['cod_notes'];?></td>
				<td><?php 
					if(isset($tracking['cod_notes'])){
						$user = Users::model()->getUsernameById($model['cod_user']);
						if($user){
							echo $user['username'];
						}
					}?>
				</td>
			</tr>
		<?php endforeach;?>
	</tbody>
</table>
<?php
endif;?>
<script type="text/javascript">
	$(function(){
		$('#hold').click(function(){
			var reason=$('#ShippingOrder_reason').val();
			var id=<?php echo $model['id'];?>;
			if(!reason){
				alert('Please enter reason');
				$('#ShippingOrder_reason').focus();
			}else{
				$.ajax({
					url: '<?php echo Yii::app()->createAbsoluteUrl("getdata/change_hold")?>',
					data:{id:id,reason:reason},
					type:'post',
					dataType:'json',
					beforeSend:function(){
						$('#loading').show();
					},
					success:function(data){
						if(data=='1'){
							alert('Change success!');
							window.location.reload();
							$('#loading').hide();
						}
					}
				});
			}
		})
	});
</script>