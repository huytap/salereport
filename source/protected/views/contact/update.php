<?php
$this->breadcrumbs = array(
    'Contact'=>Yii::app()->createAbsoluteUrl("contact/admin"),
    'Update'
);
?>
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Update contact</h1>
        </div>
    </div>
<?php 
echo $this->renderPartial('_form', compact('model')); ?>