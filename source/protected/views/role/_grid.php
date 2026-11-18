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
        'role_name',
        array(
            'name' => 'name',
            'header' => 'Module',
            'type' => 'raw',
            'filter' => false,
            'headerHtmlOptions' => array('text-align:left;'),
            'htmlOptions' => array('style' => 'text-align:left;vertical-align:middle'),
            'value' => function($data){
                return $data->showRight();
            }
        ),
        array(
            'header' => 'Actions',
            'headerHtmlOptions' => array('width' => 280,'style' => 'text-align:center;width:80px'),
            'htmlOptions' => array('style' => 'text-align:center;vertical-align:middle;width:80px;'),
            'class' => 'booster.widgets.TbJsonButtonColumn',
            'template' => '{update} {delete}',
            'updateButtonUrl' => 'Yii::app()->baseUrl."/role/update/id/".$data->role_name',
            'deleteButtonUrl' => 'Yii::app()->baseUrl."/role/delete/id/".$data->role_name',
        ),
    )
));
?>