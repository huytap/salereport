<?php $this->breadcrumbs=array('Công việc'=>array('admin'),'Lịch tuần'); $tz=TaskSchedule::timezone($timezone); $start=new DateTimeImmutable($from,$tz); $end=new DateTimeImmutable($to,$tz); ?>
<h1>Lịch tuần</h1><p><?php echo CHtml::encode($from.' → '.$end->modify('-1 day')->format('Y-m-d').' · '.$timezone); ?></p>
<p><?php echo CHtml::link('Tuần trước',array_merge(array('calendar','from'=>$start->modify('-7 days')->format('Y-m-d'),'timezone'=>$timezone),$filters),array('class'=>'btn btn-default')); ?> <?php echo CHtml::link('Tuần sau',array_merge(array('calendar','from'=>$start->modify('+7 days')->format('Y-m-d'),'timezone'=>$timezone),$filters),array('class'=>'btn btn-default')); ?>
<?php if (ActionAccess::can('task','create') && !empty(Yii::app()->params['mvp']['taskWrite'])) { echo CHtml::link('Thêm cuộc hẹn / công việc',array('create'),array('class'=>'btn btn-primary')); } ?></p>
<?php echo CHtml::beginForm(array('calendar'),'get'); ?><div class="row"><div class="form-group col-md-4"><label>Từ ngày</label><?php echo CHtml::dateField('from',$from,array('class'=>'form-control')); ?></div><div class="form-group col-md-4"><label>Timezone hiển thị</label><?php echo CHtml::textField('timezone',$timezone,array('class'=>'form-control','maxlength'=>64)); ?></div></div>
<?php if ($filters['account_id']!=='') { echo CHtml::hiddenField('account_id',$filters['account_id']); } $this->renderPartial('_filters',compact('filters','hotels','owners')); echo CHtml::submitButton('Xem lịch',array('class'=>'btn btn-default')); echo CHtml::endForm(); ?>
<h2>Cuộc hẹn theo ngày và giờ</h2>
<?php for ($day=$start;$day<$end;$day=$day->modify('+1 day')): $next=$day->modify('+1 day'); $dayStart=$day->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); $dayEnd=$next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); $found=false; ?>
<section class="panel panel-default"><div class="panel-heading"><?php echo CHtml::encode($day->format('D d/m/Y')); ?></div><div class="panel-body">
<?php foreach ($events as $e): if ($e['start_utc']>=$dayEnd || $e['end_utc']<=$dayStart) { continue; } $found=true; ?>
<p><strong><?php echo CHtml::encode(str_replace('T',' ',$e['start_local']).' → '.str_replace('T',' ',$e['end_local'])); ?></strong><br><?php echo CHtml::link(CHtml::encode($e['title']),$e['url']); ?> · <?php echo CHtml::encode($e['account_name']); ?> · <?php echo CHtml::encode(TaskReadService::labels()[$e['status']]); ?></p>
<?php endforeach; if (!$found): ?><p>Không có cuộc hẹn.</p><?php endif; ?></div></section>
<?php endfor; ?>
<h2>Công việc hạn ngày</h2><p>Giữ nguyên ngày và timezone của công việc; các mục này không tạo cuộc hẹn có giờ.</p>
<table class="table table-striped"><thead><tr><th>Ngày đến hạn</th><th>Công việc</th><th>Khách hàng</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach ($date_tasks as $d): ?><tr><td><?php echo CHtml::encode($d['due_date'].' ('.$d['timezone'].')'); ?></td><td><?php echo CHtml::link(CHtml::encode($d['title']),$d['url']); ?></td><td><?php echo CHtml::encode($d['account_name']); ?></td><td><?php echo CHtml::encode(TaskReadService::labels()[$d['status']]); ?></td></tr><?php endforeach; ?>
<?php if (!$date_tasks): ?><tr><td colspan="4">Không có công việc hạn ngày trong khoảng đã chọn.</td></tr><?php endif; ?></tbody></table>
