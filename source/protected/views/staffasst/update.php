<?php
$this->breadcrumbs = array(
    'Incident Report'=>Yii::app()->createAbsoluteUrl("incident/admin"),
    'Update'
);
?>
<?php 
echo $this->renderPartial('_form', compact('model')); ?>