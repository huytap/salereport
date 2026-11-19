<?php $labels=array('title'=>'Danh xưng','first_name'=>'Tên','last_name'=>'Họ','position'=>'Chức vụ','email'=>'Email','phone'=>'Điện thoại','mobile'=>'Di động','dob'=>'Ngày sinh (YYYY-MM-DD)','contact_role'=>'Vai trò liên hệ','notes'=>'Ghi chú'); ?>
<div class="row"><?php foreach ($labels as $field=>$label): ?><div class="form-group col-md-6"><label><?php echo CHtml::encode($label); ?></label>
<?php echo $field==='notes'?CHtml::textArea('Person['.$field.']',$person->$field,array('class'=>'form-control','rows'=>3)):CHtml::textField('Person['.$field.']',$person->$field,array('class'=>'form-control','maxlength'=>$field==='dob'?10:255)); ?>
</div><?php endforeach; ?></div>
