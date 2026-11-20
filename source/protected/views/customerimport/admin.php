<h1>Chuyển đổi hồ sơ khách hàng</h1>
<p>Chọn bản ghi Contact hoặc Sales Report cũ để tạo bản xem trước. Chỉ tạo hồ sơ chuẩn sau khi duyệt mapping và xác nhận nhập.</p>
<?php echo CHtml::beginForm(array('admin'),'get'); ?>
<label>Nguồn dữ liệu</label><?php echo CHtml::dropDownList('source_type',$sources['type'],array('contact'=>'Contact DB','report'=>'Sales Report'),array('class'=>'form-control')); ?>
<?php echo CHtml::submitButton('Xem danh sách nguồn',array('class'=>'btn btn-default')); echo CHtml::endForm(); ?>
<?php echo CHtml::beginForm(array('preview'),'post'); echo CHtml::hiddenField('ui','1'); echo CHtml::hiddenField('source_type',$sources['type']); ?>
<table class="table table-striped"><thead><tr><th>Chọn</th><th>Mã nguồn</th><th>Doanh nghiệp</th><th>Người liên hệ</th><th>Email</th></tr></thead><tbody>
<?php foreach ($sources['rows'] as $source): ?><tr><td><?php echo CHtml::checkBox('source_ids[]',false,array('id'=>'source_ids_'.(int)$source['id'],'aria-label'=>'Chọn nguồn #'.(int)$source['id'],'value'=>$source['id'],'uncheckValue'=>null)); ?></td><td><?php echo (int)$source['id']; ?></td><td><?php echo CHtml::encode($source['company']); ?></td><td><?php echo CHtml::encode(trim($source['first_name'].' '.$source['last_name'])); ?></td><td><?php echo CHtml::encode($source['email']); ?></td></tr><?php endforeach; ?>
</tbody></table>
<p>Trang <?php echo (int)$sources['page']; ?>, tổng <?php echo (int)$sources['total']; ?> bản ghi.</p>
<div class="form-group"><label>Hoặc thêm mã bản ghi nguồn (phân cách bằng dấu phẩy, tối đa 500 dòng mỗi đợt)</label><?php echo CHtml::textArea('manual_ids','',array('class'=>'form-control')); ?></div>
<?php echo CHtml::submitButton('Tạo bản xem trước từ các dòng đã chọn',array('class'=>'btn btn-primary')); echo CHtml::endForm(); ?>
<p><?php if ($sources['page']>1) { echo CHtml::link('Trang trước',array('admin','source_type'=>$sources['type'],'page'=>$sources['page']-1)); } ?>
<?php if ($sources['page']*50<$sources['total']) { echo CHtml::link('Trang sau',array('admin','source_type'=>$sources['type'],'page'=>$sources['page']+1)); } ?></p>
<h2>Các đợt gần đây</h2>
<table class="table table-striped"><thead><tr><th>Đợt</th><th>Nguồn</th><th>Trạng thái</th><th>Đối soát</th></tr></thead><tbody>
<?php foreach ($batches as $batch): ?><tr><td><?php echo CHtml::link('#'.$batch['id'],array('view','id'=>$batch['id'])); ?></td><td><?php echo CHtml::encode($batch['source_type']==='legacy_contact'?'Contact DB':'Sales Report'); ?></td><td><?php echo CHtml::encode($batch['status']); ?></td><td><?php $c=json_decode($batch['counts_json'],true); echo (int)$c['total'].' dòng; '.(int)$c['error'].' lỗi; '.(int)$c['needs_review'].' chờ'; ?></td></tr><?php endforeach; ?>
</tbody></table>
