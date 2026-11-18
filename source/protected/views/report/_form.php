<?php
$form = $this->beginWidget('CActiveForm', array(
    'id' => 'sales-form',
    'enableClientValidation' => true,
    'htmlOptions' => array(
        //'class' => 'form-inline'
    ),
));
?>
<?php //echo $form->errorSummary($model);?>
<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">Add/update report</div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-4">
                        <h3>COMPANY INFORMATION</h3>
                        <hr>
                        <div class="row">
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelEx($model, 'date', array('class' => 'control-label')); ?>
                                    <?php //echo $form->textField($model, 'date', array('class'=>'form-control datepicker', 'placeholder'=>'Date', 'disabled'=>'disabled')); ?>
                                    <span><?php echo $model->date?></span>
                                    <input type="hidden" value="<?=$model->date?>" name="Report[date]">
                                    <span class="help-inline"><?php echo $form->error($model, 'date'); ?></span>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelEx($model, 'company_name', array('class' => 'control-label')); ?>
                                    <?php echo $form->dropdownList($model, 'company_name', array(''=>'--------------') + ExtraHelper::getListDistinct('Report', 'company_name'), array('class'=>'form-control chosen-select-addnew', 'id' =>'company')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'company_name'); ?></span>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelEx($model, 'company_address', array('class' => 'control-label')); ?>
                                    <?php echo $form->textField($model, 'company_address', array('class'=>'form-control', 'placeholder'=>'Company Address')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'company_address'); ?></span>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelEx($model, 'email', array('class' => 'control-label')); ?>
                                    <?php echo $form->textField($model, 'email', array('class'=>'form-control', 'placeholder'=>'Email')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'email'); ?></span>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelEx($model, 'phone', array('class' => 'control-label')); ?>
                                    <?php echo $form->textField($model, 'phone', array('class'=>'form-control', 'placeholder'=>'Tel')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'phone'); ?></span>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelEx($model, 'biz_scope', array('class' => 'control-label')); ?>
                                    <?php echo $form->dropdownList($model, 'biz_scope', array(''=>'Select OR add new') + ExtraHelper::getListDistinct('Report', 'biz_scope'), array('class'=>'form-control chosen-select-addnew')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'biz_scope'); ?></span>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'hotels', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'hotels', Yii::app()->params['hotels'],array('class'=>'form-control chosen-select-addnew', 'multiple' => true)); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'hotels'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'title', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'title', array(''=>'-----------')+ExtraHelper::$sal ,array('class'=>'form-control')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'title'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'first_name', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'first_name', array(''=>'Select OR add new')+ExtraHelper::getListDistinct('Report', 'first_name'),array('class'=>'form-control chosen-select-addnew')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'first_name'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'last_name', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'last_name', array(''=>'Select OR add new')+ExtraHelper::getListDistinct('Report', 'last_name'),array('class'=>'form-control chosen-select-addnew')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'last_name'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelEx($model, 'position', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'position', array('' => 'Select a position or add new') + ExtraHelper::getListDistinct('report', 'position'), array('class'=>'form-control chosen-select-addnew')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'position'); ?></span>
                                    </div>
                                </div>
                            </div>                    
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelex($model, 'account_type', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropdownList($model, 'account_type', array('' => '--------------') + Yii::app()->params['account_type2'], array('class'=>'form-control chosen-select')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'account_type'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelex($model, 'sales_type', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropdownList($model, 'sales_type', array('' => '--------------') + Yii::app()->params['sales_type'], array('class'=>'form-control chosen-select')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'sales_type'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <h3>SALES CALLS DETAILS</h3>                
                        <hr>
                        <div class="row">
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'objective', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'objective', array(''=>'--------------')+Yii::app()->params['objective'],array('class'=>'form-control chosen-select')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'objective'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'expected_result', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'expected_result', array(''=>'--------------')+Yii::app()->params['expected_result'],array('class'=>'form-control')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'expected_result'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'market', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'market', array(''=>'Select OR add new')+Yii::app()->params['market'] + ExtraHelper::getListDistinct('Report', 'market'), array('class'=>'form-control chosen-select-addnew')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'market'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'report', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->textArea($model, 'report', array('class'=>'form-control', 'style'=>'height:183px;')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'report'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php //if(Yii::app()->user->id == 1 || Yii::app()->user->getState('type') == 'superadmin' || Yii::app()->user->getState('type') == 'admin'){?>
                                <div class="col-lg-12 col-sm-6">
                                    <div class="form-group">
                                        <?php
                                         echo $form->labelEx($model, 'comments', array('class' => 'control-label')); ?>
                                        <div class="controls">
                                            <?php echo $form->textArea($model, 'comments', array('class'=>'form-control', 'style'=>'height:183px;')); ?>
                                            <span class="help-inline"><?php echo $form->error($model, 'comments'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php //}?>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'city', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'city', array('' => 'Select a city or add new city') + Yii::app()->params['city'] + ExtraHelper::getListDistinct('report', 'city'), array('class'=>'form-control chosen-select-addnew')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'city'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'country', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'country', array('' => 'Select a country') + Yii::app()->params['country'], array('class'=>'form-control chosen-select')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'country'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <h3>FOLLOW UP</h3>
                        <hr>
                        <div class="row">                    
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'follow_up', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->textArea($model, 'follow_up', array('class'=>'form-control')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'follow_up'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'done', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'done', array('' => '--------', 'Yes'=>'Yes', 'No'=>'No'), array('class'=>'form-control')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'done'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <?php
                                     echo $form->labelEx($model, 'new_account', array('class' => 'control-label')); ?>
                                    <div class="controls">
                                        <?php echo $form->dropDownList($model, 'new_account', array('' => '--------', 'Yes'=>'Yes', 'No'=>'No'), array('class'=>'form-control')); ?>
                                        <span class="help-inline"><?php echo $form->error($model, 'new_account'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelEx($model, 'ent', array('class' => 'control-label')); ?>
                                    <?php echo $form->textField($model, 'ent', array('class'=>'form-control datepicker', 'placeholder'=>'ENT')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'ent'); ?></span>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelEx($model, 'sales', array('class' => 'control-label')); ?>
                                    <?php echo $form->textField($model, 'sales', array('class'=>'form-control datepicker', 'placeholder'=>'Sales')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'sales'); ?></span>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelEx($model, 'site_ins', array('class' => 'control-label')); ?>
                                    <?php echo $form->textField($model, 'site_ins', array('class'=>'form-control datepicker', 'placeholder'=>'Site Ins')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'site_ins'); ?></span>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-6">
                                <div class="form-group">
                                    <?php echo $form->labelEx($model, 'highlight', array('class' => 'control-label')); ?>
                                    <?php echo $form->dropDownList($model, 'highlight', array('' => '--------', 1=>'Yes', 0=>'No'), array('class'=>'form-control')); ?>
                                    <span class="help-inline"><?php echo $form->error($model, 'highlight'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="center"><button type="submit" class="btn btn-success" name="submit">Save</button></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->endWidget(); ?>
<?php
if(Yii::app()->controller->action->id == 'create' || 
    Yii::app()->controller->action->id == 'getbycompany'){?>
    <script type="text/javascript">
        $('#company').change(function(){
            var company_name = $(this).val();
            if(company_name){
                $.ajax({
                    url: '<?php echo Yii::app()->baseUrl?>/report/create',
                    data: {company:company_name},
                    async: false,
                    type: 'post',
                    success:function(data){
                        $('#report_fill').html(data)
                    }
                })
            }
        });

        jQuery( window ).load(function() {
            jQuery('#company').trigger("change");
        });
    </script>
<?php }?>