<?php
return array(
	"superadmin" => array(
		"users" => array('admin', 'view','create', 'update', 'delete'),
		"contact" => array('admin', 'view','create', 'update', 'delete', 'import', 'report'),
		"report" => array('admin', 'view','create', 'update', 'delete', 'import'),
		//"incident" => array('admin', 'view','create', 'update', 'delete', 'import'),
	),
	"admin" => array(
		"contact" => array('admin', 'view','create', 'update'),
		"report" => array('admin', 'view','create', 'update'),
		"users" => array('admin', 'view','create', 'update'),
		//"incident" => array('admin', 'view','create', 'update'),
	),
	"user" => array(
		"contact" => array('admin', 'view','create', 'update'),
		"report" => array('admin', 'view','create', 'update'),
		//"incident" => array('admin', 'view','create', 'update')
	),
	
);
?>