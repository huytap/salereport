<?php
/** CLI-only dispatch; no human actor is impersonated, and no cron is installed. */
class ReminderDispatchService
{
    public static function ready()
    {
        if (!CustomerPolicy::ready() || !Yii::app()->db->schema->getTable('crm_reminder_dispatch_run')) { throw new CHttpException(503,'Chưa triển khai schema reminder WP07.'); }
    }
    public static function testDb()
    {
        $db=Yii::app()->db;
        if (!($db instanceof IsolatedTestDbConnection) || $db->createCommand('SELECT DATABASE()')->queryScalar()!==IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CHttpException(503,'Worker và ghi notification WP07 chỉ chạy trên DB test.'); }
        self::ready(); return $db;
    }
    private static function limit($value)
    {
        $value=CustomerWriteSupport::id($value); if ($value>500) { throw new CHttpException(400,'Batch phải từ 1 đến 500 reminder.'); } return $value;
    }
    private static function independent()
    {
        if (Yii::app()->db->getCurrentTransaction()) { throw new CHttpException(409,'Worker/đánh dấu đã đọc cần giao dịch độc lập.'); }
    }
    public static function dispatch($limit=200,$dryRun=false)
    {
        if (PHP_SAPI!=='cli') { throw new CHttpException(403,'Dispatch chỉ được chạy từ CLI.'); }
        $db=self::testDb(); $limit=self::limit($limit); self::independent();
        if (!is_bool($dryRun)) { throw new CHttpException(400,'dryRun phải là boolean.'); }
        if (!$dryRun && empty(Yii::app()->params['mvp']['reminders'])) { throw new CHttpException(503,'Dispatch đang tắt. Chỉ bật tạm cho process test bằng --enableTest=1.'); }
        $started=microtime(true); $now=gmdate('Y-m-d H:i:s');
        $candidates=$db->createCommand("SELECT id,task_id FROM crm_task_reminder WHERE state='pending' AND notify_at_utc<=:now ORDER BY notify_at_utc,id LIMIT ".$limit)->queryAll(true,array(':now'=>$now));
        if ($dryRun) {
            $result=array('dry_run'=>true,'worker_enabled'=>!empty(Yii::app()->params['mvp']['reminders']),'selected_count'=>count($candidates),'would_deliver'=>0,'would_cancel'=>0,'would_recover'=>0,'reasons'=>array());
            foreach ($candidates as $candidate) {
                $r=$db->createCommand('SELECT * FROM crm_task_reminder WHERE id=:id')->queryRow(true,array(':id'=>$candidate['id'])); $t=$db->createCommand('SELECT * FROM crm_task WHERE id=:id')->queryRow(true,array(':id'=>$candidate['task_id']));
                if (!$r || !$t || $r['state']!=='pending') { continue; }
                $reason=ReminderEligibility::reason($t,$r,$now);
                $existing=$db->createCommand('SELECT * FROM crm_notification WHERE reminder_id=:id')->queryRow(true,array(':id'=>$r['id']));
                if ($existing && !ReminderEligibility::notificationMatches($r,$existing)) { $reason='NOTIFICATION_CONFLICT'; }
                if ($reason) { $result['would_cancel']++; $result['reasons'][$reason]=isset($result['reasons'][$reason])?$result['reasons'][$reason]+1:1; }
                else { $result['would_deliver']++; if ($existing) { $result['would_recover']++; } }
            }
            return $result;
        }
        $token=bin2hex(random_bytes(16));
        $db->createCommand()->insert('crm_reminder_dispatch_run',array('run_key'=>$token,'started_at_utc'=>$now,'heartbeat_at_utc'=>$now,'status'=>'running','selected_count'=>count($candidates))); $runId=(int)$db->lastInsertID;
        $stats=array('created_count'=>0,'recovered_count'=>0,'cancelled_count'=>0,'skipped_count'=>0,'error_count'=>0,'processed_count'=>0);
        $timeout=(int)$db->createCommand('SELECT @@SESSION.innodb_lock_wait_timeout')->queryScalar(); $budget=false;
        try {
            $db->createCommand('SET SESSION innodb_lock_wait_timeout=2')->execute();
            foreach ($candidates as $candidate) {
                if (microtime(true)-$started>=20) { $budget=true; break; }
                try {
                    $outcome=self::deliver($candidate['task_id'],$candidate['id']); $stats[$outcome.'_count']++;
                } catch (Exception $e) {
                    $stats['error_count']++; self::recordError($candidate['task_id'],$candidate['id'],self::errorCode($e));
                }
                $stats['processed_count']++;
                $db->createCommand()->update('crm_reminder_dispatch_run',array_merge($stats,array('heartbeat_at_utc'=>gmdate('Y-m-d H:i:s'))),'id=:id',array(':id'=>$runId));
            }
            $status=$stats['error_count']?'partial':'completed';
            self::finish($runId,$stats,$status,$started,$budget?'BUDGET_EXHAUSTED':($stats['error_count']?'ROW_FAILURE':null));
            return array_merge(array('run_id'=>$runId,'run_key'=>$token,'dry_run'=>false,'status'=>$status,'selected_count'=>count($candidates),'budget_exhausted'=>$budget),$stats);
        } catch (Exception $e) {
            try { self::finish($runId,$stats,'failed',$started,'DISPATCH_FAILED'); } catch (Exception $ignored) {}
            throw $e;
        } finally { try { $db->createCommand('SET SESSION innodb_lock_wait_timeout='.$timeout)->execute(); } catch (Exception $ignored) {} }
    }
    private static function finish($id,array $stats,$status,$started,$error)
    {
        Yii::app()->db->createCommand()->update('crm_reminder_dispatch_run',array_merge($stats,array('status'=>$status,'heartbeat_at_utc'=>gmdate('Y-m-d H:i:s'),'finished_at_utc'=>gmdate('Y-m-d H:i:s'),'duration_ms'=>(int)round((microtime(true)-$started)*1000),'error_code'=>$error)),'id=:id',array(':id'=>$id));
    }
    private static function errorCode($e)
    {
        $vendor=$e instanceof CDbException && isset($e->errorInfo[1])?(int)$e->errorInfo[1]:0;
        return $vendor===1205?'LOCK_TIMEOUT':($vendor===1213?'DEADLOCK':'DB_WRITE_FAILED');
    }
    private static function deliver($taskId,$reminderId)
    {
        $db=Yii::app()->db;
        for ($attempt=0; ; $attempt++) {
            $tx=$db->beginTransaction();
            try {
                $task=$db->createCommand('SELECT * FROM crm_task WHERE id=:id FOR UPDATE')->queryRow(true,array(':id'=>$taskId));
                $r=$db->createCommand('SELECT * FROM crm_task_reminder WHERE id=:id FOR UPDATE')->queryRow(true,array(':id'=>$reminderId)); $now=gmdate('Y-m-d H:i:s');
                if (!$task || !$r || $r['state']!=='pending' || $r['notify_at_utc']>$now) { $tx->commit(); return 'skipped'; }
                $reason=ReminderEligibility::reason($task,$r,$now);
                $existing=$db->createCommand('SELECT * FROM crm_notification WHERE reminder_id=:id FOR UPDATE')->queryRow(true,array(':id'=>$reminderId));
                if ($existing && !ReminderEligibility::notificationMatches($r,$existing)) { $reason='NOTIFICATION_CONFLICT'; }
                if ($reason) {
                    $db->createCommand()->update('crm_task_reminder',array('state'=>'cancelled','attempt_count'=>(int)$r['attempt_count']+1,'last_error_code'=>$reason),'id=:id',array(':id'=>$reminderId)); $tx->commit(); return 'cancelled';
                }
                if (!$existing) { $db->createCommand()->insert('crm_notification',array('reminder_id'=>$reminderId,'recipient_user_id'=>$r['recipient_user_id'],'task_id'=>$taskId,'schedule_version'=>$r['schedule_version'],'kind'=>$r['reminder_kind'],'created_at_utc'=>$now)); }
                $db->createCommand()->update('crm_task_reminder',array('state'=>'delivered','delivered_at_utc'=>$now,'attempt_count'=>(int)$r['attempt_count']+1,'last_error_code'=>null),'id=:id',array(':id'=>$reminderId));
                $tx->commit(); return $existing?'recovered':'created';
            } catch (Exception $e) {
                if ($tx->active) { $tx->rollback(); }
                if (self::errorCode($e)!=='DEADLOCK' || $attempt>=2) { throw $e; }
            }
        }
    }
    private static function recordError($taskId,$reminderId,$code)
    {
        $db=Yii::app()->db; $tx=$db->beginTransaction();
        try {
            $db->createCommand('SELECT id FROM crm_task WHERE id=:id FOR UPDATE')->queryScalar(array(':id'=>$taskId));
            $r=$db->createCommand('SELECT state,attempt_count FROM crm_task_reminder WHERE id=:id FOR UPDATE')->queryRow(true,array(':id'=>$reminderId));
            if ($r && $r['state']==='pending') { $db->createCommand()->update('crm_task_reminder',array('attempt_count'=>(int)$r['attempt_count']+1,'last_error_code'=>$code),'id=:id',array(':id'=>$reminderId)); }
            $tx->commit();
        } catch (Exception $ignored) { if ($tx->active) { $tx->rollback(); } }
    }
    /** SELECT only; no heartbeat or attempts are changed. CLI is physically test-DB guarded. */
    public static function check()
    {
        self::ready(); $db=Yii::app()->db; $now=gmdate('Y-m-d H:i:s'); $late=gmdate('Y-m-d H:i:s',time()-300);
        $queue=$db->createCommand("SELECT COUNT(*) AS pending_count,COALESCE(SUM(notify_at_utc<=:now),0) AS due_count,COALESCE(SUM(notify_at_utc<:late),0) AS late_pending_count,COALESCE(SUM(last_error_code IS NOT NULL),0) AS failed_pending_count,MIN(notify_at_utc) AS oldest_pending_at_utc FROM crm_task_reminder WHERE state='pending'")->queryRow(true,array(':now'=>$now,':late'=>$late));
        foreach (array('pending_count','due_count','late_pending_count','failed_pending_count') as $key) { $queue[$key]=(int)$queue[$key]; }
        $success=$db->createCommand("SELECT MAX(finished_at_utc) FROM crm_reminder_dispatch_run WHERE status='completed'")->queryScalar();
        $latest=$db->createCommand('SELECT * FROM crm_reminder_dispatch_run ORDER BY id DESC LIMIT 1')->queryRow();
        $stuck=(int)$db->createCommand("SELECT COUNT(*) FROM crm_reminder_dispatch_run WHERE status='running' AND heartbeat_at_utc<:cutoff")->queryScalar(array(':cutoff'=>gmdate('Y-m-d H:i:s',time()-180)));
        $alerts=array();
        if (empty(Yii::app()->params['mvp']['reminders'])) { $alerts[]='DISABLED'; }
        if (!$success) { $alerts[]='NEVER_COMPLETED'; } elseif ($success<gmdate('Y-m-d H:i:s',time()-180)) { $alerts[]='STALE_HEARTBEAT'; }
        if ($stuck) { $alerts[]='STUCK_RUN'; }
        if ($queue['late_pending_count']) { $alerts[]='BACKLOG'; }
        if ($queue['failed_pending_count'] || ($latest && in_array($latest['status'],array('partial','failed'),true))) { $alerts[]='ERRORS'; }
        return array('worker_enabled'=>!empty(Yii::app()->params['mvp']['reminders']),'healthy'=>!$alerts,'alerts'=>$alerts,'last_success_at_utc'=>$success ?: null,'stuck_runs'=>$stuck,'queue'=>$queue,'latest_run'=>$latest ?: null,'checked_at_utc'=>$now);
    }
}
