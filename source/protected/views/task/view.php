<?php $this->breadcrumbs=array('Công việc'=>array('admin'),$task->title); $open=in_array($task->status,array('open','in_progress'),true); $write=!empty(Yii::app()->params['mvp']['taskWrite']); ?>
<h1><?php echo CHtml::encode($task->title); ?></h1>
<p><?php echo CHtml::encode($task->code.' · '.TaskReadService::labels()[$task->status]); ?> · Phiên bản <?php echo (int)$task->lock_version; ?></p>
<?php $this->renderPartial('/account/_errors',compact('errors')); ?>
<dl class="dl-horizontal"><dt>Khách hàng</dt><dd><?php echo ActionAccess::can('account','view')?CHtml::link(CHtml::encode($task->account->name),array('/account/view','id'=>$task->account_id)):CHtml::encode($task->account->name); ?></dd>
<dt>Khách sạn</dt><dd><?php echo CHtml::encode($task->hotel->name); ?></dd><dt>Sales</dt><dd><?php echo CHtml::encode($task->assignee?$task->assignee->fullname:'#'.$task->assignee_user_id); ?></dd>
<dt>Người liên hệ</dt><dd><?php echo $task->contactPerson?CHtml::encode(trim($task->contactPerson->first_name.' '.$task->contactPerson->last_name)):'Không chọn'; ?></dd>
<dt>Loại / Hoạt động</dt><dd><?php echo CHtml::encode(($task->kind==='appointment'?'Cuộc hẹn':'Công việc').' · '.$task->action_type); ?></dd><dt>Ưu tiên</dt><dd><?php echo $task->priority==='high'?'Cao':'Bình thường'; ?></dd>
<dt>Hạn</dt><dd><?php echo CHtml::encode($task->due_mode==='date'?$task->due_date:str_replace('T',' ',TaskSchedule::local($task->due_at_utc,$timezone))); ?> (<?php echo CHtml::encode($timezone); ?>)<?php echo $task->isOverdue()?' · Quá hạn':''; ?></dd>
<?php if ($task->kind==='appointment'): ?><dt>Bắt đầu / Kết thúc</dt><dd><?php echo CHtml::encode(str_replace('T',' ',TaskSchedule::local($task->starts_at_utc,$timezone)).' → '.str_replace('T',' ',TaskSchedule::local($task->ends_at_utc,$timezone))); ?></dd><?php endif; ?>
<dt>Địa điểm</dt><dd><?php echo CHtml::encode($task->location); ?></dd><dt>Nội dung</dt><dd><?php echo nl2br(CHtml::encode($task->description)); ?></dd>
<?php if ($task->report_id): ?><dt>Báo cáo nguồn</dt><dd><?php echo ActionAccess::can('report','view')?CHtml::link('#'.$task->report_id,array('/report/view','id'=>$task->report_id)):'#'.(int)$task->report_id; ?></dd><?php endif; ?>
<?php if (!$open): ?><dt>Kết quả / Lý do hủy</dt><dd><?php echo nl2br(CHtml::encode($task->status==='completed'?$task->completion_note:$task->cancel_reason)); ?></dd><dt>Người hoàn thành</dt><dd><?php echo $task->completed_by?'#'.(int)$task->completed_by:'—'; ?></dd><?php endif; ?></dl>
<?php if ($write): ?>
<p><?php foreach (array('update'=>'Sửa nội dung','reschedule'=>'Đổi hạn / lịch','reopen'=>'Mở lại') as $action=>$label) { if (ActionAccess::can('task',$action) && ($action==='reopen'?!$open:$open)) { echo CHtml::link($label,array($action,'id'=>$task->id),array('class'=>'btn btn-default')).' '; } } ?></p>
<?php foreach (array('start'=>'Bắt đầu thực hiện','complete'=>'Hoàn thành','cancel'=>'Hủy công việc') as $action=>$label): if (!$open || !ActionAccess::can('task',$action) || ($action==='start' && $task->status!=='open')) { continue; } ?>
<details><summary><?php echo $label; ?></summary><?php echo CHtml::beginForm(array($action,'id'=>$task->id),'post'); echo CHtml::hiddenField('request_key',bin2hex(random_bytes(16))); echo CHtml::hiddenField('expected_version',$task->lock_version); ?>
<?php if ($action!=='start'): ?><label><?php echo $action==='complete'?'Kết quả thực hiện *':'Lý do hủy *'; ?></label><?php $note=Yii::app()->request->getPost('note',''); echo CHtml::textArea('note',is_string($note)?$note:'',array('class'=>'form-control','maxlength'=>4000,'required'=>true)); endif; ?>
<?php echo CHtml::submitButton($label,array('class'=>$action==='cancel'?'btn btn-warning':'btn btn-primary')); echo CHtml::endForm(); ?></details>
<?php endforeach; endif; ?>
<h2>Lịch sử công việc</h2><p>Thông tin lịch sử được giữ lại khi mở lại hoặc bàn giao.</p>
<?php foreach ($audit as $event): ?><details><summary><?php echo CHtml::encode($event->occurred_at_utc.' UTC · '.$event->event_type.' · actor #'.$event->actor_user_id); ?></summary><p><?php echo CHtml::encode($event->reason); ?></p><strong>Trước</strong><pre><?php echo CHtml::encode($event->before_json); ?></pre><strong>Sau</strong><pre><?php echo CHtml::encode($event->after_json); ?></pre></details><?php endforeach; ?>

<?php $this->renderPartial('/account/_pager',array('page'=>$auditPage,'total'=>$auditTotal,'route'=>array('view','id'=>$task->id))); ?>
