<?php $this->breadcrumbs=array('Users'=>array('admin'),$model->fullname=>array('view','id'=>$model->id),'Quyền khách sạn'); $current=array(); foreach ($grants as $g) { $current[$g['hotel_id']]=$g['scope_level']; } ?>
<h1>Quyền khách sạn</h1><p><?php echo CHtml::encode($model->fullname.' (#'.$model->id.')'); ?></p>
<p>Own: khách hàng do mình phụ trách. Hotel: toàn bộ khách hàng thuộc khách sạn. All được xác định bởi vai trò hệ thống; các action vẫn phụ thuộc vai trò.</p>
<p>Cần bàn giao toàn bộ hồ sơ và việc mở trước khi thu hồi khách sạn.</p>
<?php echo CHtml::beginForm(array('hotelaccess','id'=>$model->id,'format'=>'html'),'post'); echo CHtml::hiddenField('ui','1'); echo CHtml::hiddenField('expected_hash',hash('sha256',CJSON::encode($grants))); ?>
<table class="table table-striped"><thead><tr><th>Khách sạn</th><th>Phạm vi</th></tr></thead><tbody>
<?php foreach ($hotels as $h): ?><tr><td><?php echo CHtml::encode($h->name); ?></td><td><?php echo CHtml::dropDownList('scopes['.$h->id.']',isset($current[$h->id])?$current[$h->id]:'',array(''=>'Không cấp','own'=>'Own','hotel'=>'Hotel'),array('class'=>'form-control')); ?></td></tr><?php endforeach; ?></tbody></table>
<?php foreach ($grants as $g): if (!in_array($g['hotel_id'],CHtml::listData($hotels,'id','id'))): ?><p>Khách sạn #<?php echo (int)$g['hotel_id']; ?> hiện không hoạt động. Quyền hiện có được giữ nguyên; cần quản trị xử lý riêng.</p><?php echo CHtml::hiddenField('scopes['.$g['hotel_id'].']',$g['scope_level']); endif; endforeach; ?>
<div class="form-group"><label>Lý do thay đổi *</label><?php echo CHtml::textArea('reason','',array('class'=>'form-control','required'=>true,'maxlength'=>4000)); ?></div>
<?php echo CHtml::submitButton('Lưu quyền khách sạn',array('class'=>'btn btn-primary')); echo CHtml::endForm(); ?>
