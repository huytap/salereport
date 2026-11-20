<h2>Chốt chuyển đổi toàn bộ hồ sơ</h2>
<p>DB test. Freeze đóng đường ghi thường; chỉ batch đã duyệt của quản trị được backfill. Canonical cần đối soát đạt và bật đồng bộ ba cờ ghi trong process test. Closed giữ khả năng đọc, chặn ghi và dispatch. Sau phát sinh MVP, không mở lại ghi cũ.</p>
<pre><?php echo CHtml::encode(CJSON::encode($result)); ?></pre>
<?php echo CHtml::beginForm(array('cutover'),'post'); ?>
<input type="hidden" name="version" value="<?php echo (int)$result['state']['lock_version']; ?>">
<label>Trạng thái đích <?php echo CHtml::dropDownList('target','',array('frozen'=>'Frozen — dừng ghi','canonical'=>'Canonical — luồng thống nhất','closed'=>'Closed — đóng ghi khắc phục','preparing'=>'Preparing — chỉ trước phát sinh MVP')); ?></label>
<label>Lý do <textarea name="reason" required></textarea></label><button class="btn btn-primary">Chuyển trạng thái trên DB test</button>
<?php echo CHtml::endForm(); ?>
