<?php
return array(
	/*"user" => "user",
	"admin" => "admin",
	"superadmin" => "superadmin",*/
	"contact" => array('admin', 'view','create', 'update', 'delete', 'import', 'report'),
	//"report" => array('admin', 'view','create', 'update', 'delete', 'import', 'staffasst'),
	"report" => array('admin', 'view','create', 'update', 'delete', 'import'),
	//"staffasst" => array('admin', 'view','create', 'update', 'delete'),
	//"incident" => array('admin', 'view','create', 'update', 'delete', 'import'),
	"users" => array('admin', 'view','create', 'update', 'delete', 'history'),
	"role" => array('admin', 'view','create', 'update', 'delete'),
);
?>