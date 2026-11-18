<?php
$form = $this->beginWidget('CActiveForm', array(
    'id' => 'user-form',
    'enableAjaxValidation' => false,
    'enableClientValidation' => true,
    'htmlOptions' => array(
    ),
));
?>
<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">Change password</h1>
    </div>
</div>
<?php 
$none='';
if (Yii::app()->user->hasFlash('change_password')): $none='style="display:none"';?>
    <div class="clearfix">
        <div class="pull-left alert alert-success inline no-margin" style="text-align: center;margin-bottom:20px;width:96%;">
            <button data-dismiss="alert" class="close" type="button">
                    <i class="icon-remove"></i>
            </button>
            <i class="icon-umbrella bigger-120 blue"></i>
            <?php echo Yii::app()->user->getFlash('change_password'); ?>
        </div>
    </div>
<?php endif; ?> 
<?php echo $form->errorSummary($model); ?>

<div class="row" <?php echo $none;?>>
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">Change password</div>
            <div class="panel-body">
    
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'old_password', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->passwordField($model, 'old_password', array('placeholder' =>'Old password','class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'old_password'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'new_password', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->passwordField($model, 'new_password', array('placeholder' =>'New password','class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'new_password'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'confirm_new_password', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->passwordField($model, 'confirm_new_password', array('placeholder' =>'Confirm new password','class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'confirm_new_password'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="right"><button type="submit" class="btn btn-success" name="submit">Save</button></div>
            </div>
        </div>
    </div>
</div>
<?php $this->endWidget();?>