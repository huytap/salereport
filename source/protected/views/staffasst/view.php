<?php
/* @var $this ContactController */
/* @var $model Contact */

$this->breadcrumbs=array(
	'Contacts'=>array('admin'),
	$model->id,
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
		array(
			'name' => 'user_id',
			'value' => $model->user->username
		),
		'week', 'month', 'year', 'comment'
	),
)); ?>
