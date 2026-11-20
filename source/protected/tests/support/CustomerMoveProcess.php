<?php
if (PHP_SAPI!=='cli') { exit(2); }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../../yii/yii.php');
$config=require __DIR__.'/../../config/console.php'; foreach (array('customerWrite','reportWrite','taskWrite') as $f) { $config['params']['mvp'][$f]=true; }
$app=Yii::createConsoleApplication($config);
class MoveProcessActor extends CApplicationComponent { public $id=1; public function getIsGuest() { return false; } }
$app->setComponent('user',new MoveProcessActor);
try { echo json_encode(CustomerTransferService::commit($argv[1]))."\n"; exit(0); }
catch (CHttpException $e) { echo json_encode(array('status'=>$e->statusCode))."\n"; exit($e->statusCode===409?3:2); }
catch (Exception $e) { echo "{\"status\":500}\n"; exit(2); }
