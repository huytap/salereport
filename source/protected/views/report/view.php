<?php
$this->breadcrumbs = array(
    'Report'=>Yii::app()->createAbsoluteUrl("contact/admin"),
    'View'
);
?>
<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">View Contact #<?php echo $model->id; ?></h1>
    </div>
</div>

<?php $this->widget('zii.widgets.CDetailView', array(
	'data'=>$model,
	'attributes'=>array(
		'title', 'company_name', 'biz_scope', 'phone', 'email', 'company_address', 'first_name', 'last_name', 'position', 'account_type', 'sales_type', 'objective', 'expected_result', 'market', 
		array(
            'name' => 'follow_up',
            'value'=> str_replace("\n", '<br>', $model->follow_up),
            'type' => 'raw'
        ),
        'action', 'done', 'site_ins', 'sales', 'ent', 'date', 
		array(
            'name' => 'report',
            'value'=> str_replace("\n", '<br>', $model->report),
            'type' => 'raw'
        ),
        'hotels', 'new_account', 
        array(
            'name' => 'comments',
            'value'=> str_replace("\n", '<br>', $model->comments),
            'type' => 'raw'
        ),
	),
)); ?>
