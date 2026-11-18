<?php

/** Match optional form dates and open login sessions without inventing date values. */
class m261118_090000_restore_optional_dates extends CDbMigration
{
    /** Tests must opt in and prove every migration target is connection-local. */
    public $temporaryTablesOnly = false;

    public function up()
    {
        $db = $this->getDbConnection();
        if ($this->temporaryTablesOnly) {
            foreach (array('contact', 'users', 'user_history') as $table) {
                $definition = $db->createCommand('SHOW CREATE TABLE '.$db->quoteTableName($table))->queryRow();
                $values = array_values($definition);
                if (strpos($values[1], 'CREATE TEMPORARY TABLE ') !== 0) {
                    throw new CDbException('Test migration refused: '.$table.' is not a temporary table.');
                }
            }
        }
        $mode = $db->createCommand('SELECT @@SESSION.sql_mode')->queryScalar();
        $compatible = array_diff(explode(',', $mode), array('NO_ZERO_DATE', 'NO_ZERO_IN_DATE'));
        try {
            $db->createCommand('SET SESSION sql_mode = :mode')->execute(array(':mode' => implode(',', $compatible)));
            // Only relax nullability; existing values and records are preserved.
            foreach (array(
                'contact' => array('validity_from' => 'DATE', 'validity_to' => 'DATE'),
                'users' => array('last_login_date' => 'DATETIME'),
                'user_history' => array('logout_time' => 'DATETIME'),
            ) as $table => $columns) {
                $schema = $db->schema->getTable($table, true);
                $changes = array();
                foreach ($columns as $name => $type) {
                    if (!$schema->columns[$name]->allowNull) {
                        $changes[] = 'MODIFY '.$db->quoteColumnName($name).' '.$type.' NULL DEFAULT NULL';
                    }
                }
                if ($changes) {
                    $db->createCommand('ALTER TABLE '.$db->quoteTableName($table).' '.implode(', ', $changes))->execute();
                }
            }
            $schema = $db->schema->getTable('user_history', true);
            if ($schema->columns['ip_address']->size < 45) {
                $db->createCommand('ALTER TABLE user_history MODIFY ip_address VARCHAR(45) NOT NULL')->execute();
            }
        } finally {
            $db->createCommand('SET SESSION sql_mode = :mode')->execute(array(':mode' => $mode));
            $db->schema->refresh();
        }
    }

    public function down()
    {
        echo "Cannot restore NOT NULL until optional dates have been supplied for every record.\n";
        return false;
    }
}
