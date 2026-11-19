<!-- Navigation -->
<nav class="navbar navbar-default navbar-static-top" role="navigation" style="margin-bottom:0">
    <div class="navbar-header"><a class="navbar-brand" href="<?php echo Yii::app()->createUrl('site/admin'); ?>">Ho Tram Resort</a></div>
    <ul class="nav navbar-top-links navbar-right" id="navMenu">
    <?php foreach (Yii::app()->params['menu'] as $controller=>$items):
        if ($controller==='account' && !CustomerPolicy::ready()) { continue; }
        if ($controller==='customerimport' && (!ActionAccess::isAll() || empty(Yii::app()->params['mvp']['legacyImportEnabled']))) { continue; }
        $allowed = ActionAccess::actions($controller);
        $visible = array_intersect_key($items,array_flip($allowed));
        // Creation/import through unsafe legacy forms is available only to All during transition.
        if ((!ActionAccess::isAll() || !empty(Yii::app()->params['mvp']['customerWrite'])) && in_array($controller,array('contact','report'),true)) { unset($visible['create'],$visible['import']); }
        if (!$visible) { continue; }
    ?>
        <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php echo CHtml::encode(Yii::app()->params['module'][$controller]); ?> <i class="fa fa-caret-down"></i></a>
            <ul class="dropdown-menu"><?php foreach ($visible as $action=>$label): ?><li><a href="<?php echo Yii::app()->createUrl($controller.'/'.$action); ?>"><?php echo CHtml::encode($label); ?></a></li><?php endforeach; ?></ul>
        </li>
    <?php endforeach; ?>
        <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown">Welcome <?php echo CHtml::encode($user ? $user->fullname : ''); ?></a>
            <ul class="dropdown-menu dropdown-user">
                <li><a href="<?php echo Yii::app()->createUrl('site/changepassword'); ?>">Change Password</a></li>
                <li><?php echo CHtml::beginForm(array('site/logout'),'post'); ?><?php echo CHtml::submitButton('Logout',array('class'=>'btn btn-link')); ?><?php echo CHtml::endForm(); ?></li>
            </ul>
        </li>
    </ul>
</nav>
