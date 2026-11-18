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
        array(
            'name' => 'user_id',
            'value' => function($data){
                return $data->user ? $data->user->username : '(deleted user)';
            },
            'filter' => CHtml::dropDownlist('UserHistory[user_id]', $model->user_id, array('' => 'All') + Users::model()->getList(), array('class'=>'form-control')),
        ),        
        'login_time',
        'logout_time',
        'ip_address'
    )
));
?>