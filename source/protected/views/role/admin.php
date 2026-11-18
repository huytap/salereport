<?php
$this->breadcrumbs = array(
    'User Privilges',
);?>
<div class="row">
	<div class="col-lg-12">
		<div class="panel panel-default">
            <div class="panel-heading">
                User Privilges
               	<a class="pull-right" href="<?php echo Yii::app()->createUrl('role/create');?>">Create new</a>
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