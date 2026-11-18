<?php

/** Refuses non-test schemas before opening PDO, then verifies the selected schema. */
class IsolatedTestDbConnection extends CDbConnection
{
    public static function databaseName($dsn)
    {
        if (!is_string($dsn) || strpos($dsn, 'mysql:') !== 0) {
            throw new CException('Tests/CLI require an explicit MySQL test DSN.');
        }
        preg_match_all('/(?:^mysql:|;)dbname=([^;]+)/', $dsn, $matches);
        if (count($matches[1]) !== 1 || !preg_match('/^sale_report_mvp_test(?:_[a-z0-9]+)*$/D', $matches[1][0])) {
            throw new CException('Refusing database: expected sale_report_mvp_test or a suffixed test schema.');
        }
        $seen = array();
        foreach (explode(';', substr($dsn, 6)) as $part) {
            if ($part === '') { continue; }
            $pair = explode('=', $part, 2);
            if (count($pair) !== 2 || $pair[1] === '' || isset($seen[$pair[0]])
                || !in_array($pair[0], array('host', 'port', 'unix_socket', 'dbname', 'charset'), true)) {
                throw new CException('Unsupported option in isolated test DSN.');
            }
            $seen[$pair[0]] = true;
        }
        return $matches[1][0];
    }

    protected function open()
    {
        $expected = self::databaseName($this->connectionString);
        parent::open();
        if ($this->getPdoInstance()->query('SELECT DATABASE()')->fetchColumn() !== $expected) {
            $this->setActive(false);
            throw new CDbException('Selected database does not match the isolated test schema.');
        }
    }
}
