<h2>Kết quả đối soát</h2><pre><?php echo CHtml::encode(CJSON::encode($result)); ?></pre>
<?php echo CHtml::link('Về batch',array('view','id'=>$result['batch_id'])); ?>
