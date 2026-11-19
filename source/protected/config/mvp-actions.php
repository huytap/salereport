<?php

$accountRead = array('admin','view','lookup','contacts');
$accountWrite = array_merge($accountRead,array('create','update','contactcreate','contactupdate'));
$taskRead = array('admin','view','calendar');
$taskWrite = array_merge($taskRead,array('create','update','complete','cancel','start','reschedule'));
$reminder = array('admin','summary','read');
return array(
    'superadmin' => ActionAccess::catalog(),
    'admin' => array(
        'contact'=>array('admin','view'), 'report'=>array('admin','view'),
        'account'=>array_merge($accountWrite,array('archive','assign','contactarchive','assignpreview')),
        'task'=>array_merge($taskWrite,array('reopen')), 'reminder'=>$reminder,
        'customerimport'=>array('admin','view','preview','commit'),
    ),
    'users' => array('contact'=>array('admin','view'),'report'=>array('admin','view'),
        'account'=>$accountWrite,'task'=>$taskWrite,'reminder'=>$reminder),
    'reservation' => array('contact'=>array('admin','view'),'account'=>$accountRead),
    'fo' => array(),
    'Contact DB' => array('contact'=>array('admin','view'),'account'=>$accountRead),
);
