<?php

// uncomment the following to define a path alias
// Yii::setPathOfAlias('local','path/to/local-folder');

// This is the main Web application configuration. Any writable
// CWebApplication properties can be configured here.
return array(
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'Ho Tram Resort',
	'language'=>'en',
	// preloading 'log' component
	'preload'=>array('log','booster'),

	// autoloading model and component classes
	'import'=>array(
		'application.models.*',
		'application.components.*',
		'application.helpers.*',
		'application.services.*',
		//'ext.php-imap-client.*',
	),

	'modules'=>array(
		// uncomment the following to enable the Gii tool
		
		'gii'=>array(
			'class'=>'system.gii.GiiModule',
			'password'=>'123',
			// If removed, Gii defaults to localhost only. Edit carefully to taste.
			'ipFilters'=>array('127.0.0.1','::1'),
		),
		
	),

	// application components
	'components'=>array(
		'request'=>array('enableCsrfValidation'=>true),
		'user'=>array(
			// enable cookie-based authentication
			'class' => 'WebUser',
			'allowAutoLogin'=>true,
		),
		'booster' => array(
            'class' => 'ext.yiibooster.components.Booster',
        ),
		// uncomment the following to enable URLs in path-format
		
		'urlManager'=>array(
			'urlFormat'=>'path',
			'showScriptName' => false,
			'rules'=>array(
				'<controller:\w+>/<id:\d+>'=>'<controller>/view',
				'<controller:\w+>/<action:\w+>/<id:\d+>'=>'<controller>/<action>',
				'<controller:\w+>/<action:\w+>'=>'<controller>/<action>',
			),
		),
		
		'db' => require(dirname(__FILE__).'/../../confgs/dbconfgs.php'),
		// uncomment the following to use a MySQL database
		/*
		'db'=>array(
			'connectionString' => 'mysql:host=localhost;dbname=testdrive',
			'emulatePrepare' => true,
			'username' => 'root',
			'password' => '',
			'charset' => 'utf8',
		),
		*/
		'errorHandler'=>array(
			// use 'site/error' action to display errors
			//'errorAction'=>'site/error',
		),
		'log'=>array(
			'class'=>'CLogRouter',
			'routes'=>array(
				array(
					'class'=>'CFileLogRoute',
					'levels'=>'error, warning',
				),
				// uncomment the following to show log messages on web pages
				/*
				array(
					'class'=>'CWebLogRoute',
				),
				*/
			),
		),
	),

	// application-level parameters that can be accessed
	// using Yii::app()->params['paramName']
	'params'=>array(
		'mvp' => require(dirname(__FILE__).'/mvp.php'),
		'adminEmail'=>'webmaster@example.com',
		'all_roles' => require(dirname(__FILE__).'/../../confgs/roles.php'),
		'access_right' => require(dirname(__FILE__).'/../../confgs/access_rights.php'),	
		'managed' => require(dirname(__FILE__).'/../../confgs/managed.php'),		
		'account_type' => require(dirname(__FILE__).'/../../confgs/account_type.php'),		
		'hotels' => require(dirname(__FILE__).'/../../confgs/hotels.php'),		
		'remarks' => require(dirname(__FILE__).'/../../confgs/remarks.php'),	
		'fit_series' => require(dirname(__FILE__).'/../../confgs/fit_series.php'),
		'rate_code' => require(dirname(__FILE__).'/../../confgs/rate_code.php'),
		'city' => require(dirname(__FILE__).'/../../confgs/city.php'),
		'country' => require(dirname(__FILE__).'/../../confgs/country.php'),
		'nationality' => require(dirname(__FILE__).'/../../confgs/nationality.php'),
		'account_type2' => require(dirname(__FILE__).'/../../confgs/account_type2.php'),		
		'sales_type' => require(dirname(__FILE__).'/../../confgs/sales_type.php'),		
		'objective' => require(dirname(__FILE__).'/../../confgs/objective.php'),	
		'market' => require(dirname(__FILE__).'/../../confgs/market.php'),	
		'expected_result' => require(dirname(__FILE__).'/../../confgs/expected_result.php'),
		'dept'=> require(dirname(__FILE__).'/../../confgs/dept.php'),
		'user_group' => require(dirname(__FILE__).'/../../confgs/user_group.php'),
		'module' => require(dirname(__FILE__).'/../../confgs/module.php'),
		'list_view_module' => require(dirname(__FILE__).'/../../confgs/list_view_module.php'),
		'menu' => require(dirname(__FILE__).'/../../confgs/menu.php'),
		'brand' => require(dirname(__FILE__).'/../../confgs/brand.php'),
		'week' => require(dirname(__FILE__).'/../../confgs/week.php'),
		'month' => require(dirname(__FILE__).'/../../confgs/month.php'),
		'icon' => require(dirname(__FILE__).'/../../confgs/icon.php'),
	),
);
