<?php if ($errors): ?><div class="alert alert-danger" role="alert"><strong>Chưa lưu được dữ liệu:</strong><ul>
<?php foreach ($errors as $field=>$messages): foreach ($messages as $message): ?><li><?php echo CHtml::encode($field.': '.$message); ?></li><?php endforeach; endforeach; ?>
</ul></div><?php endif; ?>
