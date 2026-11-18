<?php

/** Legacy CRUD tests shadow tables in their own connection, never alter real fixture tables. */
function isolatedTemporaryTableDdl($ddl)
{
    $ddl = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $ddl);
    $ddl = preg_replace('/AUTO_INCREMENT=\d+\b/', 'AUTO_INCREMENT=1', $ddl);
    // MySQL temporary tables cannot carry FKs; cross-table constraints are exercised
    // separately by mvp-schema-smoke.php against real tables in the isolated schema.
    return preg_replace('/,\s*CONSTRAINT `[^`]+` FOREIGN KEY .*?(?=,\n|\n\))/s', '', $ddl);
}
