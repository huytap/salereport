<?php

/** Form model only; there is no contact_form table in the database. */
class ContactForm extends CFormModel
{
    public $name;
    public $email;
    public $subject;
    public $body;
    public $verifyCode;

    public function rules()
    {
        return array(
            array('name, email, subject, body', 'required'),
            array('name, email, subject, body, verifyCode', 'validateText'),
            array('email', 'email', 'skipOnError' => true),
            array('name, subject', 'length', 'max' => 255, 'skipOnError' => true),
            array('email', 'length', 'max' => 254, 'skipOnError' => true),
        );
    }

    public function validateText($attribute, $params)
    {
        if ($this->$attribute !== null && !is_string($this->$attribute)) {
            $this->addError($attribute, $this->getAttributeLabel($attribute).' must be text.');
        }
    }

    public function attributeLabels()
    {
        return array('name' => 'Name', 'email' => 'Email', 'subject' => 'Subject',
            'body' => 'Body', 'verifyCode' => 'Verification Code');
    }
}
