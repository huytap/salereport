<p>Trang <?php echo (int)$page; ?> · <?php echo (int)$total; ?> bản ghi.
<?php if ($page>1) { echo CHtml::link('Trang trước',array_merge($route,array('page'=>$page-1)),array('class'=>'btn btn-default')); } ?>
<?php if ($page*25<$total) { echo CHtml::link('Trang sau',array_merge($route,array('page'=>$page+1)),array('class'=>'btn btn-default')); } ?></p>
