<?php
$this->breadcrumbs = array(
    'Staff Assegment' => array('admin')
);
?>
<form method="post" class="form-inline" id="form_filter">
	<input type="hidden" name="do_export" value="" id="do_export"/>
</form>
<div class="row">
	<div class="col-lg-12">
		<div class="panel panel-default">
            <div class="panel-heading">
                <h3>Staff Assegment</h3>
                <a href="<?=Yii::app()->createUrl('staffasst/create');?>">Create new</a>
            </div>
            <!-- /.panel-heading -->
            <div class="panel-body">
                <div class="table-responsive">
					<?php $this->renderPartial('_grid', compact(array('model')));?>
				</div>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
function doExport() {
	$('#do_export').val('yes');
	$('#form_filter').submit();
	$('#do_export').val('no');
}	
function prepareReport() {
	$('#do_export').val('');
	return true;
}
</script>