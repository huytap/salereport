<?php

/** Fresh database state, never session role/hotel strings, is the authority. */
class ActionAccess
{
    public static function user($id = null)
    {
        if ($id === null) {
            if (!Yii::app()->hasComponent('user') || Yii::app()->user->isGuest) { return null; }
            $id = Yii::app()->user->id;
        }
        if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1) { return null; }
        $user = Users::model()->findByPk($id);
        return $user && $user->status === 'A' ? $user : null;
    }

    public static function names($value)
    {
        if (!is_string($value)) { return array(); }
        return array_values(array_unique(array_filter(array_map(function ($name) { return mb_strtolower(trim($name),'UTF-8'); }, explode(',', $value)), 'strlen')));
    }

    public static function catalog()
    {
        return require dirname(__FILE__).'/../../confgs/roles.php';
    }

    public static function actions($controller, $userId = null)
    {
        $controller = strtolower($controller);
        $catalog = self::catalog();
        if (!isset($catalog[$controller]) || !($user = self::user($userId))) { return array(); }
        if ((int)$user->id === 1) { return $catalog[$controller]; }
        $actions = array();
        foreach (self::names($user->roles) as $name) {
            $role = Role::model()->find('LOWER(TRIM(role_name))=:name',array(':name'=>$name));
            $rights = $role ? json_decode($role->access_rights,true) : null;
            if (!is_array($rights)) { continue; }
            foreach ($rights as $key => $list) {
                if (strtolower($key) !== $controller || !is_array($list)) { continue; }
                foreach ($list as $action) {
                    if (is_string($action) && in_array(strtolower($action),$catalog[$controller],true)) { $actions[] = strtolower($action); }
                }
            }
        }
        return array_values(array_unique($actions));
    }

    public static function can($controller, $action, $userId = null)
    {
        return in_array(strtolower($action),self::actions($controller,$userId),true);
    }

    public static function requireAction($controller, $action)
    {
        if (!self::can($controller,$action)) { throw new CHttpException(403,'This action is not permitted.'); }
    }

    public static function isAll($userId = null)
    {
        $user = self::user($userId);
        if (!$user) { return false; }
        if ((int)$user->id === 1) { return true; }
        $config = isset(Yii::app()->params['mvp']) ? Yii::app()->params['mvp'] : array();
        $allow = isset($config['globalScopeRoles']) ? $config['globalScopeRoles'] : array();
        $allow = array_map(function ($name) { return mb_strtolower(trim($name),'UTF-8'); }, $allow);
        foreach (array_intersect(self::names($user->roles),$allow) as $name) {
            if (Role::model()->exists('LOWER(TRIM(role_name))=:name',array(':name'=>$name))) { return true; }
        }
        return false;
    }
}
