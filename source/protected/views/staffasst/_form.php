<?php
$form = $this->beginWidget('CActiveForm', array(
    'id' => 'category-form',
    'enableClientValidation' => true,
    'htmlOptions' => array(
    ),
));
?>
<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">Add new staff assegment</div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'user_id', array('class' => 'control-label')); ?>
                            <?php echo $form->dropDownList($model, 'user_id', array('' => '----------') + Users::model()->getList(), array('class'=>'form-control')); ?>
                            <span class="help-inline"><?php echo $form->error($model, 'user_id'); ?></span>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'week', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'week', array('' => '-------------') + Yii::app()->params['week'], array('class'=>'form-control', 'placeholder'=>'What week?')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'week'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'month', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'month', array('' => '-------------') + Yii::app()->params['month'], array('class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'month'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <hr>
                <div class="row">                    
                    <div class="col-lg-6">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'comment', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textArea($model, 'comment', array('class'=>'form-control', 'placeholder'=>'Comment')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'comment'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="right"><button type="submit" class="btn btn-success" name="submit">Save</button></div>
            </div>
        </div>
    </div>
</div>

<?php $this->endWidget(); ?>
<link rel="stylesheet" href="<?php echo Yii::app()->request->baseUrl; ?>/chosen/chosen.css">
<script src="<?php echo Yii::app()->request->baseUrl; ?>/chosen/chosen.jquery.js" type="text/javascript"></script>
<script type="text/javascript">
    var config = {
    '.chosen-select'           : {width:"150px"},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
    var config = {
    '.chosen-select-multiple'           : {width:"291.5px"},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
    var config = {
    '.chosen-select-addnew'           : {width:"250px", is_enternew:true},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
    
</script>
<style type="text/css">
    .chosen-container-single.chosen-container-single-nosearch .chosen-search{
        position: relative;
        left:0;
    }
</style>
<link href="<?php echo Yii::app()->baseUrl?>/css/ui-lightness/jquery-ui-1.10.3.custom.css" rel="stylesheet">
<script src='<?php echo Yii::app()->baseUrl?>/js/jquery-ui-1.10.3.custom.js'></script>
<script>
    $(function() {
        $( ".datepicker").datepicker({
            defaultDate: "",
            yearRange : "-100:+30",
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            onClose: function( selectedDate ) {
            }
        });
    });
</script>