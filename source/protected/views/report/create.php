<?php
$this->breadcrumbs = array(
    'Report'=>Yii::app()->createAbsoluteUrl("report/admin"),
    'Add new'
);
?>
	<div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Add new report</h1>
        </div>
    </div>
    <div id="report_fill">
		<?php  echo $this->renderPartial('_form', compact('model')); ?>
        
	</div>
