<?php $this->pageTitle='Chuyển đổi hồ sơ'; ?>
<h2>Import / export hồ sơ thống nhất</h2>
<p>Chỉ thao tác DB test. Mỗi batch cần mapping từng dòng, duyệt rồi mới ghi. CSV không cập nhật ngầm hồ sơ có sẵn; dùng màn hình hồ sơ để chỉnh sửa sau đối chiếu.</p>
<?php if (ActionAccess::can('customermove','cutover')) { echo CHtml::link('Kiểm tra / chốt cutover',array('cutover'),array('class'=>'btn')); } ?>
<h3>CSV mới</h3>
<?php echo CHtml::beginForm(array('preview'),'post',array('enctype'=>'multipart/form-data')); ?>
<input type="hidden" name="kind" value="csv">
<label>Hotel ID đã được cấp quyền quản lý <input name="hotel_id" required pattern="[0-9]+"></label>
<input type="file" name="csv" accept=".csv,text/csv" required>
<button class="btn btn-primary">Tạo preview</button>
<?php echo CHtml::endForm(); ?>
<p>Xuất CSV theo Hotel và owner:</p>
<?php echo CHtml::beginForm(array('export'),'get'); ?>
<label>Hotel ID <input name="hotel_id" required></label><label>Owner ID (để trống: mọi owner trong quyền) <input name="owner_user_id"></label><button class="btn">Xuất CSV</button>
<?php echo CHtml::endForm(); ?>
<?php if (ActionAccess::isAll()): ?>
<h3>Lịch nguồn cũ</h3>
<?php echo CHtml::beginForm(array('preview'),'post'); ?>
<input type="hidden" name="kind" value="schedule">
<label>Report ID (tối đa 150, ngăn bởi dấu phẩy) <textarea name="ids" required></textarea></label>
<button class="btn">Preview ba trường ent / sales / site_ins</button>
<?php echo CHtml::endForm(); endif; ?>
<h3>Batch gần đây</h3>
<table class="table"><tr><th>Mã</th><th>Loại</th><th>Hotel</th><th>Trạng thái</th></tr>
<?php foreach ($batches as $b): ?><tr><td><?php echo CHtml::link((int)$b['id'],array('view','id'=>$b['id'])); ?></td><td><?php echo CHtml::encode($b['kind']); ?></td><td><?php echo (int)$b['hotel_id']; ?></td><td><?php echo CHtml::encode($b['status']); ?></td></tr><?php endforeach; ?></table>
