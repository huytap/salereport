<?php $new=$model->isNewRecord; $this->breadcrumbs=array('Khách hàng'=>array('admin'),$new?'Thêm khách hàng':$model->name); ?>
<h1><?php echo $new?'Thêm hồ sơ khách hàng':'Cập nhật hồ sơ'; ?></h1>
<?php $this->renderPartial('_errors',array('errors'=>$errors)); ?>
<?php if ($new): ?>
<?php echo CHtml::beginForm(array('create'),'get'); ?><label>Khách sạn tiếp nhận</label>
<?php echo CHtml::dropDownList('hotel_id',$hotelId,CHtml::listData($hotels,'id','name'),array('class'=>'form-control')); echo CHtml::submitButton('Chọn khách sạn',array('class'=>'btn btn-default')); echo CHtml::endForm(); ?>
<?php if (!$hotelId): ?><p>Chưa có quyền tại khách sạn nào. Cần được cấp quyền trước khi tạo khách hàng.</p><?php return; endif; ?>
<?php endif; ?>
<?php echo CHtml::beginForm($new?array('create'):array('update','id'=>$model->id),'post'); echo CHtml::hiddenField('request_key',$requestKey); ?>
<?php if ($new): echo CHtml::hiddenField('hotel_id',$hotelId); ?><div class="form-group"><label>Người phụ trách tại khách sạn đã chọn</label>
<?php $grant=UserHotelAccess::model()->findByPk(array('user_id'=>Yii::app()->user->id,'hotel_id'=>$hotelId)); $own=!ActionAccess::isAll() && $grant && $grant->scope_level==='own'; echo CHtml::dropDownList('owner_user_id',$own?Yii::app()->user->id:$owner,CHtml::listData($owners,'id','fullname'),$own?array('class'=>'form-control'):array('class'=>'form-control','empty'=>'Chưa phân công')); ?></div>
<?php else: echo CHtml::hiddenField('expected_version',$version); endif; ?>
<div class="row">
<?php $labels=array('name'=>'Tên doanh nghiệp *','segment'=>'Phân khúc','biz_scope'=>'Lĩnh vực kinh doanh','website'=>'Website','phone'=>'Điện thoại','email'=>'Email chung','building'=>'Tòa nhà','address_1'=>'Địa chỉ 1','address_2'=>'Địa chỉ 2','address_3'=>'Địa chỉ 3','city'=>'Thành phố','country'=>'Quốc gia','source'=>'Nguồn khách','fit_series'=>'FIT / Series','dmc'=>'DMC','notes'=>'Ghi chú'); ?>
<?php foreach ($labels as $field=>$label): ?><div class="form-group col-md-6"><label><?php echo CHtml::encode($label); ?></label>
<?php if ($field==='segment') { echo CHtml::dropDownList('Account[segment]',$model->$field,array(''=>'Chưa xác định','LEISURE'=>'LEISURE','CORPORATE'=>'CORPORATE','OTA'=>'OTA'),array('class'=>'form-control')); }
elseif (in_array($field,array('notes','biz_scope'),true)) { echo CHtml::textArea('Account['.$field.']',$model->$field,array('class'=>'form-control','rows'=>3)); }
else { echo CHtml::textField('Account['.$field.']',$model->$field,array('class'=>'form-control','maxlength'=>255)); } ?>
</div><?php endforeach; ?>
<div class="form-group col-md-6"><label>Thị trường (phân cách bằng dấu phẩy)</label><?php echo CHtml::textField('markets',$markets,array('class'=>'form-control','maxlength'=>1300)); ?></div>
</div>
<?php if ($new): ?><h2>Người liên hệ đầu tiên (tùy chọn)</h2><?php $this->renderPartial('_personFields',array('person'=>$person)); endif; ?>
<?php if (ActionAccess::isAll()): ?><div class="form-group"><?php echo CHtml::checkBox('allow_duplicate',Yii::app()->request->getPost('allow_duplicate')==='1',array('value'=>'1','uncheckValue'=>null)); ?> Xác nhận đây là doanh nghiệp riêng dù trùng tên</div>
<div class="form-group"><label>Lý do xác nhận trùng tên</label><?php echo CHtml::textArea('reason',Yii::app()->request->getPost('reason',''),array('class'=>'form-control','maxlength'=>4000)); ?></div><?php endif; ?>
<?php echo CHtml::submitButton('Lưu hồ sơ',array('class'=>'btn btn-primary')); echo CHtml::endForm(); ?>
<p><?php echo CHtml::link('Quay lại',$new?array('admin'):array('view','id'=>$model->id)); ?></p>
