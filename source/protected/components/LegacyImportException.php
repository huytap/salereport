<?php

class LegacyImportException extends CException
{
    public $errorCode;
    public function __construct($errorCode, $message = '')
    {
        $this->errorCode=$errorCode;
        parent::__construct($message ?: $errorCode);
    }
}
