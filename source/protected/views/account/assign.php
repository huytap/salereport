<?php $this->breadcrumbs=array('Khách hàng'=>array('admin'),$account->name=>array('view','id'=>$account->id),'Phân công / bàn giao'); ?>
<h1>Phân công / bàn giao khách hàng</h1><p><?php echo CHtml::encode($account->name); ?></p>
<?php echo CHtml::beginForm(array('assignpreview','id'=>$account->id),'get'); ?><label>Khách sạn</label>
<?php echo CHtml::dropDownList('hotel_id',$hotelId,CHtml::listData($hotels,'id','name'),array('class'=>'form-control')); echo CHtml::submitButton('Xem trước',array('class'=>'btn btn-default')); echo CHtml::endForm(); ?>
<?php if ($plan): ?>
<h2>Các việc mở sẽ chuyển: <?php echo count($plan['tasks']); ?></h2>
<p>Owner hiện tại: <?php echo $plan['owner_user_id']===null?'Chưa phân công':'#'.(int)$plan['owner_user_id']; ?>. Việc đã hoàn thành hoặc hủy giữ nguyên lịch sử.</p>
<table class="table table-striped"><thead><tr><th>Mã việc</th><th>Nội dung</th><th>Người phụ trách hiện tại</th></tr></thead><tbody>
<?php foreach ($plan['tasks'] as $t): ?><tr><td><?php echo (int)$t['id']; ?></td><td><?php echo CHtml::encode($t['title']); ?></td><td>#<?php echo (int)$t['assignee_user_id']; ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php echo CHtml::beginForm(array('assign','id'=>$account->id),'post'); foreach (array('hotel_id'=>$hotelId,'expected_version'=>$plan['expected_version'],'plan_hash'=>$plan['plan_hash'],'request_key'=>$requestKey) as $k=>$v) { echo CHtml::hiddenField($k,$v); } ?>
<div class="form-group"><label>Người nhận đang hoạt động tại khách sạn</label><?php echo CHtml::dropDownList('owner_user_id',$plan['owner_user_id'],CHtml::listData($owners,'id','fullname'),array('class'=>'form-control','empty'=>'Chưa phân công (chỉ khi không có việc mở)')); ?></div>
<div class="form-group"><label>Lý do bàn giao *</label><?php echo CHtml::textArea('reason','',array('class'=>'form-control','required'=>true,'maxlength'=>4000)); ?></div>
<?php echo CHtml::submitButton('Xác nhận phân công và chuyển toàn bộ việc mở',array('class'=>'btn btn-primary')); echo CHtml::endForm(); ?>
<?php else: ?><p>Chưa có khách sạn trong phạm vi quản lý.</p><?php endif; ?>
<p><?php echo CHtml::link('Quay lại hồ sơ',array('view','id'=>$account->id)); ?></p>
