<?php
/** Explicit browser rehearsal process only; separate DB, cookies and session from normal web. */
$config=require __DIR__.'/test.php';
if (getenv('SALEREPORT_BROWSER_UAT')!=='1' || IsolatedTestDbConnection::databaseName($config['components']['db']['connectionString'])!=='sale_report_mvp_test_browser_uat') { throw new CException('Browser UAT requires its dedicated synthetic DB and process opt-in.'); }
$config['name']='CRM Browser UAT — dữ liệu giả';
$config['components']['user']['allowAutoLogin']=false;
$config['components']['user']['stateKeyPrefix']='browser_uat_';
$config['components']['session']=array('class'=>'CHttpSession','sessionName'=>'SR_BROWSER_UAT','cookieParams'=>array('httponly'=>true,'path'=>'/'));
$config['components']['request']['csrfTokenName']='BROWSER_UAT_CSRF';
return $config;
