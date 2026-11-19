<?php
$this->breadcrumbs = array(
    'Report'=>Yii::app()->createAbsoluteUrl("report/admin"),
    'View'
);
?>
<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">Sales Report #<?php echo $model->id; ?></h1>
    </div>
</div>

<?php $this->widget('zii.widgets.CDetailView', array(
	'data'=>$model,
	'attributes'=>array(
		'title', 'company_name', 'biz_scope', 'phone', 'email', 'company_address', 'first_name', 'last_name', 'position', 'account_type', 'sales_type', 'objective', 'expected_result', 'market', 
		array(
            'name' => 'follow_up',
            'value'=> nl2br(CHtml::encode($model->follow_up)),
            'type' => 'raw'
        ),
        'action', 'done', 'site_ins', 'sales', 'ent', 'date', 
		array(
            'name' => 'report',
            'value'=> nl2br(CHtml::encode($model->report)),
            'type' => 'raw'
        ),
        'hotels', 'new_account', 
        array(
            'name' => 'comments',
            'value'=> nl2br(CHtml::encode($model->comments)),
            'type' => 'raw'
        ),
	),
)); ?>
