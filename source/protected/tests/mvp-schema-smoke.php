<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('YII_ENABLE_EXCEPTION_HANDLER', false);
define('YII_ENABLE_ERROR_HANDLER', false);
require_once (getenv('YII_FRAMEWORK_PATH') ?: dirname(__FILE__).'/../../../../yii/yii.php');
$app = Yii::createConsoleApplication(require dirname(__FILE__).'/../config/console.php');
$db = $app->db;
$db->setActive(true);
if ($db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar() !== 'wp01-v1') {
    throw new Exception('Prepare the isolated synthetic test fixture first.');
}
$checks = 0;
function mvpCheck($condition, $message)
{
    global $checks;
    if (!$condition) { throw new Exception($message); }
    $checks++;
}
function mvpRecord($class, array $values)
{
    $model = new $class;
    $model->setAuditActorId(1);
    $model->setAttributes($values, false); // Explicit trusted fixture assignment, never request input.
    mvpCheck($model->save(), $class.' failed: '.json_encode($model->getErrors()));
    return $model;
}
function mvpConstraint($callback, $message)
{
    try { $callback(); }
    catch (CDbException $e) {
        mvpCheck(isset($e->errorInfo[0]) && $e->errorInfo[0] === '23000', $message.' (unexpected SQL error)');
        return;
    }
    throw new Exception($message);
}
function mvpRows($db, $table)
{
    return $db->createCommand('SELECT * FROM '.$db->quoteTableName($table).' ORDER BY 1')->queryAll();
}

foreach (array('mysql:host=127.0.0.1;dbname=sale_report','sqlite:/tmp/test.db','mysql:dbname=sale_report_mvp_test;dbname=sale_report',
    'mysql:dbname=sale_report_mvp_test;init_command=DROP TABLE users','mysql:dbname=sale_report_mvp_test;dbname=') as $dsn) {
    try { IsolatedTestDbConnection::databaseName($dsn); throw new Exception('Unsafe DSN accepted.'); }
    catch (CException $e) { mvpCheck(true, 'Unsafe DSN rejected before PDO.'); }
}
mvpCheck(IsolatedTestDbConnection::databaseName('mysql:host=127.0.0.1;dbname=sale_report_mvp_test_wp01') === 'sale_report_mvp_test_wp01', 'Suffixed test schema rejected.');
$webTestConfig = require dirname(__FILE__).'/../config/test.php';
mvpCheck($webTestConfig['components']['db']['class'] === 'IsolatedTestDbConnection', 'Web tests must use isolated connection.');
mvpCheck(!array_filter($app->params['mvp'], function ($value, $key) { return in_array($key, array('customerWrite','taskWrite','reminders'),true) && $value; }, ARRAY_FILTER_USE_BOTH), 'Features must stay disabled.');
require_once dirname(__FILE__).'/../migrations/mvp/m261118_170000_create_mvp_schema.php';
$guardedMigration = new m261118_170000_create_mvp_schema;
$guardedMigration->setDbConnection(new CDbConnection('mysql:host=127.0.0.1;dbname=sale_report'));
try { $guardedMigration->up(); throw new Exception('Production connection accepted by migration.'); }
catch (CDbException $e) { mvpCheck(strpos($e->getMessage(),'isolated test DB')!==false,'Migration must reject a regular connection before connecting.'); }

$classes = array('Hotel','Account','ContactPerson','AccountHotel','AccountMarket','UserHotelAccess','EntityAlias',
    'CustomerImportBatch','LegacyLink','Task','TaskReminder','Notification','CustomerImportRow','AuditEvent');
