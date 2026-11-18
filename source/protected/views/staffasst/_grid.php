<?php
$this->widget('booster.widgets.TbJsonGridView', array(
    'type' => 'table table-striped table-bordered table-hover',
    'id' => 'dataTables-example',
    'dataProvider' => $model->search(),
    'htmlOptions' => array('class' => 'table table-striped table-bordered table-hover'),
    'filter' => $model,
    'json' => false,
    'enableSorting' => true,
    'ajaxUpdate' => false,
    'columns' => array(
        array(
            'name' => 'user_id',
            'htmlOptions'=>array('style'=>'width:100px;'),
            'value' => function($data){
                return Users::model()->getUsernameById2($data->user_id);
            },
            'filter' => CHtml::dropDownlist('StaffAsst[user_id]', $model->user_id, array('' => 'All') +Users::model()->getList(), array('class'=>'form-control')),
        ),
        array(
            'name' => 'week',
            'htmlOptions'=>array('style'=>'width:100px;'),
            'value' => function($data){
                return $data->week;
            },
            'filter' => CHtml::dropDownlist('StaffAsst[week]', $model->week, array('' => 'All')+Yii::app()->params['week'], array('class'=>'form-control')),
        ),
        array(
            'name' => 'month',
            'htmlOptions'=>array('style'=>'width:100px;'),
            'value' => function($data){
                return $data->month;
            },
            'filter' => CHtml::dropDownlist('StaffAsst[month]', $model->month, array('' => 'All')+Yii::app()->params['month'], array('class'=>'form-control')),
        ),
        array(
            'name' => 'year',
            'htmlOptions'=>array('style'=>'width:100px;'),
            'value' => function($data){
                return $data->year;
            },
            'filter' => CHtml::dropDownlist('StaffAsst[year]', $model->year, array('' => 'All')+ExtraHelper::getListDistinct('StaffAsst', 'year'), array('class'=>'form-control')),
        ),
        'comment',
        array(
            'header' => 'Action',
            'htmlOptions' => array('width' => 60,'style' => 'text-align:center;vertical-align:middle;width:80px;'),
            'class' => 'booster.widgets.TbJsonButtonColumn',
            'template' => '{view} {update} {delete}',
        ),
    )
));
?>