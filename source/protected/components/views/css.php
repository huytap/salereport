<!DOCTYPE html>
<html lang="en">

<head>
    <script src="<?php echo Yii::app()->baseUrl?>/js/jquery.min.js"></script>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="robots" content="NOINDEX, NOFOLLOW">
    <title>Ho Tram Resort</title>
    <!-- MetisMenu CSS -->
    <link href="<?php echo Yii::app()->baseUrl?>/css/metisMenu.min.css" rel="stylesheet">

    <!-- Timeline CSS -->
    <link href="<?php echo Yii::app()->baseUrl?>/css/timeline.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?php echo Yii::app()->baseUrl?>/css/sb-admin-2.css" rel="stylesheet">

    <!-- Morris Charts CSS -->
    <link href="<?php echo Yii::app()->baseUrl?>/css/morris.css" rel="stylesheet">

    <!-- Custom Fonts -->
    <link href="<?php echo Yii::app()->baseUrl?>/css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" type="text/css" href="<?php echo Yii::app()->baseUrl?>/css/style.css">
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
    <link href="<?php echo Yii::app()->baseUrl?>/css/ui-lightness/jquery-ui-1.10.3.custom.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo Yii::app()->request->baseUrl; ?>/chosen/chosen.css">
    <style type="text/css">
        #page-wrapper{margin:0;}
        #loading{
            display: none;
        }
        .dropdown .dropdown-menu{z-index: 999999;}
    </style>
    <script type='text/javascript'>
        var isCtrl = false;
        document.onkeyup=function(e)
        {
        if(e.which == 17)
        isCtrl=false;
        }
        document.onkeydown=function(e)
        {
        if(e.which == 123)
        isCtrl=true;
        if (((e.which == 85) || (e.which == 65) || (e.which == 88) || (e.which == 67) || (e.which == 86) || (e.which == 2) || (e.which == 3) || (e.which == 123) || (e.which == 83)) && isCtrl == true)
        {
        alert('This is Function Disabled');
        return false;
        }
        }
        // right click code
        var isNS = (navigator.appName == "Netscape") ? 1 : 0;
        if(navigator.appName == "Netscape") document.captureEvents(Event.MOUSEDOWN||Event.MOUSEUP);
        function mischandler(){
            alert('This is Function Disabled');
        return false;
        }
        function mousehandler(e){
        var myevent = (isNS) ? e : event;
        var eventbutton = (isNS) ? myevent.which : myevent.button;
        if((eventbutton==2)||(eventbutton==3)) return false;
        }
        document.oncontextmenu = mischandler;
        document.onmousedown = mousehandler;
        document.onmouseup = mousehandler;
        //select content code disable  alok goyal
        function killCopy(e){
        return false
        }
        function reEnable(){
        return true
        }
        document.onselectstart=new Function ("return false")
        if (window.sidebar){
        document.onmousedown=killCopy
        document.onclick=reEnable
        }

    </script>
</head>