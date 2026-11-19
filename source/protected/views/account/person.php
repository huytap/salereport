<?php $this->breadcrumbs=array('Khách hàng'=>array('admin'),$account->name=>array('view','id'=>$account->id),'Người liên hệ'); ?>
<h1><?php echo $model->isNewRecord?'Thêm người liên hệ':'Cập nhật người liên hệ'; ?></h1>
<p><?php echo CHtml::encode($account->name); ?></p>
<?php $this->renderPartial('_errors',array('errors'=>$errors)); ?>
<?php echo CHtml::beginForm($model->isNewRecord?array('contactcreate','id'=>$account->id):array('contactupdate','id'=>$account->id,'person_id'=>$model->id),'post'); echo CHtml::hiddenField('request_key',$requestKey); if (!$model->isNewRecord) { echo CHtml::hiddenField('expected_version',$version); } ?>
<?php $this->renderPartial('_personFields',array('person'=>$model)); ?>
<?php echo CHtml::submitButton('Lưu người liên hệ',array('class'=>'btn btn-primary')); echo CHtml::endForm(); ?>
<p><?php echo CHtml::link('Quay lại hồ sơ',array('view','id'=>$account->id,'tab'=>'contacts')); ?></p>
