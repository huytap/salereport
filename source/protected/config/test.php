<?php

$config = require dirname(__FILE__).'/main.php';
$config['name'] = 'Sales Report isolated tests';
$config['components']['db'] = require dirname(__FILE__).'/db-test.php';
$config['components']['fixture'] = array('class' => 'system.test.CDbFixtureManager');
$config['components']['log']['routes'] = array();
$config['modules'] = array();
$config['params']['mvp'] = require dirname(__FILE__).'/mvp.php';
$config['params']['mvp']['legacyImportEnabled'] = true;
return $config;
