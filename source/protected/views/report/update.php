<?php
$this->breadcrumbs = array(
    'Report'=>Yii::app()->createAbsoluteUrl("report/admin"),
    'Update'
);
?>
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Update report</h1>
        </div>
    </div>
<?php 
echo $this->renderPartial('_form', compact('model')); ?>