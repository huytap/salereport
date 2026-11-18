<!-- Navigation -->
<nav class="navbar navbar-default navbar-static-top" role="navigation" style="margin-bottom: 0">
    <div class="navbar-header">
        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="<?php echo Yii::app()->createUrl('site/admin');?>">Ho Tram Resort</a>
    </div>
    <!-- /.navbar-header -->

    <ul class="nav navbar-top-links navbar-right" id="navMenu">
    <?php
    if(Yii::app()->user->id !== '1'){
        $menu = Yii::app()->params['menu'];
        $module = Yii::app()->params['module'];
        $role = Yii::app()->user->getState('type');
        $icon = Yii::app()->params['icon'];
        if($role){
            $roles = Role::model()->getNameRoleById($role);
            //echo "<pre>";print_r($roles);echo'</pre>';
            if(count($roles) >0) {
                foreach($roles as $key => $mn){
                    if(isset($module[$key]) && isset($menu[$key])){
                        echo '<li class="dropdown">';
                            echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown">'.$icon[$key].' '. $module[$key] .' <i class="fa fa-caret-down"></i></a>';
                            echo '<ul class="dropdown-menu">';
                                foreach($mn as $m){
                                    if(isset($menu[$key][$m])){
                                        echo '<li><a href="'.Yii::app()->createUrl($key.'/'.$m).'">'.$menu[$key][$m].'</a></li>';
                                    }
                                }
                            echo '</ul>';
                        echo '</li>';
                    }
                }
            }
        }
    }else{
    ?>
        <li class="dropdown">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-database fa-fw"></i> Contact DB <i class="fa fa-caret-down"></i></a>
            <ul class="dropdown-menu">
                <li><a href="<?php echo Yii::app()->createUrl('contact/admin')?>">All</a></li>
                <li><a href="<?php echo Yii::app()->createUrl('contact/create')?>">Add new</a></li>
                <li><a href="<?php echo Yii::app()->createUrl('contact/report')?>">Export to excel</a></li>
                <li><a href="<?php echo Yii::app()->createUrl('contact/import')?>">Import from CSV</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-edit fa-fw"></i> Sales Report <i class="fa fa-caret-down"></i></a>
            <ul class="dropdown-menu">
                <li><a href="<?php echo Yii::app()->createUrl('report/admin')?>">All</a></li>
                <li><a href="<?php echo Yii::app()->createUrl('report/create')?>">Add new</a></li>
                <li><a href="<?php echo Yii::app()->createUrl('report/import')?>">Import from CSV</a></li>
                <!-- <li><a href="<?php echo Yii::app()->createUrl('staffasst/admin')?>">Staff Assessment</a></li> -->
            </ul>
        </li>
        <!-- <li class="dropdown">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-bar-chart-o fa-fw"></i> Incident Report <i class="fa fa-caret-down"></i></a>
            <ul class="dropdown-menu">
                <li><a href="<?php echo Yii::app()->createUrl('incident/admin')?>">All</a></li>
                <li><a href="<?php echo Yii::app()->createUrl('incident/create')?>">Add new</a></li>
                <li><a href="<?php echo Yii::app()->createUrl('incident/import')?>">Import from CSV</a></li>

            </ul>
        </li> -->
        <li class="dropdown">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-user fa-fw"></i> Users <i class="fa fa-caret-down"></i></a>
            <ul class="dropdown-menu">
                <li><a href="<?php echo Yii::app()->createUrl('users/admin')?>">All</a></li>
                <li><a href="<?php echo Yii::app()->createUrl('users/create')?>">Add new</a></li>
                <li><a href="<?php echo Yii::app()->createUrl('users/history')?>">History</a></li>
            </ul>
        </li>
    <?php }?>
    <?php 

    if(Yii::app()->user->getState('type') !== 'fo'){?>
        <li class="dropdown">
            <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                <i class="fa fa-bell fa-fw"></i> Reminder<span id="total-no" class="label label-warning">0</span> <i class="fa fa-caret-down"></i>
            </a>
            <ul class="dropdown-menu dropdown-alerts" id="reminder-js">
            </ul>
            <!-- /.dropdown-alerts -->
        </li>
    <?php }?>
        <li class="dropdown">           
            <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                  
                <span class="user-info">
                    <small>Welcome</small>

                    <?php echo $user->fullname; ?>

                </span>
                <i class="fa fa-caret-down"></i>
            </a>
            <ul class="dropdown-menu dropdown-user">
                <li><a href="<?php echo Yii::app()->createUrl('site/changepassword', array('id' => $user->id)); ?>"><i class="fa fa-gear fa-lock"></i> Change Password</a>
                </li>
                <li class="divider"></li>
                <li><a href="<?php echo Yii::app()->createUrl('site/logout'); ?>"><i class="fa fa-sign-out fa-fw"></i> Logout</a>
                </li>
            </ul>
            <!-- /.dropdown-user -->
        </li>
        <!-- /.dropdown -->
    </ul>
    <!-- /.navbar-top-links -->

    
    <!-- /.navbar-static-side -->
</nav>