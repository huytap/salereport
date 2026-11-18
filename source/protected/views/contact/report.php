<?php
$this->breadcrumbs = array(
    'Contact'=>array('admin'),
    'Report'
);
$this->menu=array(
	array('label'=>'Export Results to Excel File', 'url'=>'', 'linkOptions'=>array('style'=>'color:red;font-weight:bold;')),
);
?>
<style>
th, td {
	padding: 3px!important;
}
</style>

<div class="row">
    <div class="col-lg-6">
        <h1 class="page-header">Contact Report</h1>
    </div>
</div>
<form id="form_filter" method="post">
<?php echo CHtml::hiddenField(Yii::app()->request->csrfTokenName,Yii::app()->request->csrfToken); ?>
	<input type="hidden" id="do_export" name="do_export">
</form>
<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                Contact DB
            </div>
            <!-- /.panel-heading -->
            <div class="panel-body">
                <div class="row"><div class="col-lg-12"><a href="javascript:doExport()" style="color:red;font-weight:bold;float:right;">Export Results to Excel File</a></div></div>
                <div class="table-responsive">
                    <?php 

                        $dataProvider = new CArrayDataProvider($datalist,array('keyField'=>'1', 'pagination'=>array('pageSize'=>100)));
                        if(count($datalist)>0)
                        $this->widget('booster.widgets.TbJsonGridView', array(
                            'type' => 'table table-striped table-bordered table-hover dataTable',
                            'id'=>'contracts-service-grid',
                            'dataProvider'=>$dataProvider,
                            'columns'=>array(
                                array('header' => 'Biz Scope',
                                    'value' => '$data[0]',
                                    'htmlOptions'=>array('style'=>'width:80px;')
                                ),
                                array('header' => 'Account Type',
                                    'value' => '$data[1]'
                                ),
                                array('header' => 'Managed By',
                                    'value' => '$data[2]'
                                ),
                                array('header' => 'City',
                                    'value' => '$data[3]'
                                ),
                                array('header' => 'Tel',
                                    'value' => '$data[4]'
                                ),
                                array('header' => 'First Name',
                                    'value' => '$data[5]'
                                ),
                                array('header' => 'Last Name',
                                    'value' => '$data[6]'
                                ),
                                array('header' => 'Address',
                                    'value' => '$data[7]'
                                )
                            )
                        )); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
function doExport() {
	$('#do_export').val('yes');
	$('#form_filter').submit();
}	
function prepareReport() {
	$('#do_export').val('');
	return true;
}
</script>