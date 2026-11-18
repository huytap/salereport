<?php
$form = $this->beginWidget('CActiveForm', array(
    'id' => 'category-form',
    'enableClientValidation' => true,
    'htmlOptions' => array(
        //'class' => 'form-inline'
    ),
));
?>
<?php echo $form->errorSummary($model);?>
<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">Add new contact</div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'company', array('class' => 'control-label')); ?>
                            <?php echo $form->textField($model, 'company', array('class'=>'form-control', 'placeholder'=>'Company')); ?>
                            <span class="help-inline"><?php echo $form->error($model, 'company'); ?></span>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'biz_scope', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'biz_scope', array('class'=>'form-control', 'placeholder'=>'Biz Scope')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'biz_scope'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelex($model, 'account_type', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropdownList($model, 'account_type', array('' => 'Select one') + Yii::app()->params['account_type'], array('class'=>'form-control chosen-select')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'account_type'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelex($model, 'managed_by', array('class' => 'control-label')); 
                            //$managed_by = Yii::app()->params['managed'];
                            $managed_by = Users::model()->getList2();?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'managed_by', array('' => 'Select one') + $managed_by, array('class'=>'form-control chosen-select')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'managed_by'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'building', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'building', array(''=>'Select or add new building')+ExtraHelper::getListDistinct('Contact', 'building'),array('class'=>'form-control chosen-select-addnew')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'building'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'address_1', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'address_1', array('class'=>'form-control', 'placeholder'=>'Address 1')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'address_1'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'address_2', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'address_2', array(''=>'Select OR add new')+ExtraHelper::getListDistinct('contact', 'address_2'), array('class'=>'form-control chosen-select-addnew')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'address_2'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'city', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'city', array('' => 'Select a city or add new city') + Yii::app()->params['city'] + ExtraHelper::getListDistinct('contact', 'city'), array('class'=>'form-control chosen-select-addnew')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'city'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">                    
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'country', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'country', array('' => 'Select a country') + Yii::app()->params['country'], array('class'=>'form-control chosen-select')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'country'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'first_name', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'first_name', array('class'=>'form-control', 'placeholder'=>'First name')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'first_name'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'last_name', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'last_name', array('class'=>'form-control','placeholder'=>'Last name')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'last_name'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'cell_phone', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'cell_phone', array('class'=>'form-control', 'placeholder'=>'Cell phone')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'cell_phone'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>

                <div class="row">                    
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'tel', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'tel', array('class'=>'form-control', 'placeholder'=>'Tel')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'tel'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'fax', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'fax', array('class'=>'form-control', 'placeholder'=>'Fax')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'fax'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'sal', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'sal', array(''=>'----------')+ExtraHelper::$sal,array('class'=>'form-control chosen-select')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'sal'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'position', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'position', array('' => 'Select a position or add new') + ExtraHelper::getListDistinct('contact', 'position'), array('class'=>'form-control chosen-select-addnew')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'position'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">

                    
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'dob', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'dob', array('class'=>'form-control datepicker')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'dob'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'email', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'email', array('placeholder' => 'Email','class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'email'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'hotel_produced', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'hotel_produced', Yii::app()->params['hotels'],array('class'=>'form-control chosen-select-multiple', 'multiple'=>true)); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'hotel_produced'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'source', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'source', array('class'=>'form-control form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'source'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'remarks', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'remarks', array('Select one') + Yii::app()->params['remarks'],array('class'=>'form-control chosen-select')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'remarks'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'fit_series', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'fit_series', array('Select one') + Yii::app()->params['fit_series'],array('class'=>'form-control chosen-select')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'fit_series'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'website', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'website', array('placeholder' => 'Website','class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'website'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'dmc', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'dmc', array('class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'dmc'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'validity_from', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'validity_from', array('class'=>'form-control datepicker')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'validity_from'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'validity_to', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'validity_to', array('class'=>'form-control datepicker')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'validity_to'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'rate_code', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'rate_code', array('' => 'Select one OR add new')+Yii::app()->params['rate_code']+ExtraHelper::getListDistinct('Contact', 'rate_code'), array('class'=>'form-control chosen-select-addnew')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'rate_code'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php
                             echo $form->labelEx($model, 'market', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->dropDownList($model, 'market', Yii::app()->params['country'] +ExtraHelper::getListDistinct('contact', 'market'), array('class'=>'form-control chosen-select-multiple', 'multiple'=>true)); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'market'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">                    
                    <div class="col-lg-3">
                        <div class="form-group">
                            <?php echo $form->labelEx($model, 'comment', array('class' => 'control-label')); ?>
                            <div class="controls">
                                <?php echo $form->textField($model, 'comment', array('class'=>'form-control')); ?>
                                <span class="help-inline"><?php echo $form->error($model, 'comment'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <?php echo $form->labelEx($model, 'remark_for_rate', array('class' => 'control-label')); ?>
                        <div class="controls">
                            <?php echo $form->textField($model, 'remark_for_rate', array('class'=>'form-control')); ?>
                            <span class="help-inline"><?php echo $form->error($model, 'remark_for_rate'); ?></span>
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