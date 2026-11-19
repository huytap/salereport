<div class="row">
<div class="form-group col-md-3"><label>Khách sạn</label><?php echo CHtml::dropDownList('hotel_id',$filters['hotel_id'],CHtml::listData($hotels,'id','name'),array('class'=>'form-control','empty'=>'Tất cả trong phạm vi')); ?></div>
<div class="form-group col-md-3"><label>Sales</label><?php echo CHtml::dropDownList('assignee_user_id',$filters['assignee_user_id'],CHtml::listData($owners,'id','fullname'),array('class'=>'form-control','empty'=>'Tất cả trong phạm vi')); ?></div>
<div class="form-group col-md-2"><label>Trạng thái</label><?php echo CHtml::dropDownList('status',$filters['status'],array(''=>'Theo tab')+TaskReadService::labels(),array('class'=>'form-control')); ?></div>
<div class="form-group col-md-2"><label>Ưu tiên</label><?php echo CHtml::dropDownList('priority',$filters['priority'],array(''=>'Tất cả','normal'=>'Bình thường','high'=>'Cao'),array('class'=>'form-control')); ?></div>
<div class="form-group col-md-2"><label>Từ khóa</label><?php echo CHtml::textField('q',$filters['q'],array('class'=>'form-control','maxlength'=>255)); ?></div>
</div>
