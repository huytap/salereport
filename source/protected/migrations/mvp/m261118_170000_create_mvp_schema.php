<?php

/** Additive WP01 schema. Restricted to the isolated DB entry in this release. */
class m261118_170000_create_mvp_schema extends CDbMigration
{
    private function guard()
    {
        $db = $this->getDbConnection();
        if (!($db instanceof IsolatedTestDbConnection)) {
            throw new CDbException('WP01 migration requires the isolated test DB connection.');
        }
        $name = $db->createCommand('SELECT DATABASE()')->queryScalar();
        if ($name !== IsolatedTestDbConnection::databaseName($db->connectionString)) {
            throw new CDbException('Migration database mismatch.');
        }
        return $db;
    }

    public function up()
    {
        $db = $this->guard();
        foreach ($this->foreignKeys() as $fk) {
            $exists = $db->createCommand('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME=:name')
                ->queryScalar(array(':name'=>$fk[0]));
            if ($exists) { throw new CDbException('Pre-existing constraint name: '.$fk[0]); }
        }
        foreach ($this->definitions() as $name => $sql) {
            if ($db->schema->getTable($name, true) !== null) {
                throw new CDbException('Pre-existing MVP table: '.$name.'. Use migration history; do not rerun up directly.');
            }
        }
        $report = $db->schema->getTable('reports', true);
        if (!$report || !preg_match('/^int(?:\(\d+\))?$/i', $report->columns['id']->dbType)) {
            throw new CDbException('Expected legacy reports table with signed INT primary key.');
        }
        $engine = $db->createCommand("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports'")->queryScalar();
        if (strtolower($engine) !== 'innodb') {
            throw new CDbException('reports must already use InnoDB.');
        }
        foreach ($this->reportColumns() as $column) {
            if (isset($report->columns[$column])) { throw new CDbException('Pre-existing report column: '.$column); }
        }
        $created = array();
        $reportAltered = false;
        try {
            foreach ($this->definitions() as $name => $sql) {
                $this->execute($sql);
                $created[] = $name;
            }
            $this->execute("ALTER TABLE reports
                ADD account_id INT UNSIGNED NULL,
                ADD contact_person_id INT UNSIGNED NULL,
                ADD hotel_id INT UNSIGNED NULL,
                ADD identity_state VARCHAR(24) NOT NULL DEFAULT 'legacy_only',
                ADD lock_version INT UNSIGNED NOT NULL DEFAULT 1,
                ADD client_request_key VARCHAR(64) NULL,
                ADD UNIQUE KEY uq_report_request (added_by, client_request_key),
                ADD UNIQUE KEY uq_report_context (id, account_id, hotel_id),
                ADD KEY ix_report_account (account_id, hotel_id, date, id),
                ADD KEY ix_report_hotel (hotel_id, added_by, date),
                ADD KEY ix_report_identity (identity_state, id)");
            $reportAltered = true;
            foreach ($this->foreignKeys() as $fk) {
                $this->addForeignKey($fk[0], $fk[1], $fk[2], $fk[3], $fk[4], 'RESTRICT', 'RESTRICT');
            }
        } catch (Exception $e) {
            // MySQL DDL auto-commits: clean only objects created by this invocation.
            $this->removeForeignKeys();
            if ($reportAltered) { $this->removeReportColumns(); }
            foreach (array_reverse($created) as $name) { $this->dropTable($name); }
            $db->schema->refresh();
            throw $e;
        }
        $db->schema->refresh();
    }

    public function down()
    {
        $db = $this->guard();
        foreach (array_keys($this->definitions()) as $name) {
            if ($db->createCommand('SELECT COUNT(*) FROM '.$db->quoteTableName($name))->queryScalar() > 0) {
                throw new CDbException('Refusing rollback: MVP table contains data: '.$name);
            }
        }
        $used = $db->createCommand("SELECT COUNT(*) FROM reports WHERE account_id IS NOT NULL OR contact_person_id IS NOT NULL OR hotel_id IS NOT NULL OR client_request_key IS NOT NULL OR identity_state <> 'legacy_only' OR lock_version <> 1")->queryScalar();
        if ($used > 0) { throw new CDbException('Refusing rollback: reports contain MVP data.'); }
        $this->removeForeignKeys();
        $this->removeReportColumns();
        foreach (array_reverse(array_keys($this->definitions())) as $name) { $this->dropTable($name); }
        $db->schema->refresh();
    }

    private function removeForeignKeys()
    {
        $db = $this->getDbConnection();
        foreach (array_reverse($this->foreignKeys()) as $fk) {
            $exists = $db->createCommand('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=:table AND CONSTRAINT_NAME=:name')
                ->queryScalar(array(':table'=>$fk[1], ':name'=>$fk[0]));
            if ($exists) { $this->dropForeignKey($fk[0], $fk[1]); }
        }
    }

    private function removeReportColumns()
    {
        $this->execute('ALTER TABLE reports DROP INDEX uq_report_request, DROP INDEX uq_report_context, DROP INDEX ix_report_account, DROP INDEX ix_report_hotel, DROP INDEX ix_report_identity, '.implode(', ', array_map(function ($name) { return 'DROP COLUMN '.$name; }, $this->reportColumns())));
    }

    private function reportColumns()
    {
        return array('account_id', 'contact_person_id', 'hotel_id', 'identity_state', 'lock_version', 'client_request_key');
    }

    private function foreignKeys()
    {
        return array(
            array('fk_mvp_01', 'crm_contact_person', 'account_id', 'crm_account', 'id'),
            array('fk_mvp_02', 'crm_account_hotel', 'account_id', 'crm_account', 'id'),
            array('fk_mvp_03', 'crm_account_hotel', 'hotel_id', 'crm_hotel', 'id'),
            array('fk_mvp_04', 'crm_account_market', 'account_id', 'crm_account', 'id'),
            array('fk_mvp_05', 'crm_user_hotel_access', 'hotel_id', 'crm_hotel', 'id'),
            array('fk_mvp_06', 'crm_legacy_link', 'account_id', 'crm_account', 'id'),
            array('fk_mvp_07', 'crm_legacy_link', 'contact_person_id,account_id', 'crm_contact_person', 'id,account_id'),
            array('fk_mvp_08', 'crm_legacy_link', 'import_batch_id', 'crm_import_batch', 'id'),
            array('fk_mvp_09', 'crm_task', 'account_id,hotel_id', 'crm_account_hotel', 'account_id,hotel_id'),
            array('fk_mvp_10', 'crm_task', 'contact_person_id,account_id', 'crm_contact_person', 'id,account_id'),
            array('fk_mvp_11', 'crm_task', 'report_id,account_id,hotel_id', 'reports', 'id,account_id,hotel_id'),
            array('fk_mvp_12', 'crm_task_reminder', 'task_id', 'crm_task', 'id'),
            array('fk_mvp_13', 'crm_notification', 'reminder_id,task_id,recipient_user_id,schedule_version', 'crm_task_reminder', 'id,task_id,recipient_user_id,schedule_version'),
            array('fk_mvp_14', 'crm_import_row', 'batch_id', 'crm_import_batch', 'id'),
            array('fk_mvp_15', 'crm_import_row', 'result_account_id', 'crm_account', 'id'),
            array('fk_mvp_16', 'crm_import_row', 'result_contact_id,result_account_id', 'crm_contact_person', 'id,account_id'),
            array('fk_mvp_17', 'crm_import_row', 'result_task_id', 'crm_task', 'id'),
            array('fk_mvp_18', 'crm_audit_event', 'import_batch_id', 'crm_import_batch', 'id'),
            array('fk_mvp_19', 'reports', 'account_id,hotel_id', 'crm_account_hotel', 'account_id,hotel_id'),
            array('fk_mvp_20', 'reports', 'contact_person_id,account_id', 'crm_contact_person', 'id,account_id'),
        );
    }

    private function definitions()
    {
        return array(
            'crm_hotel' => 'CREATE TABLE `crm_hotel` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(255) NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT \'Asia/Ho_Chi_Minh\',
  status VARCHAR(16) NOT NULL DEFAULT \'active\',
  UNIQUE KEY uq_hotel_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_account' => 'CREATE TABLE `crm_account` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(255) NOT NULL,
  normalized_name VARCHAR(255) NOT NULL,
  segment VARCHAR(32) NOT NULL DEFAULT \'\',
  biz_scope VARCHAR(128) NOT NULL DEFAULT \'\',
  website VARCHAR(765) NOT NULL DEFAULT \'\',
  phone VARCHAR(64) NOT NULL DEFAULT \'\',
  email VARCHAR(200) NOT NULL DEFAULT \'\',
  building VARCHAR(128) NOT NULL DEFAULT \'\',
  address_1 VARCHAR(255) NOT NULL DEFAULT \'\',
  address_2 VARCHAR(255) NOT NULL DEFAULT \'\',
  address_3 VARCHAR(255) NOT NULL DEFAULT \'\',
  city VARCHAR(128) NOT NULL DEFAULT \'\',
  country VARCHAR(128) NOT NULL DEFAULT \'\',
  source VARCHAR(128) NOT NULL DEFAULT \'\',
  fit_series VARCHAR(128) NOT NULL DEFAULT \'\',
  dmc VARCHAR(128) NOT NULL DEFAULT \'\',
  notes TEXT NULL,
  status VARCHAR(16) NOT NULL DEFAULT \'active\',
  created_by INT UNSIGNED NOT NULL,
  updated_by INT UNSIGNED NOT NULL,
  created_at_utc DATETIME NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_account_code (code),
  KEY ix_account_name (normalized_name),
  KEY ix_account_status (status, updated_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_contact_person' => 'CREATE TABLE `crm_contact_person` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id INT UNSIGNED NOT NULL,
  title VARCHAR(32) NOT NULL DEFAULT \'\',
  first_name VARCHAR(128) NOT NULL DEFAULT \'\',
  last_name VARCHAR(128) NOT NULL DEFAULT \'\',
  position VARCHAR(128) NOT NULL DEFAULT \'\',
  email VARCHAR(200) NOT NULL DEFAULT \'\',
  phone VARCHAR(64) NOT NULL DEFAULT \'\',
  mobile VARCHAR(64) NOT NULL DEFAULT \'\',
  contact_role VARCHAR(64) NOT NULL DEFAULT \'\',
  dob DATE NULL,
  notes TEXT NULL,
  status VARCHAR(16) NOT NULL DEFAULT \'active\',
  created_by INT UNSIGNED NOT NULL,
  updated_by INT UNSIGNED NOT NULL,
  created_at_utc DATETIME NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_person_account (id, account_id),
  KEY ix_person_account (account_id, status),
  KEY ix_person_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_account_hotel' => 'CREATE TABLE `crm_account_hotel` (
  account_id INT UNSIGNED NOT NULL,
  hotel_id INT UNSIGNED NOT NULL,
  owner_user_id INT UNSIGNED NULL,
  status VARCHAR(16) NOT NULL DEFAULT \'active\',
  created_by INT UNSIGNED NOT NULL,
  updated_by INT UNSIGNED NOT NULL,
  created_at_utc DATETIME NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (account_id, hotel_id),
  KEY ix_account_hotel_owner (hotel_id, owner_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_account_market' => 'CREATE TABLE `crm_account_market` (
  account_id INT UNSIGNED NOT NULL,
  market_code VARCHAR(64) NOT NULL,
  PRIMARY KEY (account_id, market_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_user_hotel_access' => 'CREATE TABLE `crm_user_hotel_access` (
  user_id INT UNSIGNED NOT NULL,
  hotel_id INT UNSIGNED NOT NULL,
  scope_level VARCHAR(16) NOT NULL,
  updated_by INT UNSIGNED NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  PRIMARY KEY (user_id, hotel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_entity_alias' => 'CREATE TABLE `crm_entity_alias` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(16) NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  normalized_alias VARCHAR(255) NOT NULL,
  source VARCHAR(128) NOT NULL DEFAULT \'\',
  UNIQUE KEY uq_entity_alias (entity_type, entity_id, normalized_alias),
  KEY ix_alias_lookup (entity_type, normalized_alias)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_import_batch' => 'CREATE TABLE `crm_import_batch` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  source_type VARCHAR(32) NOT NULL,
  source_key VARCHAR(128) NOT NULL,
  mapping_version VARCHAR(32) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT \'preview\',
  approved_by INT UNSIGNED NULL,
  approved_at_utc DATETIME NULL,
  completed_at_utc DATETIME NULL,
  counts_json TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  updated_by INT UNSIGNED NOT NULL,
  created_at_utc DATETIME NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  UNIQUE KEY uq_import_source (source_type, source_key, mapping_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_legacy_link' => 'CREATE TABLE `crm_legacy_link` (
  source_type VARCHAR(32) NOT NULL,
  source_id VARCHAR(64) NOT NULL,
  account_id INT UNSIGNED NULL,
  contact_person_id INT UNSIGNED NULL,
  resolution_state VARCHAR(24) NOT NULL DEFAULT \'needs_review\',
  import_batch_id INT UNSIGNED NOT NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at_utc DATETIME NULL,
  PRIMARY KEY (source_type, source_id),
  KEY ix_legacy_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_task' => 'CREATE TABLE `crm_task` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  account_id INT UNSIGNED NOT NULL,
  contact_person_id INT UNSIGNED NULL,
  hotel_id INT UNSIGNED NOT NULL,
  report_id INT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  kind VARCHAR(16) NOT NULL DEFAULT \'task\',
  action_type VARCHAR(32) NOT NULL DEFAULT \'other\',
  priority VARCHAR(16) NOT NULL DEFAULT \'normal\',
  assignee_user_id INT UNSIGNED NOT NULL,
  due_mode VARCHAR(16) NOT NULL,
  due_date DATE NULL,
  due_at_utc DATETIME NULL,
  deadline_at_utc DATETIME NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT \'Asia/Ho_Chi_Minh\',
  starts_at_utc DATETIME NULL,
  ends_at_utc DATETIME NULL,
  location VARCHAR(255) NULL,
  status VARCHAR(16) NOT NULL DEFAULT \'open\',
  completed_at_utc DATETIME NULL,
  completed_by INT UNSIGNED NULL,
  completion_note TEXT NULL,
  cancelled_at_utc DATETIME NULL,
  cancel_reason TEXT NULL,
  schedule_version INT UNSIGNED NOT NULL DEFAULT 1,
  client_request_key VARCHAR(64) NOT NULL,
  legacy_key VARCHAR(100) NULL,
  created_by INT UNSIGNED NOT NULL,
  updated_by INT UNSIGNED NOT NULL,
  created_at_utc DATETIME NOT NULL,
  updated_at_utc DATETIME NOT NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_task_code (code),
  UNIQUE KEY uq_task_request (created_by, client_request_key),
  UNIQUE KEY uq_task_legacy (legacy_key),
  KEY ix_task_assignee (assignee_user_id, status, deadline_at_utc, id),
  KEY ix_task_hotel_due (hotel_id, status, deadline_at_utc, id),
  KEY ix_task_account (account_id, hotel_id, status),
  KEY ix_task_report (report_id, account_id, hotel_id),
  KEY ix_task_calendar (hotel_id, kind, starts_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_task_reminder' => 'CREATE TABLE `crm_task_reminder` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NOT NULL,
  recipient_user_id INT UNSIGNED NOT NULL,
  schedule_version INT UNSIGNED NOT NULL,
  reminder_kind VARCHAR(16) NOT NULL,
  notify_at_utc DATETIME NOT NULL,
  state VARCHAR(16) NOT NULL DEFAULT \'pending\',
  delivered_at_utc DATETIME NULL,
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_error_code VARCHAR(64) NULL,
  UNIQUE KEY uq_reminder_slot (task_id, recipient_user_id, schedule_version, reminder_kind),
  UNIQUE KEY uq_reminder_context (id, task_id, recipient_user_id, schedule_version),
  KEY ix_reminder_dispatch (state, notify_at_utc, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_notification' => 'CREATE TABLE `crm_notification` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reminder_id INT UNSIGNED NOT NULL,
  recipient_user_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  schedule_version INT UNSIGNED NOT NULL,
  kind VARCHAR(16) NOT NULL,
  created_at_utc DATETIME NOT NULL,
  read_at_utc DATETIME NULL,
  UNIQUE KEY uq_notification_reminder (reminder_id),
  KEY ix_notification_inbox (recipient_user_id, read_at_utc, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_import_row' => 'CREATE TABLE `crm_import_row` (
  batch_id INT UNSIGNED NOT NULL,
  row_no INT UNSIGNED NOT NULL,
  source_table VARCHAR(32) NULL,
  source_id VARCHAR(64) NULL,
  raw_json MEDIUMTEXT NOT NULL,
  proposed_json MEDIUMTEXT NULL,
  result_state VARCHAR(24) NOT NULL DEFAULT \'needs_review\',
  error_code VARCHAR(64) NULL,
  result_account_id INT UNSIGNED NULL,
  result_contact_id INT UNSIGNED NULL,
  result_task_id INT UNSIGNED NULL,
  PRIMARY KEY (batch_id, row_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'crm_audit_event' => 'CREATE TABLE `crm_audit_event` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(32) NOT NULL,
  entity_id VARCHAR(64) NOT NULL,
  event_type VARCHAR(32) NOT NULL,
  actor_user_id INT UNSIGNED NULL,
  occurred_at_utc DATETIME NOT NULL,
  before_json MEDIUMTEXT NULL,
  after_json MEDIUMTEXT NULL,
  reason TEXT NULL,
  request_id VARCHAR(64) NOT NULL,
  import_batch_id INT UNSIGNED NULL,
  KEY ix_audit_entity (entity_type, entity_id, id),
  KEY ix_audit_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }
}
