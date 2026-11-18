<?php $total = 0;?>
<?php if(count($data->getData())>0){
    $total += count($data->getData());?>
    <li>
        <a href="<?php echo Yii::app()->createUrl('report/admin/type/ent');?>">
            <div>
                <?php echo count($data->getData())?> ENT
                <span class="pull-right text-muted small">See All</span>
            </div>
        </a>
    </li>
    <li class="divider"></li>
<?php }?>
<?php if(count($data2->getData())>0){
    $total += count($data2->getData());?>
    <li>
        <a href="<?php echo Yii::app()->createUrl('report/admin/type/sales');?>">
            <div>
                <?php echo count($data2->getData())?> Sales
                <span class="pull-right text-muted small">See All</span>
            </div>
        </a>
    </li>
    <li class="divider"></li>
<?php }?>
<?php if(count($data3->getData())>0){
    $total += count($data3->getData());?>
    
    <li>
        <a href="<?php echo Yii::app()->createUrl('report/admin/type/site_ins');?>">
            <div>
                <?php echo count($data3->getData())?> Site Ins
                <span class="pull-right text-muted small">See All</span>
            </div>
        </a>
    </li>
<?php }?>
<script type="text/javascript">
    $('#total-no').text(<?php echo $total?>);
</script>
<?php 
if($total == 0){
    echo '<li><a href="#">Have not any reminders</a></li>';
}