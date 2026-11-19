<?php

class CustomerValidationException extends CHttpException
{
    public $errors;
    public function __construct(array $errors)
    {
        $this->errors=$errors;
        parent::__construct(422,'Vui lòng kiểm tra các trường được đánh dấu.');
    }
}
