<?php
$this->breadcrumbs = array(
    'Contact'=>array('contact/admin'),
    'Import'
);
?>
<style>
th, td {
	padding: 3px!important;
}
</style>
<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">Import contact from csv file. Click <a href="<?php echo Yii::app()->baseUrl?>/template/template.csv">here</a> to download template</h1>
    </div>
</div>
<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">Add new contact</div>
            <div class="panel-body">
				<?php
				if($flag==''):
					$form = $this->beginWidget('CActiveForm', array(
					    'id' => 'category-form',
					    'enableAjaxValidation' => false,
					    'enableClientValidation' => true,
					    'htmlOptions' => array(
					    	'class'=>'form-horizontal',
					        'enctype' => 'multipart/form-data'
					    ),
					));?>
					<div class="row">
						<div class="col-lg-12">
							<div class="control-group col-lg-8">
								<?php echo $form->labelEx($model, 'attachment', array('class' => 'control-label')); ?>
					            <div class="controls">
					                <?php echo $form->fileField($model, 'attachment',array('class'=>'id-input-file-1')); ?>
					                <span class="help-inline"><?php echo $form->error($model, 'attachment'); ?></span>
					            </div>
							</div>
							<div class="control-group col-lg-4">
					            <button class="btn btn-info btn-small" type="submit">
					                <i class="icon-ok bigger-110"></i>
					                Import
					            </button>
					        </div>
						</div>
					</div>
					<?php $this->endWidget();?>
				<?php else:
					echo $flag;
                        echo CHtml::link('Import another file', array('import'), array('class'=>'btn btn-default'));
				endif;?>
			</div>
		</div>
	</div>
</div>
<style type="text/css">
	select{
		width:auto;
	}
</style>