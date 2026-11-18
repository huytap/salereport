<a class="menu-toggler" id="menu-toggler" href="#">
    <span class="menu-text"></span>
</a>

<?php
$arrAyMenu = array(
    'default' => array(
        'parent' => array(
            'link' => Yii::app()->createUrl('site/admin'),
            'icon' => '<i class="icon-dashboard"></i>',
            'name' => '<span class="menu-text"> Home </span>'
        ),
    ),
    'contact' => array(
        'parent' => array(
            'link' => Yii::app()->createUrl('contact/admin'),
            'icon' => '<i class=" icon-envelope"></i>',
            'name' => '<span class="menu-text"> Contact DB </span>'
        )
    ),
    'report' => array(
        'parent' => array(
            'link' => Yii::app()->createUrl('contact/admin'),
            'icon' => '<i class=" icon-envelope"></i>',
            'name' => '<span class="menu-text"> Weekly Sales Report </span>'
        )
    ),
    /*'import' => array(
        'parent' => array(
            'link' => Yii::app()->createUrl('contact/import'),
            'icon' => '<i class=" icon-upload"></i>',
            'name' => '<span class="menu-text"> Import Contact </span>'
        )
    ),
   'import_imap' => array(
        'parent' => array(
            'link' => Yii::app()->createUrl('contact/importuser'),
            'icon' => '<i class=" icon-upload"></i>',
            'name' => '<span class="menu-text"> Import (IMAP) </span>'
        )
    ),*/
    'users' => array(
        'parent' => array(
            'link' => Yii::app()->createUrl('users/admin'),
            'icon' => '<i class="icon-user"></i>',
            'name' => '<span class="menu-text">Users </span>'
        ),
    ),
    'logout' => array(
        'parent' => array(
            'link' => Yii::app()->createUrl('site/logout'),
            'icon' => '<i class="icon-unlock"></i>',
            'name' => '<span class="menu-text">Logout </span>'
        ),
    ),
);

$htmlMenu = '<li><a href="link">icon name </a></li>';
$htmlMenuParent = '<a href="" class="dropdown-toggle">iconpar';

$user = Users::model()->findByPk(Yii::app()->user->id);
if($user['roles']=='user' && $user['id']!=='1'){
    unset($arrAyMenu['users']);
    unset($arrAyMenu['import']);
}


?>



<div class="sidebar" id="sidebar">
    <ul class="nav nav-list">
        <?php

        foreach ($arrAyMenu as $menu) {
            if (!isset($menu['submenu'])) {
                echo str_replace(array('link', 'icon', 'name'), $menu['parent'], $htmlMenu);
            } else {
                echo "<li>";
                echo '<a href="" class="dropdown-toggle">';
                echo $menu['parent']['icon'] . $menu['parent']['name'];
                echo '</a>';
                //submenu
                echo "<ul class='submenu'>";
                if (count($menu['submenu']) > 0):
                    foreach ($menu['submenu'] as $submenu):
                        echo str_replace(array('link', 'icon', 'name'), $submenu, $htmlMenu);
                    endforeach;
                endif;
                echo "</ul>";
                echo "</li>";
            }
        }
        // endif;
        ?>

    </ul>
    <!--/.nav-list-->

    <div class="sidebar-collapse" id="sidebar-collapse">
        <i class="icon-double-angle-left"></i>
    </div>
</div>