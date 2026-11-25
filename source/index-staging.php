<?php
if (getenv('SALEREPORT_STAGING_PILOT')!=='1') { http_response_code(404); exit; }
defined('YII_DEBUG') or define('YII_DEBUG',false);
require (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../yii/yii.php');
Yii::createWebApplication(require __DIR__.'/protected/config/staging-pilot.php')->run();
