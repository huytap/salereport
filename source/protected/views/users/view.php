<?php
$this->breadcrumbs = array('Users' => array('admin'), $model->username);
?>
<h1 class="page-header">User <?php echo CHtml::encode($model->username); ?></h1>
<?php $this->widget('zii.widgets.CDetailView', array(
    'data' => $model,
    'attributes' => array('username', 'fullname', 'email', 'cellphone', 'status', 'roles',
        'hotel', 'group', 'remarks', 'added_date', 'updated_date', 'last_login_date'),
)); ?>
