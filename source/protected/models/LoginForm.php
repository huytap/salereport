<?php

class LoginForm extends LoginFormAm
{
    public $rememberMe = false;

    public function rules()
    {
        return array(
            array('username, password', 'required'),
            array('username, password', 'validateText'),
            array('rememberMe', 'boolean'),
            array('password', 'authenticate', 'skipOnError' => true),
        );
    }

    public function validateText($attribute, $params)
    {
        if (!is_string($this->$attribute)) {
            $this->addError($attribute, $this->getAttributeLabel($attribute).' must be text.');
        }
    }
}
