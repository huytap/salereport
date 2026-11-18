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
		'id',
		'company',
		'account_type',
		'managed_by',
		'address_1',
		'address_2',
		'address_3',
		'city',
		'country',
		'tel',
		'cell_phone',
		'fax',
		'sal',
		'first_name',
		'last_name',
		'dob',
		'position',
		'email',
		'remarks',
		'hotel_produced',
		'fit_series',
		'validity_from',
		'validity_to',
		'rate_code',
		'remark_for_rate',
		'market',
		'dmc',
		'comment',
		'website',
		'source',
		'added_date',
		'updated_date',
	),
)); ?>
