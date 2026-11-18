<?php
$this->breadcrumbs = array(
    'Users'=>Yii::app()->createAbsoluteUrl("users/admin"),
    'Add new'
);
?>
<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">Add new user</h1>
    </div>
</div>
<?php  echo $this->renderPartial('_form', compact('model')); ?>