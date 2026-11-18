<?php
$this->breadcrumbs = array(
    'Users History'
);
?>
<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">Users History</h1>
    </div>
</div>
<div class="row">
	<div class="col-lg-12">
		<div class="panel panel-default">
            <div class="panel-heading">
                Users History
            </div>
            <!-- /.panel-heading -->
            <div class="panel-body">
                <div class="table-responsive">
					<?php $this->renderPartial('_grid2', compact(array('model')));?>
				</div>
			</div>
		</div>
	</div>
</div>