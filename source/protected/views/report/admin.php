<?php
$this->breadcrumbs = array(
    'Sales Report'
);
$this->menu=array(
    array('label'=>'Add new contact', 'url'=>array('create')),
    //array('label'=>'Report Contact', 'url'=>array('report')),
    array('label'=>'Export Results to Excel File', 'url'=>Yii::app()->createUrl('contact/report'), 'linkOptions'=>array('style'=>'color:red;font-weight:bold;')),
    array('label'=>'Import Contact From Excel File', 'url'=>array('import')),
);
?>
<hr>

<div class="row">
    <form class="form-inline" method="get">
        <div class="col-md-3 col-sm-4">
            <label>From Date</label>
            <?php echo CHtml::textField('filter[from_date]', $filter['from_date'], array('class' => 'form-control datepicker'));?>
        </div>
        <div class="col-md-3 col-sm-4">
            <label>To Date</label>
            <?php echo CHtml::textField('filter[to_date]', $filter['to_date'], array('class' => 'form-control datepicker'));?>
        </div>
        <?php if(Yii::app()->user->id == 1 || Yii::app()->user->getState('type') !== 'users'){?>
            <div class="col-md-2 col-sm-4">
                <label>By SM</label>
                <?php echo CHtml::dropDownlist('filter[by]', $filter['by'], array('' => 'All') + Users::model()->getList(), array('class' => 'form-control'));?>
            </div>
        <?php }?>
        <div class="col-md-3 col-sm-4">
            <label>Contact Name</label>
            <?php echo CHtml::textField('filter[contact]', $filter['contact'], array('class' => 'form-control'));?>
        </div>
        <div class="col-md-1"><button class="btn btn-primary" type="submit">Search</button></div>
    </form>
</div>
<hr>
<form method="post" class="form-inline" id="form_filter">
	<input type="hidden" name="do_export" value="" id="do_export"/>
</form>
<div class="row">
	<div class="col-lg-12">
		<div class="panel panel-default">
            <div class="panel-heading">
                Sales Report
            </div>

            <!-- /.panel-heading -->
            <div class="panel-body">
                <div class="table-responsive">
					<?php 
                        if(Yii::app()->user->id == 1 || Yii::app()->user->getState('type') == 'superadmin' || Yii::app()->user->getState('type') == 'admin'){
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
<script type="text/javascript">
    /*$.ajax({
        url: '<?php echo Yii::app()->baseUrl?>/ajax/reminderDaily',
        type: 'post',
        data: {ent:'ent'},
        async: false,
        success:function(data){
            $('#reminder').html(data);
        }
    });*/
</script>
<script type="text/javascript">
    function row_color_update() {
        $('#dataTables-example').find('tbody').find('tr').each(function(i, row){
            var color = $(row).find('.bgcolor').text();
            if(color !== ''){
                $(row).css('background', color)
            }
        });
    } 
    function changeArea(id){
        var comment = $('#cmt'+id).val();
        if(comment.length){
            $.ajax({
                url: '<?php echo Yii::app()->baseUrl?>/ajax/changeCMT',
                data: {id:id, cmt: comment},
                dataType: 'json',
                type: 'post',
                beforeSend: function(){
                    $('#loading').show();
                },
                success: function(data){
                    if(data=='1'){
                        alert('Comment is updated')
                        $('#loading').hide();
                    }else{
                        alert(data)
                    }
                }
            })
        }
    }
    row_color_update();
</script>