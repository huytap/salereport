<?php

// WP00: CLI stays test-only until a separately reviewed production entry is added.
$mvp = require dirname(__FILE__).'/mvp.php';
$mvp['legacyImportEnabled'] = true; // CLI is guarded to test schemas.
return array(
    'basePath' => dirname(__FILE__).'/..',
    'name' => 'Sales Report isolated CLI',
    'import' => array('application.models.*', 'application.components.*', 'application.helpers.*', 'application.services.*'),
    'components' => array('db' => require dirname(__FILE__).'/db-test.php'),
    'params' => array('mvp' => $mvp),
    'commandMap' => array('migrate' => array(
        'class' => 'system.cli.commands.MigrateCommand',
        'migrationPath' => 'application.migrations.mvp',
        'migrationTable' => 'crm_migration',
    )),
);
