<?php
$this->breadcrumbs = array(
    'Contact'
);
$this->menu=array(
    array('label'=>'Add new contact', 'url'=>array('create')),
    //array('label'=>'Report Contact', 'url'=>array('report')),
    array('label'=>'Export Results to Excel File', 'url'=>Yii::app()->createUrl('contact/report'), 'linkOptions'=>array('style'=>'color:red;font-weight:bold;')),
    array('label'=>'Import Contact From CSV File', 'url'=>array('import')),
);
?>

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">Contact DB</h1>
    </div>
    <!-- /.col-lg-12 -->
</div>
<!-- <div class="input-group custom-search-form">
    <input type="text" class="form-control" placeholder="Search...">
    <span class="input-group-btn">
        <button class="btn btn-default" type="button">
            <i class="fa fa-search"></i>
        </button>
    </span>
</div> -->
<form method="post" class="form-inline" id="form_filter">
<?php echo CHtml::hiddenField(Yii::app()->request->csrfTokenName,Yii::app()->request->csrfToken); ?>
	<input type="hidden" name="do_export" value="" id="do_export"/>
</form>
<div class="row">
	<div class="col-lg-12">
		<div class="panel panel-default">
            <div class="panel-heading">
                Contact DB
            </div>
            <!-- /.panel-heading -->
            <div class="panel-body">
                <div class="table-responsive">
					<?php 
                        if(ActionAccess::isAll()){
                            $this->renderPartial('_grid_admin', compact(array('model')));
                        }else{
                            $this->renderPartial('_grid', compact(array('model')));
                        }
                    ?>
				</div>
			</div>
		</div>
	</div>
</div>
<style type="text/css">
	select{
		width:auto;
	}
</style>
<link rel="stylesheet" href="<?php echo Yii::app()->request->baseUrl; ?>/chosen/chosen.css">
<script src="<?php echo Yii::app()->request->baseUrl; ?>/chosen/chosen.jquery.js" type="text/javascript"></script>
<script type="text/javascript">
    var config = {
    '.chosen-select'           : {width:"150px"},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
    var config = {
    '.chosen-select-multiple'           : {width:"291.5px"},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
    var config = {
    '.chosen-select-addnew'           : {width:"250px", is_enternew:true},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
    
</script>
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