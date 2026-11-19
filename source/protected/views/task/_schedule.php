<div class="row">
<div class="form-group col-md-4"><label>Loại công việc</label><?php echo CHtml::dropDownList('Task[kind]',$values['kind'],array('task'=>'Công việc','appointment'=>'Cuộc hẹn'),array('class'=>'form-control')); ?></div>
<div class="form-group col-md-4"><label>Kiểu hạn</label><?php echo CHtml::dropDownList('Task[due_mode]',$values['due_mode'],array('date'=>'Hạn ngày','datetime'=>'Hạn có giờ'),array('class'=>'form-control')); ?></div>
<div class="form-group col-md-4"><label>Timezone</label><?php echo CHtml::textField('Task[timezone]',$values['timezone'],array('class'=>'form-control','required'=>true,'maxlength'=>64)); ?></div>
<?php foreach (array('due_date'=>'Ngày đến hạn','due_at_local'=>'Giờ đến hạn','starts_at_local'=>'Bắt đầu cuộc hẹn','ends_at_local'=>'Kết thúc cuộc hẹn') as $name=>$label): ?><div class="form-group col-md-6"><label><?php echo $label; ?></label><?php echo CHtml::tag('input',array('type'=>$name==='due_date'?'date':'datetime-local','name'=>'Task['.$name.']','id'=>'Task_'.$name,'value'=>isset($values[$name])?$values[$name]:'','class'=>'form-control','step'=>1,'data-task-time'=>$name)); ?></div><?php endforeach; ?>
</div>
<p>Hạn ngày: chỉ nhập ngày. Hạn có giờ: chỉ nhập giờ đến hạn. Cuộc hẹn: chọn hạn có giờ, nhập bắt đầu/kết thúc và giờ đến hạn bằng kết thúc. Xóa các mốc không dùng.</p>
<div class="form-group"><?php echo CHtml::checkBox('allow_overdue',Yii::app()->request->getPost('allow_overdue')==='1',array('value'=>'1','uncheckValue'=>null)); ?> Tôi xác nhận hạn đã qua và ghi rõ lý do bên dưới.</div>
<div class="form-group"><label>Lý do <?php echo $mode==='create'?'(bắt buộc nếu tạo quá hạn)':'*'; ?></label><?php echo CHtml::textArea('reason',is_string(Yii::app()->request->getPost('reason',''))?Yii::app()->request->getPost('reason',''):'',array('class'=>'form-control','maxlength'=>4000)); ?></div>
<?php Yii::app()->clientScript->registerScript('task-schedule-inputs', <<<'JS'
(function () {
    var kind = document.getElementById('Task_kind');
    var mode = document.getElementById('Task_due_mode');
    var date = document.getElementById('Task_due_date');
    var due = document.getElementById('Task_due_at_local');
    var start = document.getElementById('Task_starts_at_local');
    var end = document.getElementById('Task_ends_at_local');
    if (!kind || !mode) return;
    function adjust() {
        if (kind.value === 'appointment') mode.value = 'datetime';
        if (mode.value === 'date') due.value = '';
        else date.value = '';
        if (kind.value !== 'appointment') { start.value = ''; end.value = ''; }
        else due.value = end.value;
    }
    kind.addEventListener('change', adjust);
    mode.addEventListener('change', adjust);
    end.addEventListener('change', function () { if (kind.value === 'appointment') due.value = end.value; });
})();
JS
); ?>
