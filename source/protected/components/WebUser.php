<?php

class WebUser extends CWebUser
{
    public $type;
    public function isUser() { $user=ActionAccess::user(); return $user && in_array('users',ActionAccess::names($user->roles),true); }
    public function isAdmin() { return ActionAccess::isAll(); }
}
