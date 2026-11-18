<?php

if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ob_start(); define('IS_IN_TESTS',true); define('YII_ENABLE_EXCEPTION_HANDLER',false); define('YII_ENABLE_ERROR_HANDLER',false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: __DIR__.'/../../../../yii/yii.php');
class ImportUser extends CWebUser
{
    public static $current=1;
    public function init() {}
    public function getId() { return self::$current; }
    public function getIsGuest() { return self::$current===null; }
}
class ImportRequest extends CHttpRequest
{
    public $method='GET';
    public function getRequestType() { return $this->method; }
    public function getIsPostRequest() { return $this->method==='POST'; }
}
class ImportRedirect extends Exception {}
$root=dirname(__DIR__,2); $_SERVER['SCRIPT_FILENAME']=$root.'/index.php'; $_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['HTTP_HOST']='crm.test.local'; $_SERVER['REQUEST_URI']='/'; $_SERVER['REQUEST_METHOD']='GET';
$config=require __DIR__.'/../config/test.php'; $config['components']['user']=array('class'=>'ImportUser'); $config['components']['request']=array('class'=>'ImportRequest','enableCsrfValidation'=>true);
$app=Yii::createWebApplication($config); $db=$app->db; $db->setActive(true);
if ($db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1') { throw new Exception('Synthetic isolated fixture required.'); }
Yii::import('application.controllers.*');
class ImportControllerTest extends CustomerimportController
{
    public function redirect($url,$terminate=true,$statusCode=302) { throw new ImportRedirect; }
}
require_once __DIR__.'/support/TemporaryTableFixture.php';
$checks=0;
function impCheck($value,$message) { global $checks; if (!$value) { throw new Exception($message); } $checks++; }
function impStatus($status,$fn,$message)
{
    try { $fn(); } catch (CHttpException $e) { impCheck($e->statusCode===$status,$message.' (received '.$e->statusCode.')'); return; } throw new Exception($message.' (not rejected)');
}
function impRecord($class,$values)
{
    $m=new $class; $m->setAuditActorId(1); $m->setAttributes($values,false); impCheck($m->save(),$class.': '.CJSON::encode($m->errors)); return $m;
}
function impRun($action,$get=array(),$post=array(),$method='GET',$csrf=true)
{
    $_GET=$get; $_POST=$post; Yii::app()->request->method=$method;
    if ($method==='POST' && $csrf) { $_POST[Yii::app()->request->csrfTokenName]=Yii::app()->request->csrfToken; }
    $c=new ImportControllerTest('customerimport'); Yii::app()->controller=$c; $c->init(); ob_start();
    try { $c->run($action); return ob_get_contents(); } catch (ImportRedirect $e) { return 'redirect'; } finally { ob_end_clean(); }
}
function impSource($type,$overrides)
{
    global $templates,$db;
    $row=$templates[$type]; unset($row['id']); foreach ($overrides as $key=>$value) { $row[$key]=$value; }
    $db->createCommand()->insert(LegacyImportSource::table($type),$row); return (int)$db->lastInsertID;
}
function impMap($id,$row,$decision)
{
    $data=CustomerImportService::view($id); $version=$data['rows'][$row-1]['lock_version'];
    CustomerImportService::map($id,$row,$version,$decision);
}
function impApprove($id)
{
    $data=CustomerImportService::view($id); return CustomerImportService::approve($id,$data['batch']['lock_version']);
}
function impDecision($hotelId,$ownerId,$changes=array())
{
    return array_merge(array('mode'=>'map','reason'=>'Verified synthetic legacy row','account_kind'=>'create','contact_kind'=>'create','hotels'=>array(array('hotel_id'=>$hotelId,'owner_user_id'=>$ownerId))),$changes);
}
$baseline=array();
foreach (array('users','roles') as $table) {
    $rows=$db->createCommand('SELECT * FROM '.$table)->queryAll(); $baseline[$table]=$rows;
    $ddl=$db->createCommand('SHOW CREATE TABLE '.$table)->queryRow(); $db->createCommand(isolatedTemporaryTableDdl($ddl['Create Table']))->execute();
    foreach ($rows as $row) { $db->createCommand()->insert($table,$row); }
}
$templates=array('contact'=>$db->createCommand('SELECT * FROM contact ORDER BY id LIMIT 1')->queryRow(),'report'=>$db->createCommand('SELECT * FROM reports ORDER BY id LIMIT 1')->queryRow());
$sourceBefore=array('contact'=>$db->createCommand('SELECT * FROM contact ORDER BY id')->queryAll(),'reports'=>$db->createCommand('SELECT * FROM reports ORDER BY id')->queryAll());
$tx=$db->beginTransaction();
try {
    foreach (array('import_owner'=>'users','import_manager'=>'admin') as $username=>$role) {
        $u=new Users('create'); $u->attributes=array('username'=>$username,'fullname'=>$username,'password'=>'fake-password','roles'=>$role,'status'=>'A','last_login_date'=>'2026-11-18 00:00:00'); impCheck($u->save(),'Import fixture user failed.'); $actors[$username]=$u;
    }
    $owner=$actors['import_owner']; $manager=$actors['import_manager'];
    $h=impRecord('Hotel',array('code'=>'IMPORT-H1','name'=>'Import Hotel A')); $h2=impRecord('Hotel',array('code'=>'IMPORT-H2','name'=>'Import Hotel B'));
    impRecord('UserHotelAccess',array('user_id'=>$owner->id,'hotel_id'=>$h->id,'scope_level'=>'own'));
    impRecord('UserHotelAccess',array('user_id'=>$manager->id,'hotel_id'=>$h->id,'scope_level'=>'hotel'));
    $defaults=array('company'=>'  Synthetic Import Corp  ','account_type'=>'CORPORATE','hotel_produced'=>'Import Hotel A','managed_by'=>(string)$owner->id,
        'first_name'=>'Alice','last_name'=>'Nguyen','email'=>'shared@example.test','tel'=>'0123456789','dob'=>'','market'=>'EU,VN','comment'=>'Keep source comment','rate_code'=>'LEGACY-RATE','validity_from'=>'2026-01-01','validity_to'=>'2026-12-31');
    $s1=impSource('contact',$defaults);
    $s2=impSource('contact',array_merge($defaults,array('company'=>'synthetic import corp','first_name'=>'Bob')));
    $s3=impSource('contact',$defaults);
    $aCount=(int)Account::model()->count(); $pCount=(int)ContactPerson::model()->count(); $linkCount=(int)LegacyLink::model()->count();
    $b=CustomerImportService::preview('contact',array($s3,$s1,$s2,$s1));
    impCheck(CustomerImportService::preview('contact',array($s2,$s1,$s3))===$b,'Preview must be stable across order/duplicate source IDs.');
    impCheck((int)Account::model()->count()===$aCount && (int)ContactPerson::model()->count()===$pCount && (int)LegacyLink::model()->count()===$linkCount,'Preview changed canonical data.');
    $v=CustomerImportService::view($b);
    impCheck($v['counts']['total']===3 && $v['counts']['balanced'],'Preview reconciliation failed.');
    impCheck(count($v['rows'][0]['proposed']['same_name_rows'])===3,'Normalized duplicate source names not grouped.');
    impCheck(isset($v['rows'][0]['proposed']['owner_candidates'][$owner->id]),'Legacy owner ID suggestion missing.');
    impStatus(409,function()use($b){CustomerImportService::commit($b);},'Unapproved import was accepted.');
    impStatus(409,function()use($b){impApprove($b);},'Unreviewed rows were approved.');
    impCheck(CustomerImportService::view($b)['rows'][0]['proposed']['market_suggestions']===array('EU','VN'),'Market suggestions missing.');
    impMap($b,1,impDecision($h->id,$owner->id,array('markets'=>array('EU','VN','EU'))));
    impMap($b,2,impDecision($h->id,$owner->id,array('account_kind'=>'row','account_row_no'=>1)));
    impMap($b,3,impDecision($h->id,$owner->id,array('account_kind'=>'row','account_row_no'=>1)));
    impStatus(409,function()use($b,$h,$owner){CustomerImportService::map($b,1,1,impDecision($h->id,$owner->id));},'Stale row mapping accepted.');
    impStatus(400,function()use($b,$h,$owner){$v=CustomerImportService::view($b);CustomerImportService::map($b,1,$v['rows'][0]['lock_version'],array_merge(impDecision($h->id,$owner->id),array('created_by'=>999)));},'Injected actor accepted in mapping.');
    impApprove($b); $first=CustomerImportService::commit($b,1);
    impCheck($first['batch']['status']==='running' && $first['counts']['created']===1 && $first['counts']['needs_review']===2,'Bounded commit should be resumable.');
    $r=CustomerImportService::commit($b);
    impCheck($r['counts']['created']===1 && $r['counts']['linked']===1 && $r['counts']['error']===1,'Per-row duplicate handling failed.');
    $v=CustomerImportService::view($b); impCheck($v['rows'][2]['error_code']==='DUPLICATE_CONTACT_REVIEW','Identical contact must require review.');
    $aid=(int)$v['rows'][0]['result_account_id']; $pid=(int)$v['rows'][0]['result_contact_id'];
    impCheck((int)Account::model()->count()===$aCount+1 && (int)ContactPerson::model()->count()===$pCount+2,'Shared email should allow two named contacts under one Account.');
    impMap($b,3,impDecision($h->id,$owner->id,array('account_kind'=>'row','account_row_no'=>1,'contact_kind'=>'row','contact_row_no'=>1)));
    impStatus(409,function()use($b){CustomerImportService::commit($b);},'Mapping edits must invalidate batch approval.');
    impApprove($b); $r=CustomerImportService::commit($b);
    impCheck($r['batch']['status']==='completed' && $r['counts']['linked']===2 && $r['consistent'],'Retry did not finish cleanly.');
    impCheck((int)AccountMarket::model()->count('account_id=:id',array(':id'=>$aid))===2,'Reviewed markets were lost or duplicated.');
    impCheck($r['objects']['accounts_created']===1 && $r['objects']['contacts_created']===2,'Object reconciliation double-counted retries.');
    $events=(int)AuditEvent::model()->count(); CustomerImportService::commit($b);
    impCheck((int)AuditEvent::model()->count()===$events,'Repeated commit wrote duplicate events.');
    impStatus(409,function()use($b,$h,$owner){impMap($b,1,impDecision($h->id,$owner->id));},'Committed rows must be immutable.');
    $original=$db->createCommand('SELECT * FROM contact WHERE id=:id')->queryRow(true,array(':id'=>$s1));
    impCheck($original['company']===$defaults['company'] && $original['rate_code']==='LEGACY-RATE','Legacy Contact fields changed.');
    impCheck(CustomerImportService::preview('contact',array($s1,$s2,$s3))===$b,'Completed preview did not reuse original snapshot.');

    $one=CustomerImportService::preview('contact',array($s1));
    impCheck(count(CustomerImportService::view($one)['rows'][0]['proposed']['contact_candidates'])>=2,'Shared email candidates were not shown for review.'); impMap($one,1,array('mode'=>'existing','reason'=>'Keep verified earlier import')); impApprove($one);
    $r=CustomerImportService::commit($one); impCheck($r['counts']['linked']===1 && $r['objects']['accounts_created']===0,'Cross-batch source idempotency failed.');
    $bad=impSource('contact',array_merge($defaults,array('company'=>'Atomic rollback customer','email'=>'bad-address')));
    $badBatch=CustomerImportService::preview('contact',array($bad)); impMap($badBatch,1,impDecision($h->id,$owner->id)); impApprove($badBatch);
    $before=(int)Account::model()->count(); $audits=(int)AuditEvent::model()->count("entity_type='account' AND event_type='import_created'");
    $r=CustomerImportService::commit($badBatch);
    impCheck($r['counts']['error']===1 && (int)Account::model()->count()===$before,'Failed contact write left an orphan Account.');
    impCheck((int)AuditEvent::model()->count("entity_type='account' AND event_type='import_created'")===$audits,'Failed row leaked creation audit.');
    impCheck(!LegacyLink::model()->findByPk(array('source_type'=>'contact','source_id'=>(string)$bad)),'Failed row leaked source link.');
    impMap($badBatch,1,impDecision($h->id,$owner->id,array('contact_fields'=>array('email'=>'corrected@example.test')))); impApprove($badBatch);
    impCheck(CustomerImportService::commit($badBatch)['counts']['created']===1,'Corrected row could not be retried.');
    $blank=impSource('contact',array_merge($defaults,array('company'=>'Company without named contact','first_name'=>'','last_name'=>'')));
    $blankBatch=CustomerImportService::preview('contact',array($blank)); impMap($blankBatch,1,impDecision($h->id,null,array('contact_kind'=>'none'))); impApprove($blankBatch);
    CustomerImportService::commit($blankBatch); $blankRow=CustomerImportService::view($blankBatch)['rows'][0];
    impCheck($blankRow['result_contact_id']===null,'Blank person should not create a contact.');
    impCheck(AccountHotel::model()->findByPk(array('account_id'=>$blankRow['result_account_id'],'hotel_id'=>$h->id))->owner_user_id===null,'Unresolved owner must stay unassigned.');
    $skip=impSource('contact',array_merge($defaults,array('company'=>'Skip <script>unsafe</script>')));
    $skipBatch=CustomerImportService::preview('contact',array($skip)); impMap($skipBatch,1,array('mode'=>'skip','reason'=>'Outside customer scope')); impApprove($skipBatch);
    $r=CustomerImportService::commit($skipBatch); impCheck($r['counts']['skipped']===1 && $r['counts']['balanced'],'Skipped row reconciliation failed.');
    impCheck(!LegacyLink::model()->findByPk(array('source_type'=>'contact','source_id'=>(string)$skip)),'Skip must not overwrite canonical mapping.');
    $skipView=impRun('view',array('id'=>$skipBatch)); impCheck(strpos($skipView,'<script>unsafe</script>')===false && strpos($skipView,'&lt;script&gt;')!==false,'Source preview HTML was not escaped.');

    $duplicate=impSource('contact',array_merge($defaults,array('first_name'=>'New person')));
    $dupBatch=CustomerImportService::preview('contact',array($duplicate)); impMap($dupBatch,1,impDecision($h->id,$owner->id)); impApprove($dupBatch);
    $r=CustomerImportService::commit($dupBatch); impCheck($r['counts']['error']===1 && CustomerImportService::view($dupBatch)['rows'][0]['error_code']==='DUPLICATE_ACCOUNT_REVIEW','New Account silently merged/duplicated by name.');
    impMap($dupBatch,1,impDecision($h->id,$owner->id,array('account_kind'=>'link','account_id'=>$aid))); impApprove($dupBatch);
    impCheck(CustomerImportService::commit($dupBatch)['counts']['linked']===1,'Explicit Account linkage failed.');
    $other=impRecord('Account',array('code'=>'IMPORT-OTHER','name'=>'Another account'));
    $otherPerson=impRecord('ContactPerson',array('account_id'=>$other->id,'first_name'=>'Wrong destination'));
    $cross=impSource('contact',array_merge($defaults,array('first_name'=>'Cross person'))); $crossBatch=CustomerImportService::preview('contact',array($cross));
    impMap($crossBatch,1,impDecision($h->id,$owner->id,array('account_kind'=>'link','account_id'=>$aid,'contact_kind'=>'link','contact_person_id'=>$otherPerson->id))); impApprove($crossBatch);
    CustomerImportService::commit($crossBatch); impCheck(CustomerImportService::view($crossBatch)['rows'][0]['error_code']==='CONTACT_ACCOUNT_MISMATCH','Contact linked to the wrong Account.');
    impMap($crossBatch,1,impDecision($h->id,$manager->id,array('account_kind'=>'link','account_id'=>$aid,'contact_kind'=>'none'))); impApprove($crossBatch);
    CustomerImportService::commit($crossBatch); impCheck(CustomerImportService::view($crossBatch)['rows'][0]['error_code']==='OWNER_OR_ASSIGNMENT_CONFLICT','Import silently reassigned ownership.');
    impCheck((int)AccountHotel::model()->findByPk(array('account_id'=>$aid,'hotel_id'=>$h->id))->owner_user_id===(int)$owner->id,'Owner changed after rejected row.');

    $stale=impSource('contact',array_merge($defaults,array('company'=>'Review source drift'))); $staleBatch=CustomerImportService::preview('contact',array($stale));
    impMap($staleBatch,1,impDecision($h->id,$owner->id)); impApprove($staleBatch);
    $db->createCommand()->update('contact',array('comment'=>'Changed since preview'),'id=:id',array(':id'=>$stale));
    CustomerImportService::commit($staleBatch); impCheck(CustomerImportService::view($staleBatch)['rows'][0]['error_code']==='SOURCE_CHANGED_SINCE_PREVIEW','Stale source was imported.');
    impCheck(CustomerImportService::view($staleBatch)['rows'][0]['raw']['comment']==='Keep source comment','Preview snapshot changed with source.');
    impCheck(CustomerImportService::preview('contact',array($stale))!==$staleBatch,'Changed source must create a new snapshot.');
    $changed=impSource('contact',array_merge($defaults,array('company'=>'Target review'))); $changedBatch=CustomerImportService::preview('contact',array($changed));
    impMap($changedBatch,1,impDecision($h->id,$owner->id,array('account_kind'=>'link','account_id'=>$aid,'contact_kind'=>'none'))); impApprove($changedBatch);
    $account=Account::model()->findByPk($aid); $account->notes='Changed after review'; impCheck($account->save(),'Target update fixture failed.');
    CustomerImportService::commit($changedBatch); impCheck(CustomerImportService::view($changedBatch)['rows'][0]['error_code']==='ACCOUNT_CHANGED_AFTER_REVIEW','Stale destination was accepted.');

    $changedPerson=impSource('contact',array_merge($defaults,array('first_name'=>'Contact review target'))); $cpBatch=CustomerImportService::preview('contact',array($changedPerson));
    impMap($cpBatch,1,impDecision($h->id,$owner->id,array('account_kind'=>'link','account_id'=>$aid,'contact_kind'=>'link','contact_person_id'=>$pid))); impApprove($cpBatch);
    $person=ContactPerson::model()->findByPk($pid); $person->notes='Edited after mapping review'; impCheck($person->save(),'Contact update fixture failed.');
    CustomerImportService::commit($cpBatch); impCheck(CustomerImportService::view($cpBatch)['rows'][0]['error_code']==='CONTACT_CHANGED_AFTER_REVIEW','Stale Contact mapping accepted.');
    $inactiveSource=impSource('contact',array_merge($defaults,array('company'=>'Owner revoked after approval'))); $inactiveBatch=CustomerImportService::preview('contact',array($inactiveSource));
    impMap($inactiveBatch,1,impDecision($h->id,$owner->id,array('contact_kind'=>'none'))); impApprove($inactiveBatch);
    $db->createCommand()->update('users',array('status'=>'I'),'id=:id',array(':id'=>$owner->id));
    CustomerImportService::commit($inactiveBatch); impCheck(CustomerImportService::view($inactiveBatch)['rows'][0]['error_code']==='OWNER_INACTIVE_OR_MISSING','Inactive owner was accepted after approval.');
    $db->createCommand()->update('users',array('status'=>'A'),'id=:id',array(':id'=>$owner->id));
    impCheck(CustomerImportService::commit($inactiveBatch)['counts']['created']===1,'Retry after owner repair did not resume.');

    $report=impSource('report',array('company_name'=>'Synthetic Import Corp','first_name'=>'Alice','last_name'=>'Nguyen','email'=>'shared@example.test','hotels'=>'Import Hotel A,Import Hotel B','done'=>'Yes','report'=>'Preserve narrative','added_by'=>$owner->id));
    $reportRaw=$db->createCommand('SELECT * FROM reports WHERE id=:id')->queryRow(true,array(':id'=>$report));
    $rb=CustomerImportService::preview('report',array($report));
    impCheck(in_array('REPORT_MULTIPLE_HOTELS',CustomerImportService::view($rb)['rows'][0]['proposed']['warnings'],true),'Multi-Hotel source was not flagged.');
    impStatus(400,function()use($rb,$h,$h2,$owner,$aid){impMap($rb,1,impDecision($h->id,$owner->id,array('account_kind'=>'link','account_id'=>$aid,'hotels'=>array(array('hotel_id'=>$h->id),array('hotel_id'=>$h2->id)))));},'Report accepted multiple destination Hotels.');
    impMap($rb,1,impDecision($h->id,$owner->id,array('account_kind'=>'link','account_id'=>$aid,'contact_kind'=>'link','contact_person_id'=>$pid))); impApprove($rb);
    $taskCount=(int)Task::model()->count(); $r=CustomerImportService::commit($rb); impCheck($r['counts']['linked']===1 && $r['consistent'],'Report link reconciliation failed.');
    $newReport=$db->createCommand('SELECT * FROM reports WHERE id=:id')->queryRow(true,array(':id'=>$report));
    impCheck(LegacyImportSource::hash($newReport,'report')===LegacyImportSource::hash($reportRaw,'report'),'Report legacy fields/audit were rewritten.');
    impCheck((int)$newReport['account_id']===$aid && (int)$newReport['contact_person_id']===$pid && (int)$newReport['hotel_id']===(int)$h->id,'Report references incorrect.');
    impCheck((int)Task::model()->count()===$taskCount,'Report import created historical reminders/tasks.');
    impCheck(CustomerImportService::preview('report',array($report))===$rb,'Report metadata update broke preview idempotency.');
    impRecord('AccountHotel',array('account_id'=>$aid,'hotel_id'=>$h2->id,'owner_user_id'=>null));
    $db->createCommand()->update('reports',array('hotel_id'=>$h2->id),'id=:id',array(':id'=>$report));
    $tampered=CustomerImportService::reconcile($rb);
    impCheck(!$tampered['consistent'] && in_array('REPORT_LINK_MISMATCH',array_column($tampered['issues'],'code'),true),'Reconcile did not detect changed Report destination.');
    $existingConflict=CustomerImportService::preview('report',array($report,(int)$templates['report']['id']));
    $conflictRows=CustomerImportService::view($existingConflict)['rows'];
    foreach ($conflictRows as $cr) { impMap($existingConflict,$cr['row_no'],array('mode'=>(int)$cr['source_id']===$report?'existing':'skip','reason'=>'Verify preserved source linkage')); }
    impApprove($existingConflict); CustomerImportService::commit($existingConflict);
    $conflictRows=CustomerImportService::view($existingConflict)['rows'];
    foreach ($conflictRows as $cr) { if ((int)$cr['source_id']===$report) { impCheck($cr['error_code']==='REPORT_LINK_CONFLICT','Existing-source mode accepted changed Hotel metadata.'); } }
    $db->createCommand()->update('reports',array('hotel_id'=>$h->id),'id=:id',array(':id'=>$report));
    impCheck(CustomerImportService::commit($existingConflict)['consistent'],'Existing report mapping could not resume after repair.');
    $identityReport=impSource('report',array('company_name'=>'Identity race','hotels'=>'Import Hotel A'));
    $identityBatch=CustomerImportService::preview('report',array($identityReport)); impMap($identityBatch,1,impDecision($h->id,$owner->id,array('account_kind'=>'link','account_id'=>$aid,'contact_kind'=>'none'))); impApprove($identityBatch);
    $db->createCommand()->update('reports',array('identity_state'=>'pending_review'),'id=:id',array(':id'=>$identityReport));
    CustomerImportService::commit($identityBatch); impCheck(CustomerImportService::view($identityBatch)['rows'][0]['error_code']==='REPORT_IDENTITY_CHANGED','Report metadata changed after review was overwritten.');

    $aliasSource=impSource('contact',array_merge($defaults,array('company'=>'Approved former company name'))); $aliasBatch=CustomerImportService::preview('contact',array($aliasSource));
    impMap($aliasBatch,1,impDecision($h->id,$owner->id,array('account_kind'=>'link','account_id'=>$aid,'contact_kind'=>'none','remember_alias'=>true))); impApprove($aliasBatch);
    impCheck(CustomerImportService::commit($aliasBatch)['counts']['linked']===1,'Reviewed alias mapping failed.');
    $aliasReport=impSource('report',array('company_name'=>'Approved former company name')); $aliasPreview=CustomerImportService::preview('report',array($aliasReport));
    $matches=CustomerImportService::view($aliasPreview)['rows'][0]['proposed']['account_candidates'];
    impCheck(count(array_filter($matches,function($a)use($aid){return (int)$a['id']===$aid && $a['reason']==='reviewed_alias';}))===1,'Reviewed alias was not suggested.');
    impStatus(409,function()use($aliasPreview){impApprove($aliasPreview);},'Alias suggestion auto-approved a merge.');
    impMap($aliasPreview,1,impDecision($h->id,null,array('account_kind'=>'link','account_id'=>$other->id,'contact_kind'=>'none','remember_alias'=>true))); impApprove($aliasPreview);
    CustomerImportService::commit($aliasPreview); impCheck(CustomerImportService::view($aliasPreview)['rows'][0]['error_code']==='ALIAS_CONFLICT','Conflicting alias was silently rebound.');
    $duplicateAllowed=impSource('contact',array_merge($defaults,array('first_name'=>'Another person'))); $allowedBatch=CustomerImportService::preview('contact',array($duplicateAllowed));
    impMap($allowedBatch,1,impDecision($h->id,$owner->id,array('allow_duplicate_account'=>true,'contact_kind'=>'none'))); impApprove($allowedBatch);
    impCheck(CustomerImportService::commit($allowedBatch)['counts']['created']===1 && (int)CustomerImportService::view($allowedBatch)['rows'][0]['result_account_id']!==$aid,'Explicit distinct same-name company decision not respected.');
    $ownerSource=impSource('contact',array_merge($defaults,array('company'=>'Owner missing Hotel access'))); $ownerBatch=CustomerImportService::preview('contact',array($ownerSource));
    impMap($ownerBatch,1,impDecision($h2->id,$owner->id,array('contact_kind'=>'none'))); impApprove($ownerBatch);
    CustomerImportService::commit($ownerBatch); impCheck(CustomerImportService::view($ownerBatch)['rows'][0]['error_code']==='OWNER_WITHOUT_HOTEL_ACCESS','Import granted Hotel ownership without access.');
    $dobSource=impSource('contact',array_merge($defaults,array('company'=>'Invalid birthday example','dob'=>'31/02/2000'))); $dobBatch=CustomerImportService::preview('contact',array($dobSource));
    impCheck(in_array('INVALID_DOB_REQUIRES_REVIEW',CustomerImportService::view($dobBatch)['rows'][0]['proposed']['warnings'],true),'Invalid source birthdate not flagged.');
    impMap($dobBatch,1,impDecision($h->id,$owner->id,array('contact_fields'=>array('dob'=>null)))); impApprove($dobBatch); CustomerImportService::commit($dobBatch);
    $dobPerson=ContactPerson::model()->findByPk(CustomerImportService::view($dobBatch)['rows'][0]['result_contact_id']); impCheck($dobPerson->dob===null,'Invalid source birthday became an invented date.');
    $legacyUnversioned=impSource('contact',array_merge($defaults,array('company'=>'Old mapping without fingerprint')));
    impRecord('LegacyLink',array('source_type'=>'contact','source_id'=>(string)$legacyUnversioned,'account_id'=>$aid,'resolution_state'=>'linked','import_batch_id'=>$b));
    $unversioned=CustomerImportService::preview('contact',array($legacyUnversioned)); impMap($unversioned,1,array('mode'=>'existing','reason'=>'Inspect existing unversioned mapping')); impApprove($unversioned);
    CustomerImportService::commit($unversioned); impCheck(CustomerImportService::view($unversioned)['rows'][0]['error_code']==='EXISTING_LINK_NOT_VERIFIED','Unversioned legacy link was silently blessed.');
    impStatus(400,function()use($unversioned,$h,$owner){impMap($unversioned,1,impDecision($h->id,$owner->id,array('account_kind'=>'row','account_row_no'=>1)));},'Cyclic/self mapping dependency accepted.');
    $reportHtml=impRun('reconcile',array('id'=>$b)); impCheck(strpos($reportHtml,'Đối soát đợt')!==false,'HTML reconciliation view did not render.');
    $reportJson=CJSON::decode(impRun('reconcile',array('id'=>$b,'format'=>'json'))); impCheck($reportJson['counts']['balanced'] && $reportJson['consistent'],'JSON reconciliation failed.');
    impStatus(404,function(){CustomerImportService::preview('contact',array(4294967295));},'Missing source IDs were silently omitted.');
    impStatus(400,function(){CustomerImportService::preview('users',array(1));},'Arbitrary SQL source table accepted.');
    impStatus(400,function(){CustomerImportService::preview('contact',array('1 OR 1=1'));},'Unsafe source ID accepted.');
    impStatus(405,function()use($b){impRun('commit',array('id'=>$b));},'GET commit accepted.');
    impStatus(400,function()use($b){impRun('commit',array('id'=>$b),array(),'POST',false);},'Import without CSRF accepted.');
    $html=impRun('admin'); impCheck(strpos($html,'name="'.Yii::app()->request->csrfTokenName.'"')!==false,'Preview form missing CSRF.');
    $rowHtml=impRun('view',array('id'=>$changedBatch)); impCheck(strpos($rowHtml,'decision[account_kind]')!==false && strpos($rowHtml,'expected_version')!==false,'Mapping review UI did not render.');
    $json=CJSON::decode(impRun('view',array('id'=>$b,'format'=>'json'))); impCheck(count($json['rows'])===3 && $json['counts']['balanced'],'JSON batch view failed.');
    $formSource=impSource('contact',array_merge($defaults,array('company'=>'Complete browser form flow','first_name'=>'','last_name'=>'')));
    $previewJson=CJSON::decode(impRun('preview',array(),array('source_type'=>'contact','source_ids'=>array($formSource)),'POST'));
    $formBatch=$previewJson['batch']['id']; impCheck($previewJson['counts']['total']===1,'Preview POST endpoint failed.');
    $uiDecision=array('mode'=>'map','reason'=>'Reviewed through the form','account_kind'=>'create','contact_kind'=>'none','account_fields'=>array('name'=>'Complete browser form flow'),
        'contact_fields'=>array('first_name'=>''),'hotels'=>array(array('hotel_id'=>$h->id,'owner_user_id'=>'keep'),array('hotel_id'=>'','owner_user_id'=>'keep')));
    impCheck(impRun('map',array('id'=>$formBatch,'row'=>1),array('ui'=>'1','expected_version'=>$previewJson['rows'][0]['lock_version'],'decision'=>$uiDecision,'markets'=>'EU, VN'),'POST')==='redirect','Mapping form POST failed.');
    $formData=CustomerImportService::view($formBatch);
    impCheck(impRun('approve',array('id'=>$formBatch),array('ui'=>'1','expected_version'=>$formData['batch']['lock_version']),'POST')==='redirect','Approval form POST failed.');
    impCheck(impRun('commit',array('id'=>$formBatch),array('ui'=>'1','limit'=>100),'POST')==='redirect','Commit form POST failed.');
    $formResult=CustomerImportService::reconcile($formBatch); impCheck($formResult['counts']['created']===1 && $formResult['consistent'],'Form flow reconciliation failed.');
    impCheck(CustomerImportService::browseSources('report')['total']>0,'Source picker could not browse Reports.');
    impStatus(400,function(){CustomerImportService::browseSources('contact','bad-page');},'Invalid source picker page accepted.');
    $sqlMode=$db->createCommand('SELECT @@SESSION.sql_mode')->queryScalar();
    try { $db->createCommand("SET SESSION sql_mode=''")->execute(); $zeroReport=impSource('report',array('company_name'=>'Legacy zero date report','ent'=>'0000-00-00','sales'=>'0000-00-00','site_ins'=>'0000-00-00')); }
    finally { $db->createCommand('SET SESSION sql_mode=:mode')->execute(array(':mode'=>$sqlMode)); }
    $zeroBatch=CustomerImportService::preview('report',array($zeroReport)); impMap($zeroBatch,1,impDecision($h->id,$owner->id,array('account_kind'=>'link','account_id'=>$aid,'contact_kind'=>'none'))); impApprove($zeroBatch);
    impCheck(CustomerImportService::commit($zeroBatch)['counts']['linked']===1,'Legacy zero dates failed strict-mode metadata-only import.');
    $zero=$db->createCommand('SELECT ent,sales,site_ins FROM reports WHERE id=:id')->queryRow(true,array(':id'=>$zeroReport));
    impCheck($zero['ent']==='0000-00-00' && $zero['sales']==='0000-00-00' && $zero['site_ins']==='0000-00-00','Zero dates were rewritten or converted into schedules.');
    $countsBefore=(int)CustomerImportBatch::model()->count();
    ImportUser::$current=$manager->id;
    impStatus(403,function(){CustomerImportService::preview('contact',array(1));},'Hotel manager can read unmapped legacy source.');
    impStatus(403,function()use($b){impRun('view',array('id'=>$b));},'Hotel manager can enumerate raw batch by ID.');
    impStatus(403,function()use($b){CustomerImportService::commit($b);},'Hotel action alone grants All import.');
    ImportUser::$current=$owner->id;
    impStatus(403,function()use($b){impRun('view',array('id'=>$b));},'Own sales can read import snapshot.');
    impCheck((int)CustomerImportBatch::model()->count()===$countsBefore,'Denied preview wrote staging data.');
    ImportUser::$current=1;
    $approver=new Users('create'); $approver->attributes=array('username'=>'import_approver','fullname'=>'Separate approver','password'=>'fake-password','roles'=>'superadmin','status'=>'A','last_login_date'=>'2026-11-18 00:00:00'); impCheck($approver->save(),'Approver fixture failed.');
    $approvalSource=impSource('contact',array_merge($defaults,array('company'=>'Separate approver and importer'))); $approvalBatch=CustomerImportService::preview('contact',array($approvalSource));
    impMap($approvalBatch,1,impDecision($h->id,null,array('contact_kind'=>'none'))); impApprove($approvalBatch);
    ImportUser::$current=$approver->id; $db->createCommand()->update('users',array('status'=>'I'),'id=1');
    impStatus(409,function()use($approvalBatch){CustomerImportService::commit($approvalBatch);},'Former approver lost permission but batch still imported.');
    impApprove($approvalBatch); $db->createCommand()->update('users',array('status'=>'A'),'id=1'); ImportUser::$current=1;
    impCheck(CustomerImportService::commit($approvalBatch)['counts']['created']===1,'Reapproval by an authorized actor failed.');
    $reviewed=LegacyLink::model()->findByPk(array('source_type'=>'contact','source_id'=>(string)$approvalSource));
    impCheck((int)$reviewed->reviewed_by===(int)$approver->id,'Source link confused the approver with the commit actor.');
    $flags=$app->params['mvp']; $disabled=$flags; $disabled['legacyImportEnabled']=false; $app->params['mvp']=$disabled;
    impStatus(503,function(){CustomerImportService::listing();},'Import enabled outside explicit staging flag.'); $app->params['mvp']=$flags;
    $db->createCommand()->update('users',array('status'=>'I'),'id=1');
    impStatus(403,function()use($b){CustomerImportService::view($b);},'Inactive All actor can read snapshots.');
    $db->createCommand()->update('users',array('status'=>'A'),'id=1');
    $peer=Yii::createComponent(require __DIR__.'/../config/db-test.php'); $peer->setActive(true);
    $lock=CustomerImportService::lockName();
    impCheck((int)$peer->createCommand('SELECT GET_LOCK(:key,0)')->queryScalar(array(':key'=>$lock))===1,'Second connection could not acquire import mutex.');
    try { impStatus(409,function()use($b){CustomerImportService::commit($b);},'Concurrent importer did not respect mutex.'); }
    finally { $peer->createCommand('SELECT RELEASE_LOCK(:key)')->queryScalar(array(':key'=>$lock)); $peer->setActive(false); }
    impCheck(CustomerImportService::commit($b)['counts']['linked']===2,'Resume after mutex release failed.');
    require_once __DIR__.'/../migrations/mvp/m261118_190000_prepare_legacy_import.php';
    try { (new m261118_190000_prepare_legacy_import)->down(); throw new Exception('Populated import rollback accepted.'); }
    catch (CDbException $e) { impCheck(strpos($e->getMessage(),'snapshots')!==false,'Migration rollback must preserve snapshots.'); }
} finally {
    if ($tx->active) { $tx->rollback(); }
    foreach (array('roles','users') as $table) { $db->createCommand('DROP TEMPORARY TABLE IF EXISTS '.$table)->execute(); }
}
foreach ($sourceBefore as $table=>$rows) { impCheck($rows===$db->createCommand('SELECT * FROM '.$table.' ORDER BY id')->queryAll(),'Test failed to restore legacy source: '.$table); }
foreach ($baseline as $table=>$rows) { impCheck($rows===$db->createCommand('SELECT * FROM '.$table)->queryAll(),'Test altered real fixture user/role rows.'); }
ob_end_clean();
echo "PASS: $checks import assertions; snapshots, explicit duplicate review, per-row rollback/resume, source/target staleness, Report preservation, permissions, UI/CSRF, reconciliation and cross-connection mutex.\n";
