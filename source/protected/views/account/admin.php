<?php $this->breadcrumbs=array('Khách hàng'); ?>
<h1>Hồ sơ khách hàng</h1>
<?php if (ActionAccess::can('account','create') && !empty(Yii::app()->params['mvp']['customerWrite'])) { echo CHtml::link('Thêm khách hàng',array('create'),array('class'=>'btn btn-primary')); } ?>
<?php echo CHtml::beginForm(array('admin'),'get'); ?>
<div class="row">
<div class="form-group col-md-4"><label>Tìm công ty / mã / người liên hệ / email / điện thoại</label><?php echo CHtml::textField('q',$filters['q'],array('class'=>'form-control','maxlength'=>255)); ?></div>
<div class="form-group col-md-3"><label>Khách sạn</label><?php echo CHtml::dropDownList('hotel_id',$filters['hotel_id'],CHtml::listData($hotels,'id','name'),array('class'=>'form-control','empty'=>'Tất cả trong phạm vi')); ?></div>
<div class="form-group col-md-2"><label>Người phụ trách</label><?php echo CHtml::dropDownList('owner_user_id',$filters['owner_user_id'],CHtml::listData($owners,'id','fullname'),array('class'=>'form-control','empty'=>'Tất cả trong phạm vi')); ?></div>
<div class="form-group col-md-3"><label>Trạng thái</label><?php echo CHtml::dropDownList('status',$filters['status'],array(''=>'Tất cả','active'=>'Đang hoạt động','archived'=>'Đã lưu trữ'),array('class'=>'form-control')); ?></div>
<div class="form-group col-md-3"><label>Phân khúc</label><?php echo CHtml::dropDownList('segment',$filters['segment'],array(''=>'Tất cả','LEISURE'=>'LEISURE','CORPORATE'=>'CORPORATE','OTA'=>'OTA'),array('class'=>'form-control')); ?></div>
</div><?php echo CHtml::submitButton('Lọc',array('class'=>'btn btn-default')); echo CHtml::endForm(); ?>
<table class="table table-striped"><thead><tr><th>Mã hồ sơ</th><th>Doanh nghiệp</th><th>Phân khúc</th><th>Liên hệ chung</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach ($accounts as $a): ?><tr><td><?php echo CHtml::encode($a->code); ?></td><td><?php echo CHtml::link(CHtml::encode($a->name),array('view','id'=>$a->id)); ?></td><td><?php echo CHtml::encode($a->segment); ?></td><td><?php echo CHtml::encode($a->email.' · '.$a->phone); ?></td><td><?php echo $a->status==='active'?'Đang hoạt động':'Đã lưu trữ'; ?></td></tr><?php endforeach; ?>
<?php if (!$accounts): ?><tr><td colspan="5">Chưa có hồ sơ phù hợp trong phạm vi truy cập.</td></tr><?php endif; ?>
</tbody></table>
<?php $this->renderPartial('_pager',array('page'=>$page,'total'=>$total,'route'=>array_merge(array('admin'),$filters))); ?>
