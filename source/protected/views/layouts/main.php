<?php
$this->widget('CssWidget');?>

<body>
	<div id="wrapper">
		<?php $this->widget('HeaderWidget');?>

		<div id="page-wrapper">
			<div class="row">
				<div class="col-lg-12">
					<?php 
						if(isset($this->breadcrumbs)){
							$this->widget('zii.widgets.CBreadcrumbs', array('links'=>$this->breadcrumbs));
					}?>
				</div>
			</div>
	        <?php echo $content; ?>
		</div>
		<div id="loading">
			<img src="<?php echo Yii::app()->baseUrl; ?>/images/loading.gif">
		</div>
	</div>
	<?php $this->widget('FooterWidget');?>