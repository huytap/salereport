<?php $b=$data['batch']; $this->pageTitle='Duyệt batch '.(int)$b['id']; ?>
<h2>Batch <?php echo (int)$b['id']; ?> — <?php echo CHtml::encode($b['kind'].' / '.$b['status']); ?></h2>
<p>Mã hồ sơ/người liên hệ trong CSV chỉ là gợi ý. Link cần đúng ID và phiên bản hiện tại; không ghi đè thông tin có sẵn. Tạo mới bị chặn nếu có dấu hiệu trùng. Skip cần lý do. Lịch cũ chỉ được tạo khi quản lý xác nhận còn hiệu lực, đúng owner và phiên bản phân công.</p>
<?php foreach ($data['rows'] as $r): $p=json_decode($r['payload_json'],true); $d=$r['decision_json']?json_decode($r['decision_json'],true):array(); ?>
<div class="well"><h3>Dòng <?php echo (int)$r['row_no'].' — '.CHtml::encode($r['state'].' '.$r['error_code']); ?></h3>
<details><summary>Dữ liệu preview</summary><pre><?php echo CHtml::encode(CJSON::encode($p)); ?></pre></details>
<?php if (!in_array($r['state'],array('created','linked','skipped'),true)):
echo CHtml::beginForm(array('map','id'=>$b['id']),'post'); ?>
<input type="hidden" name="row_no" value="<?php echo (int)$r['row_no']; ?>"><input type="hidden" name="version" value="<?php echo (int)$r['lock_version']; ?>">
<label>Quyết định <?php echo CHtml::dropDownList('decision[mode]',isset($d['mode'])?$d['mode']:'',array(''=>'Chọn','create'=>'Tạo mới','link'=>'Link hiện có (CSV)','skip'=>'Bỏ qua có lý do')); ?></label>
<?php $fields=$b['kind']==='csv'?array('account_id'=>'Account ID để link','account_version'=>'Phiên bản Account','contact_id'=>'Contact ID để link','contact_version'=>'Phiên bản Contact','owner_user_id'=>'Owner khi tạo Account'):array('owner_user_id'=>'Owner hiện tại đã xác nhận','assignment_version'=>'Phiên bản phân công Account/Hotel');
foreach ($fields as $name=>$label): ?><label><?php echo CHtml::encode($label); ?> <input name="decision[<?php echo $name; ?>]" value="<?php echo CHtml::encode(isset($d[$name])?$d[$name]:''); ?>"></label><?php endforeach; ?>
<?php if ($b['kind']==='csv'): ?><label>Người liên hệ <?php echo CHtml::dropDownList('decision[contact_mode]',isset($d['contact_mode'])?$d['contact_mode']:'none',array('none'=>'Không thêm','create'=>'Tạo mới','link'=>'Link hiện có')); ?></label>
<?php else: ?><label><input type="checkbox" name="decision[confirmed]" value="1" <?php echo !empty($d['confirmed'])?'checked':''; ?>> Quản lý xác nhận lịch tương lai còn hiệu lực</label><?php endif; ?>
<label>Lý do đối chiếu <textarea name="decision[reason]" required><?php echo CHtml::encode(isset($d['reason'])?$d['reason']:''); ?></textarea></label>
<button class="btn">Lưu mapping (hủy phê duyệt cũ)</button><?php echo CHtml::endForm(); endif; ?>
<?php if ($r['result_json']): ?><pre><?php echo CHtml::encode($r['result_json']); ?></pre><?php endif; ?></div>
<?php endforeach; ?>
<?php echo CHtml::beginForm(array('approve','id'=>$b['id']),'post'); ?><input type="hidden" name="version" value="<?php echo (int)$b['lock_version']; ?>"><button class="btn">Phê duyệt toàn bộ mapping</button><?php echo CHtml::endForm(); ?>
<?php echo CHtml::beginForm(array('commit','id'=>$b['id']),'post'); ?><button class="btn btn-primary">Ghi tối đa 50 dòng đã duyệt</button><?php echo CHtml::endForm(); ?>
<?php echo CHtml::link('Đối soát',array('reconcile','id'=>$b['id'])); ?>
