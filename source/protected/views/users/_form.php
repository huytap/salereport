<?php
$form = $this->beginWidget('CActiveForm', array(
    'id' => 'user-form',
    'enableAjaxValidation' => false,
    'enableClientValidation' => true,
    'htmlOptions' => array(
        //'class' => 'form-inline'
    ),
));
?>
<?php echo $form->errorSummary($model); ?>

<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">User</div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'username', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'username', array('placeholder' =>'Username','class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'username'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'fullname', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'fullname', array('placeholder' =>'Full name','class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'fullname'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'cellphone', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'cellphone', array('placeholder' =>'Cellphone','class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'cellphone'); ?></span>
                            </div>
                        </div>
                    </div>          
                </div>
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            
                            <div class="controls">
                                <?php
                                if(Yii::app()->controller->action->id == 'create'){?>
                                    <?php echo $form->labelEx($model, 'password', array('class' => 'control-label')); ?>
                                    <?php echo $form->passwordField($model, 'password', array('placeholder' =>'Password','class'=>'form-control','value'=>'','autocomplete'=>'new-password')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'password'); ?></span>
                                <?php }else{?>
                                    <?php echo $form->labelEx($model, 'new_password', array('class' => 'control-label')); ?>
                                    <?php echo $form->passwordField($model, 'new_password', array('placeholder' =>'New Password','class'=>'form-control','value'=>'','autocomplete'=>'new-password')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'new_password'); ?></span>
                                <?php }?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'email', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'email', array('placeholder' =>'Email','class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'email'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'status', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownlist($model, 'status', ExtraHelper::$user_status, array('class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'status'); ?></span>
                            </div>
                        </div>
                    </div>
                                
                </div>

                <?php
                if((Yii::app()->controller->action->id == 'create' && Yii::app()->user->id == $model->id) || 
                    Yii::app()->user->getState('type') == 'superadmin' || Yii::app()->user->id==1){?>
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="form-group">
                                <?php echo $form->labelEx($model, 'hotel', array('class' => 'control-label')); ?>
                                <div class="controls">
                                    <?php echo $form->dropDownlist($model, 'hotel', array(''=>'All') + Yii::app()->params['brand'], array('class'=>'form-control other-chosen-select')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'hotel'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <?php echo $form->labelEx($model, 'group', array('class' => 'control-label')); ?>
                                <div class="controls">
                                    <?php echo $form->dropDownlist($model, 'group', Yii::app()->params['user_group'], array('class'=>'form-control other-chosen-select')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'group'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group">
                                <?php  

                                    /*$arr_roles = Yii::app()->params['access_right'];
                                    if(Yii::app()->user->getState('type') == 'admin'){
                                        unset($arr_roles['superadmin']);
                                        unset($arr_roles['admin']);
                                    }
                                     foreach($arr_roles as $k => $v){
                                        $roles[$k] = $k;                      
                                     }*/ 
                                    $arr_roles = Role::model()->getAllRoleName();
                                    $roles=array();
                                    foreach($arr_roles as $k => $v){
                                        $roles[$k] = $k;                      
                                    }
                                    ?>
                                    <?php echo $form->labelEx($model, 'roles', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownlist($model, 'roles', $roles, array('class'=>'form-control other-chosen-select')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'roles'); ?></span>
                                    </div>
                            </div>
                        </div>
                    </div>
                <?php }?>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">

                            <?php echo $form->labelEx($model, 'remarks', array('class' => 'control-label'));?>
                            <div class="controls">
                                <?php echo $form->textArea($model, 'remarks', array('placeholder'=>'Remarks','rows'=>5,'class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'remarks'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="form-actions">
                        <button class="btn btn-info submit-form-create" type="submit">
                            <i class="icon-ok bigger-110"></i>
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->endWidget(); ?>
<link rel="stylesheet" href="<?php echo Yii::app()->request->baseUrl; ?>/chosen/chosen.css">
<script src="<?php echo Yii::app()->request->baseUrl; ?>/chosen/chosen.jquery.js" type="text/javascript"></script>
<script type="text/javascript">
    var config = {
    '.chosen-select'           : {width:"250px"},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
    var config = {
    '.chosen-select-small'           : {width:"118px"},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
    
    var config = {
        '.other-chosen-select-multi'            : {width:"260px", multiple: true, search_contains: true,max_selected_options: 6},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
    
</script>