<?php
if (!is_array($row)) { $row=array(); }
$fields=isset($row['fields']) && is_array($row['fields'])?$row['fields']:array();
$v=array_merge(array('title'=>'','description'=>'','kind'=>'task','action_type'=>'other','priority'=>'normal','due_mode'=>'date','due_date'=>$date,'due_at_local'=>'','starts_at_local'=>'','ends_at_local'=>'','timezone'=>'Asia/Ho_Chi_Minh','location'=>'','contact_person_id'=>'inherit'),$fields);
$prefix='tasks['.$index.']';
?>
<fieldset class="panel panel-default"><legend>Việc mới <?php echo $index+1; ?></legend><div class="panel-body">
<?php $rowErrors=array(); foreach ($errors as $field=>$messages) { if ($field==='tasks.'.$index || strpos($field,'tasks.'.$index.'.')===0) { $rowErrors[$field]=$messages; } } $this->renderPartial('/account/_errors',array('errors'=>$rowErrors)); ?>
<div class="form-group"><label>Tiêu đề *</label><?php echo CHtml::textField($prefix.'[fields][title]',$v['title'],array('class'=>'form-control','maxlength'=>255,'required'=>true)); ?></div>
<div class="form-group"><label>Nội dung</label><?php echo CHtml::textArea($prefix.'[fields][description]',$v['description'],array('class'=>'form-control','rows'=>2)); ?></div>
<div class="row">
<?php foreach (array('kind'=>array('Loại việc',array('task'=>'Công việc','appointment'=>'Cuộc hẹn')),'due_mode'=>array('Kiểu hạn',array('date'=>'Hạn ngày','datetime'=>'Có giờ')),'priority'=>array('Ưu tiên',array('normal'=>'Bình thường','high'=>'Cao')),'action_type'=>array('Hoạt động',array('call'=>'Gọi điện','visit'=>'Gặp khách','entertainment'=>'Tiếp khách','site_inspection'=>'Tham quan','send_quote'=>'Gửi báo giá','other'=>'Khác'))) as $name=>$definition): ?><div class="form-group col-md-3"><label><?php echo $definition[0]; ?></label><?php echo CHtml::dropDownList($prefix.'[fields]['.$name.']',$v[$name],$definition[1],array('class'=>'form-control')); ?></div><?php endforeach; ?>
<div class="form-group col-md-6"><label>Liên hệ của việc</label><?php echo CHtml::dropDownList($prefix.'[fields][contact_person_id]',$v['contact_person_id'],array('inherit'=>'Theo người liên hệ của báo cáo',''=>'Không chọn')+CHtml::listData($contacts,'id',function($p){return trim($p->first_name.' '.$p->last_name);}),array('class'=>'form-control')); ?></div>
<div class="form-group col-md-6"><label>Timezone</label><?php echo CHtml::textField($prefix.'[fields][timezone]',$v['timezone'],array('class'=>'form-control','maxlength'=>64,'required'=>true)); ?></div>
<?php foreach (array('due_date'=>'Ngày đến hạn','due_at_local'=>'Giờ đến hạn','starts_at_local'=>'Bắt đầu cuộc hẹn','ends_at_local'=>'Kết thúc cuộc hẹn') as $name=>$label): ?><div class="form-group col-md-6"><label><?php echo $label; ?></label><?php echo CHtml::tag('input',array('type'=>$name==='due_date'?'date':'datetime-local','name'=>$prefix.'[fields]['.$name.']','value'=>$v[$name],'class'=>'form-control','step'=>1)); ?></div><?php endforeach; ?>
<div class="form-group col-md-12"><label>Địa điểm</label><?php echo CHtml::textField($prefix.'[fields][location]',$v['location'],array('class'=>'form-control','maxlength'=>255)); ?></div></div>
<p>Chỉ nhập mốc tương ứng: hạn ngày không có giờ; cuộc hẹn cần bắt đầu/kết thúc và giờ đến hạn bằng kết thúc. Xóa mốc không dùng.</p>
<?php echo CHtml::checkBox($prefix.'[allow_overdue]',isset($row['allow_overdue']) && in_array($row['allow_overdue'],array(true,1,'1'),true),array('value'=>'1','uncheckValue'=>null)); ?> Xác nhận hạn đã qua
<div class="form-group"><label>Lý do khi tạo quá hạn</label><?php echo CHtml::textArea($prefix.'[reason]',isset($row['reason']) && is_string($row['reason'])?$row['reason']:'',array('class'=>'form-control','maxlength'=>4000)); ?></div>
<?php echo CHtml::htmlButton('Bỏ việc mới này',array('type'=>'submit','name'=>'remove_task','value'=>(string)$index,'class'=>'btn btn-warning','formnovalidate'=>true)); ?>
</div></fieldset>
