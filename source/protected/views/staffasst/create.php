<?php
$this->breadcrumbs = array(
    'Incident Report'=>Yii::app()->createAbsoluteUrl("incident/admin"),
    'Add new'
);
?>
<?php  echo $this->renderPartial('_form', compact('model')); ?>