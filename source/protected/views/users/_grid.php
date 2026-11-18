<?php
$this->widget('booster.widgets.TbJsonGridView', array(
    'type' => 'table table-striped table-bordered table-hover dataTable',
    'id' => 'shippingstatus-grid',
    'dataProvider' => $model->search(),
    'template' => "{items}\n{pager}",
    'htmlOptions' => array('class' => 'table table-striped table-bordered table-hover dataTable'),
    'filter' => $model,
    'json' => true,
    'enableSorting' => true,
    'columns' => array(
        'username',        
        'fullname',
        'roles',
        'hotel',
        'cellphone',
        'email',
        array(
            'header' => 'Status',
            'type' => 'raw',
            'value' => function($data){
                return ExtraHelper::$user_status[$data->status];
            },
            'headerHtmlOptions' => array('width' => 20,'style' => 'text-align:center;'),
            'htmlOptions' => array('style' => 'text-align:center;vertical-align:middle')
        ),

        'remarks',
        array(
            'header' => 'Action',
            'htmlOptions' => array('style' => 'text-align:center;vertical-align:middle;width:150px;'),
            'class' => 'booster.widgets.TbJsonButtonColumn',
            'template' => '{view} {update} {delete}',
            'buttons' => array('delete' => array('visible' => '$data->id != 1 && $data->id != Yii::app()->user->id')),
        )
    )
));
?>