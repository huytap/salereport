<?php

require_once dirname(__FILE__).'/../components/IsolatedTestDbConnection.php';
$dsn = getenv('SALEREPORT_TEST_DSN') ?: 'mysql:host=127.0.0.1;dbname=sale_report_mvp_test';
IsolatedTestDbConnection::databaseName($dsn);
return array(
    'class' => 'IsolatedTestDbConnection',
    'connectionString' => $dsn,
    'username' => getenv('SALEREPORT_TEST_USER') ?: 'root',
    'password' => getenv('SALEREPORT_TEST_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'emulatePrepare' => false,
    'enableProfiling' => false,
    'enableParamLogging' => false,
);
