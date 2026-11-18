<?php
$this->widget('booster.widgets.TbJsonGridView', array(
    'type' => 'table table-striped table-bordered table-hover',
    'id' => 'dataTables-example',
    'dataProvider' => $model->search(),
    //'template' => "{items}\n{pager}",
    'htmlOptions' => array('class' => 'table table-striped table-bordered table-hover'),
    'filter' => $model,
    'json' => false,
    'enableSorting' => true,
    'ajaxUpdate' => false,
    'columns' => array(
        'company',
        array('name' => 'biz_scope',
            'value' => function($data){
                return $data->biz_scope;
            },
            'htmlOptions'=>array('style'=>'width:80px;'),
            'filter' => CHtml::dropDownlist('Contact[biz_scope]', $model->biz_scope, array(''=>'All')+ExtraHelper::getListDistinct('contact','biz_scope'),array('class'=>'form-control chosen-select')),
        ),
        array('name' => 'account_type',
            'value' => function($data){
                return $data->account_type;
            },
            'filter' => CHtml::dropDownlist('Contact[account_type]', $model->account_type, array(''=>'All')+Yii::app()->params['account_type'], array('class'=>'form-control')),
        ),
        array('name' => 'managed_by',
            'value' => function($data){
                return $data->managed_by;
            },
            'filter' => CHtml::dropDownlist('Contact[managed_by]', $model->managed_by, array(''=>'All')+Yii::app()->params['managed'], array('class'=>'form-control')),
        ),
    	array('name' => 'city',
    		'value' => function($data){
    			return $data->city;
    		},
    		'filter' => CHtml::dropDownlist('Contact[city]', $model->city, array(''=>'All')+Yii::app()->params['city'] + ExtraHelper::getListDistinct('contact', 'city'), array('class'=>'form-control')),
    	),
        'tel',
        'first_name',
        'last_name',
        'address_2',
        array(
            'header' => 'Action',
            'htmlOptions' => array('width' => 60,'style' => 'text-align:center;vertical-align:middle;width:80px;'),
            'class' => 'booster.widgets.TbJsonButtonColumn',
            'template' => '{view} {update} {delete}',
        ),
    )
));
?>