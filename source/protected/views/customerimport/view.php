<?php
$batch=$data['batch']; $d=$row['decision'] ?: array('mode'=>'map','account_kind'=>'create','contact_kind'=>'none','reason'=>'');
$proposal=$row['proposed']; $av=array_merge($proposal['values']['account'],isset($d['account_fields'])?$d['account_fields']:array());
$pv=array_merge($proposal['values']['contact'],isset($d['contact_fields'])?$d['contact_fields']:array());
$hotelOptions=array(''=>'Không chọn'); foreach ($hotels as $h) { $hotelOptions[$h->id]=$h->name; }
$ownerOptions=array('keep'=>'Giữ người phụ trách hiện có',''=>'Chưa phân công'); foreach ($owners as $owner) { $ownerOptions[$owner->id]=$owner->fullname; }
$warningLabels=array('MISSING_COMPANY'=>'Thiếu tên doanh nghiệp','SAME_NAME_IN_SNAPSHOT'=>'Nhiều dòng nguồn cùng tên doanh nghiệp','ACCOUNT_MATCH_REQUIRES_REVIEW'=>'Có hồ sơ doanh nghiệp tương tự cần đối chiếu','CONTACT_MATCH_REQUIRES_REVIEW'=>'Có người liên hệ trùng tên hoặc email cần đối chiếu','HOTEL_UNRESOLVED'=>'Chưa xác định chắc khách sạn','REPORT_MULTIPLE_HOTELS'=>'Report ghi nhiều khách sạn, cần chọn một đích','OWNER_UNRESOLVED'=>'Chưa xác định người phụ trách','REPORT_CREATOR_IS_NOT_OWNER'=>'Người lập Report không mặc nhiên là người phụ trách khách','INVALID_DOB_REQUIRES_REVIEW'=>'Ngày sinh nguồn không hợp lệ, cần xác nhận','INVALID_EMAIL_REQUIRES_REVIEW'=>'Email nguồn cần sửa hoặc xác nhận không tạo liên hệ','NO_CONTACT_PERSON_NAME'=>'Chưa có tên người liên hệ','EXISTING_SOURCE_LINK'=>'Bản ghi đã có mapping trước đó','MARKET_REQUIRES_REVIEW'=>'Kiểm tra các thị trường trước khi nhập');
$locked=in_array($row['result_state'],array('created','linked','skipped'),true);
?>
<h1>Đợt #<?php echo (int)$batch['id']; ?> — <?php echo $batch['source_type']==='legacy_contact'?'Contact DB':'Sales Report'; ?></h1>
<p>Trạng thái: <strong><?php echo CHtml::encode($batch['status']); ?></strong>. Tổng <?php echo (int)$data['counts']['total']; ?> dòng: <?php echo (int)$data['counts']['created']; ?> tạo mới, <?php echo (int)$data['counts']['linked']; ?> liên kết, <?php echo (int)$data['counts']['skipped']; ?> bỏ qua, <?php echo (int)$data['counts']['error']; ?> lỗi, <?php echo (int)$data['counts']['needs_review']; ?> chờ.</p>
<?php echo CHtml::beginForm(array('approve','id'=>$batch['id']),'post'); echo CHtml::hiddenField('ui','1'); echo CHtml::hiddenField('expected_version',$batch['lock_version']); echo CHtml::submitButton('Duyệt toàn bộ mapping hiện tại',array('class'=>'btn btn-default','disabled'=>$batch['status']==='completed')); echo CHtml::endForm(); ?>
<?php echo CHtml::beginForm(array('commit','id'=>$batch['id']),'post'); echo CHtml::hiddenField('ui','1'); echo CHtml::hiddenField('limit','100'); echo CHtml::submitButton('Nhập / tiếp tục 100 dòng',array('class'=>'btn btn-primary','disabled'=>!in_array($batch['status'],array('approved','running','failed'),true))); echo CHtml::endForm(); ?>
<p><?php echo CHtml::link('Xem đối soát chi tiết',array('reconcile','id'=>$batch['id'])); ?></p>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Dòng / mã nguồn</th><th>Doanh nghiệp</th><th>Kết quả</th><th>Mapping</th></tr></thead><tbody>
<?php foreach ($data['rows'] as $item): ?><tr><td><?php echo CHtml::link($item['row_no'].' / '.$item['source_id'],array('view','id'=>$batch['id'],'row'=>$item['row_no'])); ?></td><td><?php echo CHtml::encode($item['proposed']['values']['account']['name']); ?></td><td><?php echo CHtml::encode($item['result_state'].($item['error_code']?' — '.$item['error_code']:'')); ?></td><td><?php echo $item['decision']?'Đã chọn':'Chưa chọn'; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<h2>Mapping dòng <?php echo (int)$row['row_no']; ?></h2>
<p>Dữ liệu nguồn được giữ nguyên trong snapshot. Tên trùng là gợi ý kiểm tra, không tự gộp. Có thể dùng một dòng trước trong đợt làm hồ sơ chung.</p>
<?php if ($proposal['warnings']): ?><div class="alert alert-info">Cần rà soát: <?php echo CHtml::encode(implode('; ',array_map(function($code)use($warningLabels){return isset($warningLabels[$code])?$warningLabels[$code]:$code;},$proposal['warnings']))); ?></div><?php endif; ?>
<?php if ($proposal['account_candidates']): ?><h3>Hồ sơ có thể liên quan</h3><ul><?php foreach ($proposal['account_candidates'] as $candidate): ?><li><?php echo CHtml::encode('#'.$candidate['id'].' — '.$candidate['code'].' — '.$candidate['name'].' ('.$candidate['status'].')'); ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (!empty($proposal['contact_candidates'])): ?><h3>Người liên hệ có thể liên quan</h3><ul><?php foreach ($proposal['contact_candidates'] as $candidate): ?><li><?php echo CHtml::encode('#'.$candidate['id'].' — '.$candidate['name'].' — '.$candidate['email'].' — hồ sơ #'.$candidate['account_id']); ?><?php if ($candidate['reason']==='shared_email_verify_person'): ?> (email dùng chung, không mặc nhiên cùng một người)<?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (!empty($row['result']['fields'])): ?><div class="alert alert-danger"><?php foreach ($row['result']['fields'] as $field=>$messages): ?><p><?php echo CHtml::encode($field.': '.implode('; ',$messages)); ?></p><?php endforeach; ?></div><?php endif; ?>
<details><summary>Đối chiếu bản ghi nguồn</summary><table class="table table-condensed"><?php foreach ($row['raw'] as $field=>$value): ?><tr><th><?php echo CHtml::encode($field); ?></th><td style="white-space:pre-wrap"><?php echo CHtml::encode((string)$value); ?></td></tr><?php endforeach; ?></table></details>
<?php if (!$locked): ?>
<?php echo CHtml::beginForm(array('map','id'=>$batch['id'],'row'=>$row['row_no']),'post'); echo CHtml::hiddenField('ui','1'); echo CHtml::hiddenField('expected_version',$row['lock_version']); ?>
<div class="form-group"><label>Quyết định</label><?php echo CHtml::dropDownList('decision[mode]',$d['mode'],array('map'=>'Nhập / nối hồ sơ','existing'=>'Giữ mapping đã nhập trước đó','skip'=>'Bỏ qua dòng này'),array('class'=>'form-control')); ?></div>
<div class="row"><div class="col-md-6">
<h3>Doanh nghiệp</h3>
<?php echo CHtml::dropDownList('decision[account_kind]',isset($d['account_kind'])?$d['account_kind']:'create',array('create'=>'Tạo hồ sơ mới','link'=>'Chọn hồ sơ có sẵn','row'=>'Dùng hồ sơ của dòng trước'),array('class'=>'form-control')); ?>
<label>ID hồ sơ có sẵn</label><?php echo CHtml::numberField('decision[account_id]',isset($d['account_id'])?$d['account_id']:'',array('class'=>'form-control','min'=>1)); ?>
<label>Hoặc số dòng đã nhập hồ sơ trong đợt</label><?php echo CHtml::numberField('decision[account_row_no]',isset($d['account_row_no'])?$d['account_row_no']:'',array('class'=>'form-control','min'=>1)); ?>
<?php foreach (array('name'=>'Tên doanh nghiệp','segment'=>'Phân khúc','phone'=>'Điện thoại doanh nghiệp','email'=>'Email doanh nghiệp đã xác nhận','address_1'=>'Địa chỉ') as $field=>$label): ?><label><?php echo $label; ?> (khi tạo mới)</label><?php echo CHtml::textField('decision[account_fields]['.$field.']',isset($av[$field])?$av[$field]:'',array('class'=>'form-control')); ?><?php endforeach; ?>
<label><?php echo CHtml::checkBox('decision[allow_duplicate_account]',!empty($d['allow_duplicate_account'])); ?> Xác nhận tạo doanh nghiệp khác dù trùng tên</label>
<label><?php echo CHtml::checkBox('decision[remember_alias]',!empty($d['remember_alias'])); ?> Ghi nhớ tên nguồn như tên gọi khác đã duyệt</label>
</div><div class="col-md-6"><h3>Người liên hệ</h3>
<?php echo CHtml::dropDownList('decision[contact_kind]',isset($d['contact_kind'])?$d['contact_kind']:'none',array('none'=>'Không tạo người liên hệ','create'=>'Tạo người liên hệ','link'=>'Chọn người liên hệ có sẵn','row'=>'Dùng người liên hệ của dòng trước'),array('class'=>'form-control')); ?>
<label>ID người liên hệ có sẵn</label><?php echo CHtml::numberField('decision[contact_person_id]',isset($d['contact_person_id'])?$d['contact_person_id']:'',array('class'=>'form-control','min'=>1)); ?>
<label>Hoặc số dòng đã nhập người liên hệ</label><?php echo CHtml::numberField('decision[contact_row_no]',isset($d['contact_row_no'])?$d['contact_row_no']:'',array('class'=>'form-control','min'=>1)); ?>
<?php foreach (array('first_name'=>'Tên','last_name'=>'Họ','email'=>'Email','dob'=>'Ngày sinh YYYY-MM-DD') as $field=>$label): ?><label><?php echo $label; ?> (khi tạo mới)</label><?php echo CHtml::textField('decision[contact_fields]['.$field.']',isset($pv[$field])?$pv[$field]:'',array('class'=>'form-control')); ?><?php endforeach; ?>
<label><?php echo CHtml::checkBox('decision[allow_duplicate_contact]',!empty($d['allow_duplicate_contact'])); ?> Xác nhận người khác dù trùng họ tên</label>
</div></div>
<h3>Khách sạn và người phụ trách</h3>
<p>Report phải chọn đúng một khách sạn. Để “Giữ người phụ trách hiện có” khi nối hồ sơ; hồ sơ mới sẽ ở trạng thái chưa phân công nếu không chọn sales.</p>
<?php $assignments=isset($d['hotels'])?$d['hotels']:array(); $n=$batch['source_type']==='legacy_report'?1:max(3,count($assignments)); for ($i=0;$i<$n;$i++): $choice=isset($assignments[$i])?$assignments[$i]:array(); ?>
<div class="row"><div class="col-md-6"><?php echo CHtml::dropDownList('decision[hotels]['.$i.'][hotel_id]',isset($choice['hotel_id'])?$choice['hotel_id']:'',$hotelOptions,array('class'=>'form-control')); ?></div><div class="col-md-6"><?php echo CHtml::dropDownList('decision[hotels]['.$i.'][owner_user_id]',array_key_exists('owner_user_id',$choice)?$choice['owner_user_id']:'keep',$ownerOptions,array('class'=>'form-control')); ?></div></div>
<?php endfor; ?>
<label>Thị trường đã xác nhận (phân cách bằng dấu phẩy)</label><?php echo CHtml::textField('markets',isset($d['markets'])?implode(',',$d['markets']):implode(', ',isset($proposal['market_suggestions'])?$proposal['market_suggestions']:array()),array('class'=>'form-control')); ?>
<label>Lý do / căn cứ mapping</label><?php echo CHtml::textArea('decision[reason]',$d['reason'],array('class'=>'form-control','required'=>true)); ?>
<?php echo CHtml::submitButton('Lưu mapping dòng này',array('class'=>'btn btn-primary')); echo CHtml::endForm(); ?>
<?php else: ?><p>Dòng đã có kết quả cuối cùng, không sửa mapping tại đây. Bản ghi nguồn và lịch sử đối soát vẫn được giữ.</p><?php endif; ?>
