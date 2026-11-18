<?php

class RoleHelper
{
    public static function GetRole($forceController = '')
    {
        $controller = $forceController ?: Yii::app()->controller->id;
        $actions = ActionAccess::actions($controller);
        // Yii treats an empty action array as ALL actions in an allow rule.
        return $actions ?: array('__no_permitted_action__');
    }
}
