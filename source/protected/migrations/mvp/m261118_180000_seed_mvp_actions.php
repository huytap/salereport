<?php

/** Action grants only. Never infer Hotel grants from legacy user.hotel text. */
class m261118_180000_seed_mvp_actions extends CDbMigration
{
    private function guard()
    {
        $db = $this->getDbConnection();
        if (!($db instanceof IsolatedTestDbConnection)) { throw new CDbException('WP02 seed requires the isolated test DB connection.'); }
        if ($db->createCommand('SELECT DATABASE()')->queryScalar() !== IsolatedTestDbConnection::databaseName($db->connectionString)) { throw new CDbException('Database mismatch.'); }
        return $db;
    }

    public function up()
    {
        $db = $this->guard();
        if ($db->schema->getTable('crm_role_action_seed',true)) { throw new CDbException('Pre-existing role seed journal; inspect migration history.'); }
        $grants = require dirname(__FILE__).'/../../config/mvp-actions.php';
        $rows = array();
        foreach ($grants as $name=>$rights) {
            $matches = $db->createCommand('SELECT * FROM roles WHERE LOWER(TRIM(role_name))=:name')->queryAll(true,array(':name'=>mb_strtolower(trim($name),'UTF-8')));
            if (count($matches)>1) { throw new CDbException('Ambiguous normalized role name: '.$name); }
            $old = $matches ? $matches[0] : null;
            $decoded = $old ? json_decode($old['access_rights'],true) : array();
            if (!is_array($decoded)) { throw new CDbException('Invalid role JSON: '.$name); }
            foreach ($decoded as $controller=>$actions) { if (!is_array($actions) || count(array_filter($actions,'is_string'))!==count($actions)) { throw new CDbException('Invalid action list: '.$name); } }
            foreach ($rights as $controller=>$actions) { $decoded[$controller]=array_values(array_unique(array_merge(isset($decoded[$controller])?$decoded[$controller]:array(),$actions))); }
            $rows[] = array('name'=>$old?$old['role_name']:$name,'old'=>$old,'after'=>json_encode($decoded));
        }
        $this->createTable('crm_role_action_seed',array('role_name'=>'VARCHAR(64) NOT NULL PRIMARY KEY','before_row_json'=>'TEXT NULL','after_rights'=>'TEXT NOT NULL'),'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        // Journal BEFORE each MyISAM write; an interrupted seed is inspectable, never silently reset.
        foreach ($rows as $row) {
            $db->createCommand()->insert('crm_role_action_seed',array('role_name'=>$row['name'],'before_row_json'=>$row['old']?json_encode($row['old']):null,'after_rights'=>$row['after']));
            if ($row['old']) {
                $count=$db->createCommand()->update('roles',array('access_rights'=>$row['after']),'role_name=:name AND access_rights=:before',array(':name'=>$row['name'],':before'=>$row['old']['access_rights']));
                if (!$count && $row['old']['access_rights']!==$row['after']) { throw new CDbException('Role changed while seeding. Inspect journal.'); }
            } else {
                $db->createCommand()->insert('roles',array('role_name'=>$row['name'],'access_rights'=>$row['after'],'remark'=>'MVP action grants; Hotel scope must be assigned separately.',
                    'added_date'=>gmdate('Y-m-d H:i:s'),'updated_date'=>gmdate('Y-m-d H:i:s'),'updated_by'=>1));
            }
        }
        $db->schema->refresh();
    }

    public function down()
    {
        $db=$this->guard();
        $rows=$db->createCommand('SELECT * FROM crm_role_action_seed')->queryAll();
        foreach ($rows as $row) {
            $current=$db->createCommand('SELECT access_rights FROM roles WHERE role_name=:name')->queryScalar(array(':name'=>$row['role_name']));
            if ($current!==$row['after_rights']) { throw new CDbException('Refusing rollback: role rights changed after seed.'); }
            if ($row['before_row_json']===null) {
                foreach ($db->createCommand('SELECT roles FROM users')->queryColumn() as $names) {
                    if (in_array(mb_strtolower(trim($row['role_name']),'UTF-8'),ActionAccess::names($names),true)) { throw new CDbException('Refusing rollback: a newly seeded role is in use.'); }
                }
            }
        }
        foreach ($rows as $row) {
            $old=$row['before_row_json']===null?null:json_decode($row['before_row_json'],true);
            if ($old) { $db->createCommand()->update('roles',array('access_rights'=>$old['access_rights']),'role_name=:name',array(':name'=>$row['role_name'])); }
            else { $db->createCommand()->delete('roles','role_name=:name',array(':name'=>$row['role_name'])); }
        }
        $this->dropTable('crm_role_action_seed');
    }
}
