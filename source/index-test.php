<?php
/**
 * This is the bootstrap file for test application.
 * This file should be removed when the application is deployed for production.
 */

// change the following paths if necessary
$yii=getenv('YII_FRAMEWORK_PATH') ?: dirname(__FILE__).'/../../yii/yii.php';
$config=dirname(__FILE__).'/protected/config/'.(getenv('SALEREPORT_BROWSER_UAT')==='1'?'browser-uat.php':'test.php');

// remove the following line when in production mode
defined('YII_DEBUG') or define('YII_DEBUG',getenv('SALEREPORT_BROWSER_UAT')!=='1');

require_once($yii);
Yii::createWebApplication($config)->run();
