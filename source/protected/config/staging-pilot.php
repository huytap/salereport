<?php
/** Explicit browser rehearsal process only; separate DB, cookies and session from normal web. */
$config=require __DIR__.'/test.php';
if (getenv('SALEREPORT_STAGING_PILOT')!=='1' || IsolatedTestDbConnection::databaseName($config['components']['db']['connectionString'])!=='sale_report_mvp_test_staging_pilot') { throw new CException('Browser UAT requires its dedicated synthetic DB and process opt-in.'); }
$config['name']='STAGING PILOT — dữ liệu giả';
$config['components']['user']['allowAutoLogin']=false;
$config['components']['user']['stateKeyPrefix']='staging_pilot_';
$config['components']['session']=array('class'=>'CHttpSession','sessionName'=>'SR_STAGING_PILOT','cookieParams'=>array('httponly'=>true,'path'=>'/'));
$config['components']['request']['csrfTokenName']='STAGING_PILOT_CSRF';
return $config;
