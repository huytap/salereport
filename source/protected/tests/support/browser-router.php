<?php
/** Local PHP development server only. No production routing/configuration changes. */
if (PHP_SAPI!=='cli-server' || getenv('SALEREPORT_BROWSER_UAT')!=='1' || !in_array(isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'',array('127.0.0.1','::1'),true)) { http_response_code(404); exit; }
$root=dirname(__DIR__,3); $path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)); $file=realpath($root.$path);
if ($file && strpos($file,$root.DIRECTORY_SEPARATOR)===0 && is_file($file) && preg_match('/\.(?:css|js|png|jpg|jpeg|gif|ico|svg|woff2?|ttf|eot)$/i',$file) && strpos($file,$root.'/protected/')!==0) { return false; }
$_SERVER['SCRIPT_NAME']='/index-test.php'; $_SERVER['SCRIPT_FILENAME']=$root.'/index-test.php'; $_SERVER['PHP_SELF']='/index-test.php';
require $root.'/index-test.php';