foreach ($classes as $class) {
    $model = $class::model();
    mvpCheck($model->getTableSchema() !== null, 'Missing model mapping: '.$class);
    $engine = $db->createCommand('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:name')->queryScalar(array(':name'=>$model->tableName()));
    mvpCheck(strtolower($engine) === 'innodb', 'MVP tables require InnoDB.');
}
$fkCount = $db->createCommand("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='FOREIGN KEY' AND CONSTRAINT_NAME LIKE 'fk_mvp_%'")->queryScalar();
mvpCheck((int)$fkCount === 20, 'Expected 20 cross-table foreign keys.');
$legacyTables = array('contact','reports','users','roles','incident','staff_asst','trackings','user_history');
$baseline = array();
foreach ($legacyTables as $table) { $baseline[$table] = mvpRows($db,$table); }
$tx = $db->beginTransaction();
try {
    $h = mvpRecord('Hotel', array('code'=>'TEST-H1','name'=>'Khách sạn kiểm thử'));
    $account = mvpRecord('Account', array('code'=>'TEST-A1','name'=>'  Công ty   KIỂM THỬ  ','segment'=>'CORPORATE'));
    mvpCheck($account->normalized_name === 'công ty kiểm thử', 'Vietnamese company normalization failed.');
    $assignment = mvpRecord('AccountHotel',array('account_id'=>$account->id,'hotel_id'=>$h->id,'owner_user_id'=>1));
    $person = mvpRecord('ContactPerson',array('account_id'=>$account->id,'first_name'=>'Người liên hệ','email'=>'shared@example.test'));
    mvpRecord('ContactPerson',array('account_id'=>$account->id,'last_name'=>'Người thứ hai','email'=>'shared@example.test'));
    mvpRecord('AccountMarket',array('account_id'=>$account->id,'market_code'=>'VN'));
    mvpRecord('UserHotelAccess',array('user_id'=>1,'hotel_id'=>$h->id,'scope_level'=>'own'));
    mvpRecord('EntityAlias',array('entity_type'=>'account','entity_id'=>$account->id,'normalized_alias'=>'legacy alias'));
    $batch = mvpRecord('CustomerImportBatch',array('source_type'=>'synthetic','source_key'=>'wp01-fixture','mapping_version'=>'v1','counts_json'=>'{"rows":1}'));
    mvpRecord('LegacyLink',array('source_type'=>'contact','source_id'=>'1','account_id'=>$account->id,'contact_person_id'=>$person->id,'resolution_state'=>'linked','import_batch_id'=>$batch->id));

    $reportId = $baseline['reports'][0]['id'];
    $db->createCommand()->update('reports',array('account_id'=>$account->id,'contact_person_id'=>$person->id,'hotel_id'=>$h->id,'identity_state'=>'linked'),'id=:id',array(':id'=>$reportId));
    $taskValues = array('code'=>'TEST-T1','account_id'=>$account->id,'contact_person_id'=>$person->id,'hotel_id'=>$h->id,'report_id'=>$reportId,
        'title'=>'Gọi lại khách','assignee_user_id'=>1,'due_mode'=>'date','due_date'=>'2026-11-20','client_request_key'=>'test-request-1','legacy_key'=>'report:1:field:sales');
    $task = mvpRecord('Task',$taskValues);
    mvpCheck($task->deadline_at_utc === '2026-11-20 17:00:00', 'Date-only deadline must be next midnight in Vietnam, stored UTC.');
    mvpCheck(!$task->isOverdue('2026-11-20 16:59:59') && $task->isOverdue('2026-11-20 17:00:00'), 'Overdue midnight boundary failed.');
    mvpCheck($task->account->id == $account->id && $task->contactPerson->id == $person->id && $task->report->id == $reportId, 'Task relations failed.');
    mvpCheck(count(Account::model()->findByPk($account->id)->contacts) === 2, 'Account must support multiple contacts.');
    mvpCheck(count(Report::model()->findByPk($reportId)->tasks) === 1, 'Report task relation failed.');
    $reminder = mvpRecord('TaskReminder',array('task_id'=>$task->id,'recipient_user_id'=>1,'schedule_version'=>1,'reminder_kind'=>'due_day','notify_at_utc'=>'2026-11-20 02:00:00'));
    mvpRecord('Notification',array('reminder_id'=>$reminder->id,'task_id'=>$task->id,'recipient_user_id'=>1,'schedule_version'=>1,'kind'=>'due_day'));
    mvpRecord('CustomerImportRow',array('batch_id'=>$batch->id,'row_no'=>1,'raw_json'=>'{"company":"Synthetic"}',
        'result_account_id'=>$account->id,'result_contact_id'=>$person->id,'result_task_id'=>$task->id,'result_state'=>'linked'));
    mvpRecord('AuditEvent',array('entity_type'=>'task','entity_id'=>(string)$task->id,'event_type'=>'created','request_id'=>'wp01-test','after_json'=>'{"status":"open"}','import_batch_id'=>$batch->id));

    $unsafe = new Task;
    $unsafe->attributes = array('id'=>999,'account_id'=>999,'hotel_id'=>999,'assignee_user_id'=>999,'status'=>'completed','created_by'=>999,
        'lock_version'=>88,'schedule_version'=>77,'deadline_at_utc'=>'2000-01-01 00:00:00','client_request_key'=>'injected','title'=>'Allowed');
    mvpCheck($unsafe->title === 'Allowed' && $unsafe->id === null && $unsafe->account_id === null && $unsafe->assignee_user_id === null
        && $unsafe->status === 'open' && $unsafe->lock_version == 1 && $unsafe->schedule_version == 1 && $unsafe->deadline_at_utc === null && $unsafe->created_by === null,
        'System fields must not be mass assignable.');
    $legacy = new Report;
    $legacy->attributes = array('company_name'=>'Allowed','account_id'=>999,'hotel_id'=>999,'identity_state'=>'linked','client_request_key'=>'injected');
    mvpCheck($legacy->company_name === 'Allowed' && $legacy->account_id === null && $legacy->hotel_id === null && $legacy->client_request_key === null, 'Report MVP fields must be protected.');
    $badPerson = new ContactPerson; $badPerson->setAuditActorId(1); $badPerson->account_id=$account->id;
    mvpCheck(!$badPerson->validate() && $badPerson->hasErrors('first_name'), 'Blank contact person accepted.');
    $bad = new Task; $bad->setAuditActorId(1); $bad->setAttributes(array_merge($taskValues,array('due_date'=>'2026-02-30')),false);
    mvpCheck(!$bad->validate() && $bad->hasErrors('due_date'), 'Invalid calendar date accepted.');
    $bad->due_date='2026-11-20'; $bad->timezone='Not/AZone';
    mvpCheck(!$bad->validate() && $bad->hasErrors('timezone'), 'Invalid timezone accepted.');
    $bad->timezone='Asia/Ho_Chi_Minh'; $bad->status='completed';
    mvpCheck(!$bad->validate() && $bad->hasErrors('completion_note'), 'Completed task without evidence accepted.');
    $bad->status='unknown';
    mvpCheck(!$bad->validate() && $bad->hasErrors('status'), 'Unknown status accepted.');
    $bad->status='open'; $bad->assignee_user_id=999999;
    mvpCheck(!$bad->validate() && $bad->hasErrors('assignee_user_id'), 'Missing user reference accepted.');
    $bad->assignee_user_id=1; $bad->title=array('unexpected');
    mvpCheck(!$bad->validate() && $bad->hasErrors('title'), 'Array input accepted for scalar column.');

    $appointment = mvpRecord('Task',array_merge($taskValues,array('code'=>'TEST-T2','client_request_key'=>'test-request-2','legacy_key'=>null,
        'kind'=>'appointment','due_mode'=>'datetime','due_date'=>null,'due_at_utc'=>'2026-11-20 04:00:00','starts_at_utc'=>'2026-11-20 03:00:00','ends_at_utc'=>'2026-11-20 04:00:00')));
    mvpCheck($appointment->deadline_at_utc === $appointment->ends_at_utc, 'Appointment deadline must be end time.');
    $appointment->ends_at_utc='2026-11-20 02:00:00';
    mvpCheck(!$appointment->validate() && $appointment->hasErrors('starts_at_utc'), 'Reversed appointment accepted.');
    $json = new AuditEvent; $json->setAttributes(array('entity_type'=>'task','entity_id'=>'1','event_type'=>'test','request_id'=>'bad-json','after_json'=>'{bad'),false);
    mvpCheck(!$json->validate() && $json->hasErrors('after_json'), 'Invalid audit JSON accepted.');

    $other = mvpRecord('Account',array('code'=>'TEST-A2','name'=>'Other account'));
    $otherPerson = mvpRecord('ContactPerson',array('account_id'=>$other->id,'first_name'=>'Other person'));
    $wrong = new Task; $wrong->setAuditActorId(1); $wrong->setAttributes(array_merge($taskValues,array('contact_person_id'=>$otherPerson->id)),false);
    mvpCheck(!$wrong->validate() && $wrong->hasErrors('contact_person_id'), 'Cross-account contact accepted by model.');
    mvpConstraint(function () use ($db,$task,$otherPerson) { $db->createCommand()->update('crm_task',array('contact_person_id'=>$otherPerson->id),'id=:id',array(':id'=>$task->id)); }, 'Composite FK must reject cross-account contacts.');
    mvpConstraint(function () use ($db,$task) { $db->createCommand()->update('crm_task',array('hotel_id'=>999999),'id=:id',array(':id'=>$task->id)); }, 'Composite FK must reject unknown Account/Hotel.');
    mvpConstraint(function () use ($db,$reminder) { $values=$reminder->attributes; unset($values['id']); $db->createCommand()->insert('crm_task_reminder',$values); }, 'Reminder dedup key missing.');
    mvpConstraint(function () use ($db,$task,$appointment) { $db->createCommand()->update('crm_task',array('client_request_key'=>$task->client_request_key),'id=:id',array(':id'=>$appointment->id)); }, 'Request dedup key missing.');
    mvpConstraint(function () use ($db,$task,$appointment) { $db->createCommand()->update('crm_task',array('legacy_key'=>$task->legacy_key),'id=:id',array(':id'=>$appointment->id)); }, 'Legacy dedup key missing.');
    mvpConstraint(function () use ($db,$reminder,$appointment) { $db->createCommand()->update('crm_notification',array('task_id'=>$appointment->id),'reminder_id=:id',array(':id'=>$reminder->id)); }, 'Notification context composite FK missing.');
    $first=Account::model()->findByPk($account->id); $second=Account::model()->findByPk($account->id);
    $first->setAuditActorId(1); $first->name='Updated'; mvpCheck($first->save() && $first->lock_version==2, 'Optimistic version did not advance.');
    $second->setAuditActorId(1); $second->name='Stale';
    try { $second->save(); throw new Exception('Stale update overwrote newer record.'); }
    catch (CDbException $e) { mvpCheck($e->getCode()===409,'Expected version conflict.'); }
    mvpCheck($second->refresh(), 'Stale record must be refreshable.');
    $second->name='Refreshed update';
    mvpCheck($second->save() && $second->lock_version==3, 'Refresh must update the optimistic lock baseline.');
    $audit=AuditEvent::model()->find(); $audit->reason='attempted rewrite';
    try { $audit->save(); throw new Exception('Audit rewrite accepted.'); }
    catch (CDbException $e) { mvpCheck(strpos($e->getMessage(),'append-only')!==false,'Audit events must be append-only.'); }
    try { $task->delete(); throw new Exception('Hard delete allowed.'); }
    catch (CDbException $e) { mvpCheck(strpos($e->getMessage(),'archived/cancelled')!==false,'Expected hard-delete protection.'); }
    require_once dirname(__FILE__).'/../migrations/mvp/m261118_170000_create_mvp_schema.php';
    $migration = new m261118_170000_create_mvp_schema;
    try { $migration->down(); throw new Exception('Destructive rollback accepted.'); }
    catch (CDbException $e) { mvpCheck(strpos($e->getMessage(),'contains data')!==false,'Rollback should refuse populated MVP tables.'); }
} finally { $tx->rollback(); }
foreach ($legacyTables as $table) { mvpCheck($baseline[$table] === mvpRows($db,$table), 'Legacy fixture changed: '.$table); }
echo "PASS: $checks assertions; 14 models, FK/idempotency constraints, dates, protected fields, version conflict, rollback guard and preserved legacy data.\n";
