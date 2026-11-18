<?php
$this->breadcrumbs = array(
    '404'
);
?>
<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">404</h1>
    </div>
</div>
<div class="row">
	<div class="col-lg-12">
		<div class="panel panel-default">
            <div class="panel-body">
				<h1 class="grey lighter smaller">
					<h3 class="lighter smaller">
						You are not authorized to perform this action<br><br>
						OR 
						<br><br>We looked everywhere but we couldn't find it!
					</h3>
				<hr />
				<div class="space"></div>

				<div class="row-fluid">
					<div class="center">
						<a href="javascript:window.history.go(-1);" class="btn btn-grey">
							<i class="icon-arrow-left"></i>
							Go Back
						</a>

						<a href="<?=Yii::app()->baseUrl?>/" class="btn btn-primary">
							<i class="icon-dashboard"></i>
							Dashboard
						</a>
					</div>
				</div>
				</h1>
			</div>
		</div>
	</div>
</div>