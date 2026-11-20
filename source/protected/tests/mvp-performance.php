<?php
/** Synthetic committed fixture benchmark with owned-ID cleanup. Never reads or copies operating customer rows. */
if (PHP_SAPI!=='cli') { exit(2); }
define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
class PerformanceActor extends CWebUser { public static $actor=1; public function init() {} public function getId() { return self::$actor; } public function getIsGuest() { return false; } }
$_SERVER['SCRIPT_FILENAME']=dirname(__DIR__,2).'/index-test.php'; $_SERVER['SCRIPT_NAME']='/index-test.php'; $_SERVER['HTTP_HOST']='fixture.local'; $_SERVER['REQUEST_URI']='/';
$config=require __DIR__.'/../config/test.php'; require_once __DIR__.'/support/MeasuredTestDbConnection.php'; $config['components']['db']['class']='MeasuredTestDbConnection'; $config['components']['user']=array('class'=>'PerformanceActor'); $app=Yii::createWebApplication($config); $db=$app->db; $db->active=true;
if ($db->createCommand('SELECT DATABASE()')->queryScalar()!=='sale_report_mvp_test_performance' || $db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1' || Account::model()->count() || Task::model()->count()) { throw new Exception('Empty dedicated synthetic performance DB required.'); }
require_once __DIR__.'/support/TemporaryTableFixture.php'; $userRows=$db->createCommand('SELECT * FROM users')->queryAll(); $ddl=$db->createCommand('SHOW CREATE TABLE users')->queryRow(); $db->createCommand(isolatedTemporaryTableDdl($ddl['Create Table']))->execute(); foreach ($userRows as $r) { $db->createCommand()->insert('users',$r); }
function perfInsert($table,array $values) {
    $db=Yii::app()->db; $row=array();
    foreach ($db->schema->getTable($table)->columns as $c=>$def) {
        if ($def->autoIncrement || $def->allowNull || $def->defaultValue!==null) { continue; }
        $row[$c]=strpos($def->dbType,'int')!==false?1:($def->dbType==='datetime'?gmdate('Y-m-d H:i:s'):($def->dbType==='date'?'2030-01-01':''));
    }
    $db->createCommand()->insert($table,array_merge($row,$values)); return (int)$db->lastInsertID;
}
function perfRead($name,$callback,$iterations=21) {
    $db=Yii::app()->db; $callback(); $ms=array(); $queries=array();
    for ($i=0;$i<$iterations;$i++) { $before=$db->statementCount; $start=microtime(true); $callback(); $ms[]=round((microtime(true)-$start)*1000,3); $queries[]=$db->statementCount-$before; }
    sort($ms); return array('name'=>$name,'samples'=>$iterations,'p50_ms'=>$ms[(int)floor(($iterations-1)*.5)],'p95_ms'=>$ms[(int)ceil($iterations*.95)-1],'max_sql'=>max($queries));
}
$tx=$db->beginTransaction(); $result=null; $seeded=false; $accounts=array(); $hotels=array(); $legacyBefore=$db->createCommand('SELECT * FROM reports ORDER BY id')->queryAll();
try {
    $sales=perfInsert('users',array('username'=>'perf_sales','fullname'=>'Synthetic Sales','roles'=>'users','status'=>'A','password'=>'fake'));
    $manager=perfInsert('users',array('username'=>'perf_manager','fullname'=>'Synthetic Manager','roles'=>'admin','status'=>'A','password'=>'fake'));
    $h1=perfInsert('crm_hotel',array('code'=>'PERF-H1','name'=>'Synthetic H1')); $h2=perfInsert('crm_hotel',array('code'=>'PERF-H2','name'=>'Synthetic H2')); $hotels=array($h1,$h2);
    perfInsert('crm_user_hotel_access',array('user_id'=>$sales,'hotel_id'=>$h1,'scope_level'=>'own')); perfInsert('crm_user_hotel_access',array('user_id'=>$manager,'hotel_id'=>$h1,'scope_level'=>'hotel'));
    $accounts=array(); $first=null; $day=new DateTimeImmutable('2030-01-01',new DateTimeZone('UTC')); $reportTemplate=$db->createCommand('SELECT * FROM reports LIMIT 1')->queryRow(); unset($reportTemplate['id']);
    for ($i=0;$i<2000;$i++) {
        $hotel=$i%2?$h2:$h1; $owner=$i%2?1:$sales;
        $aid=perfInsert('crm_account',array('code'=>'PERF-A'.$i,'name'=>sprintf('Synthetic Company %04d',$i),'normalized_name'=>sprintf('synthetic company %04d',$i))); $accounts[]=$aid; if ($first===null) { $first=$aid; }
        perfInsert('crm_account_hotel',array('account_id'=>$aid,'hotel_id'=>$hotel,'owner_user_id'=>$owner));
        perfInsert('crm_contact_person',array('account_id'=>$aid,'first_name'=>'Synthetic','last_name'=>'Contact '.$i,'email'=>'fixture@example.test'));
        $r=array_merge($reportTemplate,array('account_id'=>$aid,'hotel_id'=>$hotel,'identity_state'=>'linked','snapshot_json'=>'{}','company_name'=>'Synthetic Company '.$i)); $db->createCommand()->insert('reports',$r);
        for ($j=0;$j<8;$j++) {
            $n=$i*8+$j; $date=$day->modify('+'.($n%365).' days')->format('Y-m-d'); $appointment=$j===0; $due=$date.' 04:00:00';
            $tid=perfInsert('crm_task',array('code'=>'PERF-T'.$n,'title'=>'Synthetic task '.$n,'account_id'=>$aid,'hotel_id'=>$hotel,'assignee_user_id'=>$owner,'client_request_key'=>sprintf('perf_request_%020d',$n),'kind'=>$appointment?'appointment':'task','due_mode'=>$appointment?'datetime':'date','due_date'=>$appointment?null:$date,'due_at_utc'=>$appointment?$due:null,'deadline_at_utc'=>$appointment?$due:$date.' 17:00:00','starts_at_utc'=>$appointment?$date.' 03:00:00':null,'ends_at_utc'=>$appointment?$due:null));
            if (!$appointment && $n%4===0) {
                $rem=perfInsert('crm_task_reminder',array('task_id'=>$tid,'recipient_user_id'=>$owner,'schedule_version'=>1,'reminder_kind'=>'day','state'=>'delivered','notify_at_utc'=>'2026-01-01 00:00:00'));
                perfInsert('crm_notification',array('task_id'=>$tid,'recipient_user_id'=>$owner,'reminder_id'=>$rem,'schedule_version'=>1,'kind'=>'day'));
            }
        }
    }
    $counts=array(); foreach (array('crm_account','crm_task','reports','crm_notification') as $t) { $counts[$t]=(int)$db->createCommand('SELECT COUNT(*) FROM '.$t)->queryScalar(); }
    $tx->commit(); $seeded=true;
    foreach (array('crm_account','crm_account_hotel','crm_task','crm_notification','crm_task_reminder','reports') as $table) { $db->createCommand('ANALYZE TABLE '.$table)->queryAll(); }
    $db->enableProfiling=false; $measurements=array();
    foreach (array('Own'=>$sales,'Hotel'=>$manager,'All'=>1) as $scope=>$actor) {
        PerformanceActor::$actor=$actor;
        $measurements[]=perfRead($scope.'/accounts',function(){ AccountReadService::listing(array(),1); });
        $measurements[]=perfRead($scope.'/tasks_render_data',function(){ $data=TaskReadService::listing(array(),'all',1); foreach ($data['tasks'] as $t) { $x=array($t->account->name,$t->hotel->name,$t->assignee->fullname); } });
        $measurements[]=perfRead($scope.'/calendar',function(){ TaskReadService::calendar('2030-01-01','2030-01-08','Asia/Ho_Chi_Minh'); });
        $measurements[]=perfRead($scope.'/account_detail',function()use($first){ AccountReadService::detail($first,'tasks',1); });
        $measurements[]=perfRead($scope.'/summary',function(){ ReminderService::summary(); });
    }
    $plans=array();
    foreach (array('account_list'=>array('Account',"t.status='active'",'t.name,t.id'),'task_dates'=>array('Task',"t.kind='task' AND t.due_mode='date' AND t.due_date>='2030-01-01' AND t.due_date<'2030-01-08'",'t.due_date,t.id'),'task_list'=>array('Task','1=1','t.deadline_at_utc,t.id')) as $name=>$q) {
        $m=$q[0]::model(); $c=new CDbCriteria(array('condition'=>$q[1],'order'=>$q[2],'limit'=>25)); $command=$m->commandBuilder->createFindCommand($m->tableSchema,$c); $plans[$name]=$db->createCommand('EXPLAIN FORMAT=TRADITIONAL '.$command->text)->queryAll();
    }
    $result=array('measured_at_utc'=>gmdate('c'),'php'=>PHP_VERSION,'mysql'=>$db->createCommand('SELECT VERSION()')->queryScalar(),'server_comment'=>$db->createCommand('SELECT @@version_comment')->queryScalar(),'architecture'=>php_uname('m'),'fixture_counts'=>$counts,'method'=>'21 warm serial samples per service including rendered Task relations; counting DB command executions without SQL profiler retention; committed synthetic fixture with ANALYZE TABLE, owned-ID cleanup; not HTTP/concurrent production load','measurements'=>$measurements,'explain'=>$plans);
    $failures=array();
    if (!in_array('--baseline',$argv,true)) {
        foreach ($measurements as $m) { if ($m['p95_ms']>=2000) { $failures[]='Synthetic p95 budget exceeded: '.$m['name']; } if ((strpos($m['name'],'tasks_render_data')!==false || strpos($m['name'],'calendar')!==false) && $m['max_sql']>50) { $failures[]='N+1 query budget exceeded: '.$m['name'].' '.$m['max_sql']; } }
    }
    $result['result']=$failures?'FAIL':'PASS'; $result['failures']=$failures;
} finally {
    $db->enableProfiling=false; if ($tx->active) { $tx->rollback(); }
    if ($seeded) {
        $cleanup=$db->beginTransaction();
        try {
            foreach (array_chunk($accounts,250) as $ids) {
                $list=implode(',',array_map('intval',$ids));
                foreach (array('crm_notification','crm_task_reminder') as $table) { $db->createCommand('DELETE FROM '.$table.' WHERE task_id IN (SELECT id FROM crm_task WHERE account_id IN ('.$list.'))')->execute(); }
                foreach (array('crm_task','reports','crm_contact_person','crm_account_hotel') as $table) { $db->createCommand('DELETE FROM '.$table.' WHERE account_id IN ('.$list.')')->execute(); }
                $db->createCommand('DELETE FROM crm_account WHERE id IN ('.$list.')')->execute();
            }
            foreach ($hotels as $id) { $db->createCommand()->delete('crm_user_hotel_access','hotel_id=:id',array(':id'=>$id)); $db->createCommand()->delete('crm_hotel','id=:id',array(':id'=>$id)); }
            $cleanup->commit();
        } catch (Exception $e) { $cleanup->rollback(); throw $e; }
    }
    $db->createCommand('DROP TEMPORARY TABLE users')->execute();
    if ($db->createCommand('SELECT * FROM reports ORDER BY id')->queryAll()!==$legacyBefore || Account::model()->count() || Task::model()->count() || Notification::model()->count()) { throw new Exception('Performance fixture cleanup/source integrity failed.'); }
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n"; exit($result['result']==='PASS'?0:1);
