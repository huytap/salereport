<?php
$this->breadcrumbs = array(
    'Users'
);
$this->menu=array(
    array('label'=>'Add new user', 'url'=>array('create')),
);
?>
<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">Users</h1>
    </div>
</div>
<div class="row">
	<div class="col-lg-12">
		<div class="panel panel-default">
            <div class="panel-heading">
                Users
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