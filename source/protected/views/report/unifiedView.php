<?php $this->breadcrumbs=array('Sales Report'=>array('admin'),'#'.$model->id); ?>
<h1>Sales Report #<?php echo (int)$model->id; ?></h1>
<p><?php echo CHtml::encode($model->date.' · '.$model->company_name.' · '.$model->hotels); ?></p>
<?php if (!$canEdit): ?><div class="alert alert-info">Báo cáo đã khóa sửa nội dung theo quy tắc 7 ngày hoặc thiếu ngày tạo hợp lệ. Các công việc vẫn được xử lý độc lập.</div><?php endif; ?>
<p><?php if ($canEdit && ActionAccess::can('report','update') && !empty(Yii::app()->params['mvp']['reportWrite'])) { echo CHtml::link('Sửa nội dung / thêm việc mới',array('update','id'=>$model->id),array('class'=>'btn btn-default')); } ?>
<?php if (ActionAccess::can('account','view')) { echo CHtml::link('Hồ sơ khách hàng',array('/account/view','id'=>$model->account_id),array('class'=>'btn btn-default')); } ?>
<?php if (ActionAccess::can('task','create') && !empty(Yii::app()->params['mvp']['taskWrite']) && $model->account && $model->account->status==='active') { echo CHtml::link('Thêm việc từ báo cáo này',array('/task/create','account_id'=>$model->account_id,'hotel_id'=>$model->hotel_id,'report_id'=>$model->id),array('class'=>'btn btn-primary')); } ?></p>
<h2>Thông tin tại thời điểm báo cáo</h2>
<p>Thông tin dưới đây giữ nguyên khi hồ sơ khách hàng hoặc người liên hệ thay đổi.</p>
<dl class="dl-horizontal"><?php foreach (array('company_name'=>'Doanh nghiệp','company_address'=>'Địa chỉ','phone'=>'Điện thoại','email'=>'Email','biz_scope'=>'Lĩnh vực','city'=>'Thành phố','country'=>'Quốc gia','account_type'=>'Phân khúc','market'=>'Thị trường','title'=>'Danh xưng','first_name'=>'Tên','last_name'=>'Họ','position'=>'Chức vụ','sales_type'=>'Loại tiếp xúc','objective'=>'Mục đích','expected_result'=>'Nhóm nhu cầu','report'=>'Nội dung','follow_up'=>'Tóm tắt việc tiếp theo','action'=>'Tóm tắt hành động','comments'=>'Ghi chú','new_account'=>'Khách mới') as $field=>$label): ?><dt><?php echo $label; ?></dt><dd><?php echo nl2br(CHtml::encode($model->$field)); ?></dd><?php endforeach; ?></dl>
<p>Người tạo lịch sử: #<?php echo (int)$model->added_by; ?> · <?php echo CHtml::encode($model->added_date); ?> · Phiên bản <?php echo (int)$model->lock_version; ?></p>
<h2>Công việc liên quan</h2>
<?php if (!ActionAccess::can('task','view') && !ActionAccess::can('task','admin')): ?><p>Chưa có quyền xem công việc.</p><?php else: ?>
<table class="table table-striped"><thead><tr><th>Công việc</th><th>Hạn</th><th>Trạng thái</th><th>Sales</th></tr></thead><tbody>
<?php foreach ($tasks as $task): ?><tr><td><?php echo ActionAccess::can('task','view')?CHtml::link(CHtml::encode($task->code.' · '.$task->title),array('/task/view','id'=>$task->id)):CHtml::encode($task->title); ?></td><td><?php echo CHtml::encode($task->due_mode==='date'?$task->due_date.' ('.$task->timezone.')':str_replace('T',' ',TaskSchedule::local($task->due_at_utc,$task->timezone)).' ('.$task->timezone.')'); ?></td><td><?php echo CHtml::encode(TaskReadService::labels()[$task->status]); ?></td><td>#<?php echo (int)$task->assignee_user_id; ?></td></tr><?php endforeach; ?>
<?php if (!$tasks): ?><tr><td colspan="4">Chưa có công việc liên quan trong phạm vi truy cập.</td></tr><?php endif; ?></tbody></table>
<?php $this->renderPartial('/account/_pager',array('page'=>$page,'total'=>$total,'route'=>array('view','id'=>$model->id,'audit_page'=>$auditPage))); endif; ?>

<h2>Lịch sử báo cáo</h2>
<?php foreach ($audit as $event): ?><details><summary><?php echo CHtml::encode($event->occurred_at_utc.' UTC · '.$event->event_type.' · actor #'.$event->actor_user_id); ?></summary><p><?php echo CHtml::encode($event->reason); ?></p><strong>Trước</strong><pre><?php echo CHtml::encode($event->before_json); ?></pre><strong>Sau</strong><pre><?php echo CHtml::encode($event->after_json); ?></pre></details><?php endforeach; ?>
<?php if (!$audit): ?><p>Chưa có thay đổi được ghi nhận từ luồng MVP. Dữ liệu và người tạo cũ vẫn giữ nguyên ở báo cáo.</p><?php endif; ?>
<p>Lịch sử: trang <?php echo (int)$auditPage; ?> · <?php echo (int)$auditTotal; ?> thay đổi.
<?php if ($auditPage>1) { echo CHtml::link('Trang lịch sử trước',array('view','id'=>$model->id,'page'=>$page,'audit_page'=>$auditPage-1)); } ?>
<?php if ($auditPage*25<$auditTotal) { echo CHtml::link('Trang lịch sử sau',array('view','id'=>$model->id,'page'=>$page,'audit_page'=>$auditPage+1)); } ?></p>
