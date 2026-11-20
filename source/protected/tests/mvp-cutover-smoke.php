<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ob_start(); define('IS_IN_TESTS',true); define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
class MoveTestUser extends CWebUser { public static $current=1; public function init() {} public function getId() { return self::$current; } public function getIsGuest() { return self::$current===null; } }
class MoveTestRequest extends CHttpRequest { public $method='GET'; public function getRequestType() { return $this->method; } public function getIsPostRequest() { return $this->method==='POST'; } }
class MoveRedirect extends Exception { public $target; public function __construct($url) { $this->target=$url; } }
$root=dirname(__DIR__,2); $_SERVER['SCRIPT_FILENAME']=$root.'/index.php'; $_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['HTTP_HOST']='crm.test.local'; $_SERVER['REQUEST_URI']='/'; $_SERVER['REQUEST_METHOD']='GET';
$config=require __DIR__.'/../config/test.php'; $config['components']['user']=array('class'=>'MoveTestUser'); $config['components']['request']=array('class'=>'MoveTestRequest','enableCsrfValidation'=>true);
$app=Yii::createWebApplication($config); $db=$app->db; $db->active=true;
if ($db->createCommand('SELECT DATABASE()')->queryScalar()!=='sale_report_mvp_test_cutover' || $db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1' || $db->createCommand('SELECT COUNT(*) FROM crm_account')->queryScalar() || $db->createCommand('SELECT COUNT(*) FROM crm_transfer_batch')->queryScalar()) { throw new Exception('Dedicated empty synthetic test_cutover schema required.'); }
Yii::import('application.controllers.*'); class MoveTestController extends CustomermoveController { public function redirect($url,$terminate=true,$statusCode=302) { throw new MoveRedirect($url); } }
class MoveContactController extends ContactController { public function redirect($url,$terminate=true,$statusCode=302) { throw new MoveRedirect($url); } }
require_once __DIR__.'/support/TemporaryTableFixture.php';
$checks=0; function ck($ok,$m) { global $checks; if (!$ok) { throw new Exception($m); } $checks++; }
function rejected($status,$fn,$m) { try { $fn(); } catch (CHttpException $e) { ck($e->statusCode===$status,$m.' got '.$e->statusCode.': '.$e->getMessage()); return; } throw new Exception($m.' accepted'); }
function mk($class,$values) { $m=new $class; $m->setAttributes($values,false); CustomerWriteSupport::save($m); return $m; }
function key8() { return bin2hex(random_bytes(16)); }
function csv8(array $headers,array $rows) { $s=fopen('php://temp','w+'); fputcsv($s,$headers); foreach ($rows as $r) { fputcsv($s,$r); } rewind($s); return $s; }
function preview8(array $h,array $rows,$hotel) { $s=csv8($h,$rows); try { return CustomerTransferService::previewCsv($s,$hotel); } finally { fclose($s); } }
function map8($id,$row,$decision) { $v=CustomerTransferService::view($id); return CustomerTransferService::map($id,$row,$v['rows'][$row-1]['lock_version'],$decision); }
function approve8($id) { $v=CustomerTransferService::view($id); return CustomerTransferService::approve($id,$v['batch']['lock_version']); }
function legacy8($type,$id,$aid,$pid,$hid,$owner) {
    $batch=CustomerImportService::preview($type,array($id));
    CustomerImportService::map($batch,1,1,array('mode'=>'map','reason'=>'WP08 final source mapping reviewed','account_kind'=>'link','account_id'=>$aid,'contact_kind'=>'link','contact_person_id'=>$pid,'hotels'=>array(array('hotel_id'=>$hid,'owner_user_id'=>$owner))));
    $v=CustomerImportService::view($batch); CustomerImportService::approve($batch,$v['batch']['lock_version']);
    ck(CustomerImportService::commit($batch)['consistent'],'WP03 mapping participates in final cutover');
}
function http8($controller,$action,$get=array(),$post=array(),$method='GET',$csrf=true) {
    $_GET=$get; $_POST=$post; Yii::app()->request->method=$method;
    if ($method==='POST' && $csrf) { $_POST[Yii::app()->request->csrfTokenName]=Yii::app()->request->csrfToken; }
    $c=$controller==='contact'?new MoveContactController('contact'):new MoveTestController('customermove'); Yii::app()->controller=$c; $c->init(); ob_start();
    try { $c->run($action); return ob_get_contents(); } catch (MoveRedirect $e) { return $e->target; } finally { ob_end_clean(); }
}
$source=array(); foreach (array('users','roles','contact') as $table) { $rows=$db->createCommand('SELECT * FROM '.$table)->queryAll(); $source[$table]=$rows; $ddl=$db->createCommand('SHOW CREATE TABLE '.$table)->queryRow(); $db->createCommand(isolatedTemporaryTableDdl($ddl['Create Table']))->execute(); foreach ($rows as $r) { $db->createCommand()->insert($table,$r); } }
$reportsBefore=$db->createCommand('SELECT * FROM reports')->queryAll(); $tx=$db->beginTransaction();
try {
    $db->createCommand()->delete('contact'); $db->createCommand()->delete('reports');
    $h=mk('Hotel',array('code'=>'WP08-H1','name'=>'WP08 Hotel')); $hidden=mk('Hotel',array('code'=>'WP08-H2','name'=>'WP08 Hidden'));
    $actors=array(); foreach (array('manager'=>'hotel','sales'=>'own','hidden'=>'hotel') as $name=>$scope) {
        $u=new Users('create'); $u->attributes=array('username'=>'wp08_'.$name,'fullname'=>'Synthetic '.$name,'password'=>'fake','roles'=>'wp08_'.$name,'status'=>'A','last_login_date'=>'2026-11-20 00:00:00'); ck($u->save(),'User fixture'); $actors[$name]=(int)$u->id;
        $rights=ActionAccess::catalog(); unset($rights['customermove']); $rights['customermove']=array('admin','view','preview','map','approve','commit','reconcile','export');
        $db->createCommand()->insert('roles',array('role_name'=>'wp08_'.$name,'access_rights'=>CJSON::encode($rights),'remark'=>'Synthetic','added_date'=>'2026-11-20 00:00:00','updated_date'=>'2026-11-20 00:00:00','updated_by'=>1));
        mk('UserHotelAccess',array('user_id'=>$u->id,'hotel_id'=>$name==='hidden'?$hidden->id:$h->id,'scope_level'=>$scope));
    }
    ck(CutoverService::state()['stage']==='preparing','Default gate');
    $closed=CutoverService::transition('frozen',1,'Synthetic freeze before first write'); ck($closed['stage']==='frozen','Freeze empty system');
    rejected(503,function()use($h){ AccountService::create(array('name'=>'Blocked'),$h->id,1,null,array(),key8()); },'Freeze blocks account');
    rejected(503,function(){ TaskService::authorize('create'); },'Freeze blocks task'); rejected(503,function(){ ReportService::authorize('create'); },'Freeze blocks report');
    rejected(410,function(){ CutoverService::legacyWrite(); },'Freeze blocks old writes');
    CutoverService::transition('preparing',$closed['lock_version'],'Synthetic rollback before any writes'); ck(!CutoverService::legacyBlocked(),'Pre-write rollback can reopen legacy');
    $a=AccountService::create(array('name'=>"=Unsafe,\n<b>Company</b> 😀",'phone'=>'+84900000000'),$h->id,$actors['sales'],array('first_name'=>'Lan','notes'=>"'quoted"),array('VN'),key8()); $aid=$a['account_id']; $pid=$a['contact_id'];
    $foreign=AccountService::create(array('name'=>'Hidden Account'),$hidden->id,$actors['hidden'],null,array(),key8());
    MoveTestUser::$current=$actors['sales'];
    $s=fopen('php://temp','w+'); ck(CustomerTransferCsv::export($s,$h->id)===1,'Own export count'); rewind($s); $text=stream_get_contents($s); ck(strpos($text,'Hidden Account')===false && strpos($text,"'=Unsafe")!==false && strpos($text,"'+849")!==false,'Scope and CSV formula escape'); rewind($s); $parsed=CustomerTransferCsv::read($s); fclose($s);
    ck($parsed['rows'][0]['account']['name']==="=Unsafe,\n<b>Company</b> 😀" && $parsed['rows'][0]['contact']['notes']==="'quoted",'CSV lossless quoted multiline/Unicode/formula'); ck($parsed['rows'][0]['markets']===array('VN'),'CSV markets');
    rejected(403,function()use($h){ preview8(array('account_name'),array(array('Own cannot import')),$h->id); },'Own cannot bulk import');
    rejected(404,function()use($hidden){ $s=fopen('php://temp','w+'); try { CustomerTransferCsv::export($s,$hidden->id); } finally { fclose($s); } },'Hidden Hotel export');
    MoveTestUser::$current=$actors['manager'];
    rejected(403,function(){ CutoverService::readiness(); },'Hotel cannot cutover'); rejected(403,function(){ CustomerTransferService::previewSchedules(array(1)); },'Hotel cannot browse raw legacy schedules');
    rejected(404,function()use($h,$foreign){ preview8(array('account_name','account_id'),array(array('Forged',$foreign['account_id'])),$h->id); },'CSV hidden Account ID');
    rejected(400,function()use($h){ preview8(array('account_name','account_name'),array(array('A','B')),$h->id); },'Duplicate headers');
    rejected(400,function()use($h){ preview8(array('account_name','raw_json'),array(array('A','secret')),$h->id); },'Unsupported raw fields');
    rejected(400,function()use($h){ preview8(array('account_name','hotel_id'),array(array('A','-1')),$h->id); },'Invalid Hotel in file');
    $csv=preview8(array('account_name','contact_first_name','account_markets'),array(array('CSV New','A','["VN","US"]'),array('CSV New','B','[]')),$h->id);
    ck(preview8(array('account_name','contact_first_name','account_markets'),array(array('CSV New','A','["VN","US"]'),array('CSV New','B','[]')),$h->id)===$csv,'Same file replay uses same batch');
    rejected(409,function()use($csv){ CustomerTransferService::commit($csv); },'Unapproved batch blocked');
    rejected(409,function()use($csv){ approve8($csv); },'Undecided rows block approval');
    $d=array('mode'=>'create','contact_mode'=>'create','owner_user_id'=>$actors['sales'],'reason'=>'Manager reviewed duplicate candidates'); map8($csv,1,$d); map8($csv,2,$d); approve8($csv);
    $result=CustomerTransferService::commit($csv); ck($result['counts']===array('created'=>1,'error'=>1),'Duplicate account requires manual review, first row durable within parent');
    $v=CustomerTransferService::view($csv); $new=json_decode($v['rows'][0]['result_json'],true); $nid=$new['account_id'];
    ck(Account::model()->findByPk($nid) && ContactPerson::model()->count('account_id=:id',array(':id'=>$nid))==1,'CSV account/contact atomic');
    $d=array('mode'=>'link','account_id'=>$nid,'account_version'=>Account::model()->findByPk($nid)->lock_version,'contact_mode'=>'create','reason'=>'Second person belongs to first account'); map8($csv,2,$d);
    rejected(409,function()use($csv){ CustomerTransferService::commit($csv); },'Remap invalidates approval'); approve8($csv); $result=CustomerTransferService::commit($csv);
    ck($result['status']==='completed' && $result['consistent'] && $result['counts']['created']===2,'Remapped duplicate links same Account');
    $before=array(Account::model()->count(),ContactPerson::model()->count(),AuditEvent::model()->count()); CustomerTransferService::commit($csv); ck($before===array(Account::model()->count(),ContactPerson::model()->count(),AuditEvent::model()->count()),'Completed commit replay no duplicates/audits');
    $s=fopen('php://temp','w+'); CustomerTransferCsv::export($s,$h->id,$actors['sales']); rewind($s); $round=CustomerTransferService::previewCsv($s,$h->id); fclose($s); $v=CustomerTransferService::view($round);
    foreach ($v['rows'] as $r) { $p=json_decode($r['payload_json'],true); $x=$p['ids']; map8($round,$r['row_no'],array('mode'=>'link','account_id'=>$x['account_id'],'account_version'=>Account::model()->findByPk($x['account_id'])->lock_version,'contact_mode'=>empty($x['contact_id'])?'none':'link','contact_id'=>$x['contact_id'],'contact_version'=>empty($x['contact_id'])?'':ContactPerson::model()->findByPk($x['contact_id'])->lock_version,'reason'=>'Roundtrip existing identity')); }
    approve8($round); $res=CustomerTransferService::commit($round); ck($res['status']==='completed' && $res['consistent'],'Canonical export/import roundtrip');
    $stale=preview8(array('account_name'),array(array('Stale preview')),$h->id); $v=map8($stale,1,array('mode'=>'skip','reason'=>'No business approval')); rejected(409,function()use($stale){ CustomerTransferService::map($stale,1,1,array('mode'=>'skip','reason'=>'stale')); },'Stale mapping');
    approve8($stale); CustomerTransferService::commit($stale);
    ck(strpos(http8('customermove','view',array('id'=>$round)),'&lt;b&gt;Company&lt;')!==false,'Preview HTML escapes source text');
    rejected(405,function()use($round){ http8('customermove','commit',array('id'=>$round)); },'GET commit blocked');
    rejected(400,function()use($round){ http8('customermove','approve',array('id'=>$round),array('version'=>1),'POST',false); },'CSRF required');
    MoveTestUser::$current=$actors['hidden']; rejected(404,function()use($round){ CustomerTransferService::view($round); },'Other Hotel cannot read batch');
    MoveTestUser::$current=1;
    // A mapped legacy report retains its old fields exactly; done=Yes never closes a Task.
    $oldContact=$source['contact'][0]; unset($oldContact['id']); $oldContact['company']='WP08 retained source';
    $db->createCommand()->insert('contact',$oldContact); $cid=(int)$db->lastInsertID; legacy8('contact',$cid,$aid,$pid,$h->id,$actors['sales']);
    $raw=$reportsBefore[0]; unset($raw['id']); $raw['account_id']=null; $raw['hotel_id']=null; $raw['contact_person_id']=null; $raw['identity_state']='legacy_only'; $raw['done']='Yes'; $raw['ent']='2035-01-08'; $raw['sales']='2020-01-01'; $raw['site_ins']=null;
    $db->createCommand()->insert('reports',$raw); $rid=(int)$db->lastInsertID; $unmapped=CustomerTransferService::previewSchedules(array($rid)); legacy8('report',$rid,$aid,$pid,$h->id,$actors['sales']);
    foreach (array(1,2,3) as $n) { map8($unmapped,$n,array('mode'=>'skip','reason'=>'Superseded preview before WP03 mapping; review again after mapping')); } approve8($unmapped); $old=CustomerTransferService::commit($unmapped);
    ck($old['status']==='completed' && $old['consistent'] && count($old['warnings'])===3,'Abandoned pre-mapping preview can be closed with explicit skip, retains drift warnings');
    ck(in_array('unreviewed_schedules',CutoverService::readiness()['issues'],true),'Skipped pre-mapping snapshot does not count as current schedule review');
    $sourceHash=LegacyImportSource::hash($db->createCommand('SELECT * FROM reports WHERE id=:id')->queryRow(true,array(':id'=>$rid)),'report');
    $schedule=CustomerTransferService::previewSchedules(array($rid)); $v=CustomerTransferService::view($schedule); ck(count($v['rows'])===3,'Three independent legacy date fields');
    $ah=AccountHotel::model()->findByPk(array('account_id'=>$aid,'hotel_id'=>$h->id)); $sd=array('mode'=>'create','owner_user_id'=>$actors['sales'],'assignment_version'=>$ah->lock_version,'confirmed'=>1,'reason'=>'Manager confirmed future visit still effective');
    rejected(409,function()use($schedule,$sd){ $x=$sd; $x['confirmed']=0; map8($schedule,1,$x); },'Unconfirmed future schedule');
    rejected(409,function()use($schedule,$sd){ map8($schedule,2,$sd); },'Past dates review-only'); rejected(409,function()use($schedule,$sd){ map8($schedule,3,$sd); },'Empty dates review-only');
    rejected(409,function()use($schedule,$sd){ $x=$sd; $x['owner_user_id']=1; map8($schedule,1,$x); },'No added_by owner guess');
    map8($schedule,1,$sd); foreach (array(2,3) as $n) { map8($schedule,$n,array('mode'=>'skip','reason'=>'Historical/empty; reviewed, no active task')); } approve8($schedule);
    $gate=CutoverService::state(); $gate=CutoverService::transition('frozen',$gate['lock_version'],'Synthetic final freeze');
    rejected(409,function()use($gate){ CutoverService::transition('preparing',$gate['lock_version'],'Unsafe rollback after writes'); },'Never reopen legacy after MVP writes');
    $db->createCommand()->update('crm_account_hotel',array('lock_version'=>(int)$ah->lock_version+1),'account_id=:a AND hotel_id=:h',array(':a'=>$aid,':h'=>$h->id));
    $res=CustomerTransferService::commit($schedule); ck(isset($res['counts']['error']) && !Task::model()->exists('report_id=:id',array(':id'=>$rid)),'Assignment changed after approval prevents Task creation');
    $sd['assignment_version']=(int)$ah->lock_version+1; map8($schedule,1,$sd); approve8($schedule);
    $notificationCount=Notification::model()->count(); $res=CustomerTransferService::commit($schedule); ck($res['status']==='completed' && $res['consistent'] && $res['counts']['created']===1 && $res['counts']['skipped']===2,'Reviewed admin backfill allowed during freeze');
    $t=Task::model()->find('legacy_key=:key',array(':key'=>'report:'.$rid.':field:ent')); ck($t && $t->status==='open' && $t->due_mode==='date' && $t->due_date==='2035-01-08' && (int)$t->assignee_user_id===$actors['sales'],'done Yes ignored, reviewed date-only owner task');
    ck((int)TaskReminder::model()->count('task_id=:id',array(':id'=>$t->id))===2 && Notification::model()->count()===$notificationCount,'Backfill plans reminders without dispatch');
    ck($sourceHash===LegacyImportSource::hash($db->createCommand('SELECT * FROM reports WHERE id=:id')->queryRow(true,array(':id'=>$rid)),'report'),'Legacy report preserved');
    $before=Task::model()->count(); CustomerTransferService::commit($schedule); ck(Task::model()->count()===$before,'Legacy key / commit replay');
    $db->createCommand()->update('users',array('roles'=>'superadmin'),'id=:id',array(':id'=>$actors['manager'])); MoveTestUser::$current=$actors['manager'];
    $second=CustomerTransferService::previewSchedules(array($rid)); ck($second!==$schedule,'Different reviewer gets separate immutable batch');
    map8($second,1,$sd); foreach (array(2,3) as $n) { map8($second,$n,array('mode'=>'skip','reason'=>'Same reviewed historical source')); } approve8($second); $res=CustomerTransferService::commit($second);
    ck($res['status']==='completed' && $res['counts']['linked']===1 && Task::model()->count()===$before,'Cross-batch legacy key links verified provenance without another Task');
    MoveTestUser::$current=1;
    $quarantined=$source['contact'][0]; unset($quarantined['id']); $quarantined['company']='Uncertain source retained in archive';
    $db->createCommand()->insert('contact',$quarantined); $qid=(int)$db->lastInsertID; $qb=CustomerImportService::preview('contact',array($qid));
    CustomerImportService::map($qb,1,1,array('mode'=>'skip','reason'=>'Data owner quarantines ambiguous source, retain archive')); $qv=CustomerImportService::view($qb); CustomerImportService::approve($qb,$qv['batch']['lock_version']); CustomerImportService::commit($qb);
    ck(CutoverService::readiness()['counts']['quarantined_contacts']===1,'Reviewed unchanged skipped source is quarantined without losing raw data');
    $qraw=$reportsBefore[0]; unset($qraw['id']); $qraw['company_name']='Uncertain legacy report'; $qraw['ent']=null; $qraw['sales']=null; $qraw['site_ins']=null;
    $db->createCommand()->insert('reports',$qraw); $qrid=(int)$db->lastInsertID; $qb=CustomerImportService::preview('report',array($qrid));
    CustomerImportService::map($qb,1,1,array('mode'=>'skip','reason'=>'Ambiguous report quarantined, no effective schedule')); $qv=CustomerImportService::view($qb); CustomerImportService::approve($qb,$qv['batch']['lock_version']); CustomerImportService::commit($qb);
    ck(CutoverService::readiness()['counts']['quarantined_reports']===1,'Reviewed report quarantine retains source without inventing Account/Hotel');
    $db->createCommand()->update('reports',array('company_name'=>'Changed after quarantine'),'id=:id',array(':id'=>$qrid)); ck(!CutoverService::readiness()['ready'],'Stale quarantine fingerprint cannot bypass final review');
    $db->createCommand()->update('reports',array('company_name'=>$qraw['company_name']),'id=:id',array(':id'=>$qrid));
    $check=CutoverService::readiness(); ck($check['ready'],'Final readiness: '.CJSON::encode($check));
    $gate=CutoverService::transition('canonical',$gate['lock_version'],'Synthetic canonical cutover'); ck($gate['canonical_latched']==1,'Durable canonical latch');
    $flags=Yii::app()->params['mvp']; $off=$flags; foreach (array('customerWrite','reportWrite','taskWrite') as $f) { $off[$f]=false; } Yii::app()->params['mvp']=$off;
    rejected(410,function(){ CustomerPolicy::requireLegacyWrite('contact','create'); },'Flags off cannot reopen Contact'); rejected(410,function(){ CustomerPolicy::requireLegacyWrite('report','create'); },'Flags off cannot reopen Report');
    rejected(410,function()use($rid){ CustomerTransferService::previewSchedules(array($rid)); },'Legacy backfill closes after canonical');
    MoveTestUser::$current=$actors['sales']; ck(http8('contact','view',array('id'=>$cid))==array('/account/view','id'=>$aid),'Old mapped view redirects within Own scope');
    MoveTestUser::$current=$actors['hidden']; rejected(404,function()use($cid){ http8('contact','view',array('id'=>$cid)); },'Old redirected view hides cross-Hotel source');
    MoveTestUser::$current=1;
    rejected(410,function(){ http8('contact','create'); },'Old Contact create route closed'); rejected(410,function(){ http8('contact','import'); },'Old Contact CSV import route closed');
    rejected(410,function()use($cid){ http8('contact','update',array('id'=>$cid)); },'Old Contact update GET closed'); rejected(410,function()use($cid){ http8('contact','delete',array('id'=>$cid),array(),'POST'); },'Old Contact delete closed');
    ck(http8('contact','admin')===array('/account/admin'),'Old list redirects canonical'); rejected(410,function()use($aid){ http8('contact','report'); },'Old export closed');
    Yii::app()->params['mvp']=$flags; $gate=CutoverService::transition('closed',$gate['lock_version'],'Synthetic incident rollback: preserve reads and history');
    rejected(503,function(){ TaskService::authorize('create'); },'Closed blocks task writes'); rejected(503,function(){ ReportService::authorize('create'); },'Closed blocks report writes'); rejected(410,function(){ CutoverService::legacyWrite(); },'Closed keeps legacy blocked');
    ck(CustomerTransferService::reconcile($schedule)['consistent'] && AccountReadService::detail($aid,'contacts',1),'Closed keeps read/reconcile');
    $gate=CutoverService::transition('canonical',$gate['lock_version'],'Synthetic recovery after checks'); ck($gate['stage']==='canonical','Recovery returns canonical only');
    $db->createCommand()->update('contact',array('company'=>'Synthetic drift'),'id=:id',array(':id'=>$cid)); ck(in_array('LEGACY_SOURCE_DRIFT',CutoverService::readiness()['issues'],true),'Final gate detects legacy Contact drift');
    $db->createCommand()->update('contact',array('company'=>$oldContact['company']),'id=:id',array(':id'=>$cid));
    $db->createCommand()->update('reports',array('ent'=>'2036-01-01'),'id=:id',array(':id'=>$rid)); ck(!CustomerTransferService::reconcile($schedule)['consistent'],'Reconciliation detects source drift'); ck(!CutoverService::readiness()['ready'],'Drift blocks readiness');
} finally {
    MoveTestUser::$current=1; if ($tx->active) { $tx->rollback(); }
    foreach (array('contact','roles','users') as $table) { $db->createCommand('DROP TEMPORARY TABLE '.$table)->execute(); ck($db->createCommand('SELECT * FROM '.$table)->queryAll()===$source[$table],'Original '.$table.' preserved'); }
    ck($db->createCommand('SELECT * FROM reports')->queryAll()===$reportsBefore,'Original reports preserved');
}
ck(CutoverService::state()['stage']==='preparing' && !$db->createCommand('SELECT COUNT(*) FROM crm_transfer_batch')->queryScalar() && !$db->createCommand('SELECT COUNT(*) FROM crm_task')->queryScalar(),'All run-owned data rolled back');
ob_end_clean(); echo "PASS: $checks WP08 cutover/CSV/backfill/scope/HTTP/rollback assertions; only synthetic DB changed, fixtures restored.\n";
