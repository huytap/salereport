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
        array(
            'name' => 'date',
            'htmlOptions'=>array('style'=>'width:100px;'),
            'value'=> function($data){
                $ht = ExtraHelper::date_2_show($data->date);
                if($data->highlight){
                    $ht .= '<span style="display:none;" class="bgcolor">rgb(255, 255, 0)</span>';
                }
                return $ht;
            },
            'headerHtmlOptions' => array('width' => 110),
            'type' => 'raw',
            'filter' => CHtml::textField('Report[date]', $model->date,array('class'=>'form-control datepicker'))
        ),
        array('name' => 'company_name',
            'value' => function($data){
                return $data->company_name;
            },
            'htmlOptions'=>array('style'=>'width:80px;'),
            'headerHtmlOptions' => array('width' => 80),
            'filter' => CHtml::dropDownlist('Report[company_name]', $model->company_name, array(''=>'All')+ExtraHelper::getListDistinct('report','company_name'),array('class'=>'form-control')),
        ),
        
        array('name' => 'sales_type',
            'value' => function($data){
                return $data->sales_type;
            },
            'headerHtmlOptions' => array('width' => 60),
            'filter' => CHtml::dropDownlist('Report[sales_type]', $model->sales_type, array(''=>'All')+Yii::app()->params['sales_type'], array('class'=>'form-control')),
        ),
        
        array('name'=>'market',
            'value'=>function($data){
                return $data->market;
            },
            'filter' => CHtml::dropDownlist('Report[market]', $model->market, array(''=>'All')+Yii::app()->params['market'], array('class'=>'form-control')),
            'headerHtmlOptions' => array('width' => 80,'style' => 'text-align:center;'),
        ),
        array(
            'name' => 'report',
            'headerHtmlOptions' => array('width' => 350),
            'value'=> function($data){
                return str_replace("\n", '<br>', $data->report);
            },
            'type' => 'raw'
        ),
        array('name'=>'follow_up',
            'value'=>function($data){
                return $data->follow_up;
            },
            'headerHtmlOptions' => array('width' => 80,'style' => 'text-align:center;'),
        ),
        array('name'=>'done',
            'value'=>function($data){
                return $data->done;
            },
            'filter' => CHtml::dropDownlist('Report[done]', $model->done, array(''=>'All', 'No'=>'No','Yes'=>'Yes'), array('class'=>'form-control')),
            'headerHtmlOptions' => array('width' => 60,'style' => 'text-align:center;'),
        ),
        array('name'=>'new_account',
            'value'=>function($data){
                return $data->new_account;
            },
            'filter' => CHtml::dropDownlist('Report[new_account]', $model->new_account, array(''=>'All', 'No'=>'No','Yes'=>'Yes'), array('class'=>'form-control')),
            'headerHtmlOptions' => array('width' => 60,'style' => 'text-align:center;'),
        ),
        'comments',
        array(
            'header' => '#',
            'headerHtmlOptions'=>array('width' => 40,'style' => 'text-align:center;vertical-align:middle;width:40px;'),
            'htmlOptions' => array('width' => 40,'style' => 'text-align:center;vertical-align:middle;width:40px;'),
            'class' => 'booster.widgets.TbJsonButtonColumn',
            'template' => '{view} {update} {delete}',
            'buttons' => array(
                'update' => array(
                    'label'=>'Update',
                    'url'=> function($data){
                        $now = date('Y-m-d');
                        $date = date('Y-m-d', strtotime($data->added_date));
                        $new = date('Y-m-d', strtotime("$date +7days"));
                        if(strtotime($new) >= strtotime($now)){
                            return Yii::app()->createUrl("report/update", array("id"=>$data->id));
                        }else{
                            return '';
                        }
                    }
                )
            )
        ),
    )
));
?>