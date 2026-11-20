<!-- Navigation -->
<nav class="navbar navbar-default navbar-static-top" role="navigation" style="margin-bottom:0">
    <div class="navbar-header"><a class="navbar-brand" href="<?php echo Yii::app()->createUrl('site/admin'); ?>">Ho Tram Resort</a></div>
    <ul class="nav navbar-top-links navbar-right" id="navMenu">
    <?php foreach (Yii::app()->params['menu'] as $controller=>$items):
        if (in_array($controller,array('account','task'),true) && !CustomerPolicy::ready()) { continue; }
        if ($controller==='customerimport' && (!ActionAccess::isAll() || empty(Yii::app()->params['mvp']['legacyImportEnabled']))) { continue; }
        $allowed = ActionAccess::actions($controller);
        $visible = array_intersect_key($items,array_flip($allowed));
        // Creation/import through unsafe legacy forms is available only to All during transition.
        if ((!ActionAccess::isAll() || !empty(Yii::app()->params['mvp']['customerWrite'])) && in_array($controller,array('contact','report'),true)) { unset($visible['create'],$visible['import']); }
        if ($controller==='report' && !empty(Yii::app()->params['mvp']['reportWrite'])) { unset($visible['import']); }
        if ($controller==='report' && !empty(Yii::app()->params['mvp']['reportWrite']) && ActionAccess::can('report','create') && isset($items['create'])) { $visible['create']=$items['create']; }
        if (!$visible) { continue; }
    ?>
        <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php echo CHtml::encode(Yii::app()->params['module'][$controller]); ?> <i class="fa fa-caret-down"></i></a>
            <ul class="dropdown-menu"><?php foreach ($visible as $action=>$label): ?><li><a href="<?php echo Yii::app()->createUrl($controller.'/'.$action); ?>"><?php echo CHtml::encode($label); ?></a></li><?php endforeach; ?></ul>
        </li>
    <?php endforeach; ?>
        <?php if (ReminderService::available() && ActionAccess::can('reminder','admin') && ActionAccess::can('reminder','summary') && ActionAccess::can('task','view')): ?>
        <li class="dropdown" id="mvp-reminder-menu" data-summary-url="<?php echo CHtml::encode(Yii::app()->createUrl('reminder/summary',array('format'=>'json'))); ?>" data-inbox-url="<?php echo CHtml::encode(Yii::app()->createUrl('reminder/admin')); ?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-bell" aria-hidden="true"></i> Thông báo <span class="badge" id="mvp-reminder-count" aria-live="polite">—</span></a>
            <ul class="dropdown-menu" id="mvp-reminder-preview"><li><?php echo CHtml::link('Xem thông báo',array('/reminder/admin')); ?></li></ul>
        </li>
        <?php if (ActionAccess::can('task','admin')): ?><li><?php echo CHtml::link('Việc của tôi quá hạn: <span id="mvp-overdue-count">—</span>',array('/task/admin','tab'=>'overdue','assignee_user_id'=>Yii::app()->user->id)); ?></li><?php else: ?><li style="display:none"><span id="mvp-overdue-count"></span></li><?php endif; ?>
        <?php endif; ?>
        <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown">Welcome <?php echo CHtml::encode($user ? $user->fullname : ''); ?></a>
            <ul class="dropdown-menu dropdown-user">
                <li><a href="<?php echo Yii::app()->createUrl('site/changepassword'); ?>">Change Password</a></li>
                <li><?php echo CHtml::beginForm(array('site/logout'),'post'); ?><?php echo CHtml::submitButton('Logout',array('class'=>'btn btn-link')); ?><?php echo CHtml::endForm(); ?></li>
            </ul>
        </li>
    </ul>
</nav>

<?php if (ReminderService::available() && ActionAccess::isAll() && ActionAccess::can('reminder','monitor')): ?>
<div class="alert alert-warning" id="mvp-dispatch-banner" style="display:none" role="status" aria-live="polite"></div>
<?php endif; ?>
