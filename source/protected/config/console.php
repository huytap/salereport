<?php

// WP00: CLI stays test-only until a separately reviewed production entry is added.
return array(
    'basePath' => dirname(__FILE__).'/..',
    'name' => 'Sales Report isolated CLI',
    'import' => array('application.models.*', 'application.components.*', 'application.helpers.*'),
    'components' => array('db' => require dirname(__FILE__).'/db-test.php'),
    'params' => array('mvp' => require dirname(__FILE__).'/mvp.php'),
    'commandMap' => array('migrate' => array(
        'class' => 'system.cli.commands.MigrateCommand',
        'migrationPath' => 'application.migrations.mvp',
        'migrationTable' => 'crm_migration',
    )),
);
