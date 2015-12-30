<?php


use Web\LogRead;

// глобальный конфиг
$config = unserialize(file_get_contents($_SERVER[ 'DOCUMENT_ROOT' ] . '/data/config'));
require $_SERVER[ 'DOCUMENT_ROOT' ] . '/vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'].'/global_head.php';
global $USER;

if (isset($_GET['user']))
	$USER = $_GET['user'];
?>


<!DOCTYPE html>
<html lang="en">
	<head>
		<script>
			logurl = 'ajax/index.php?user=<?=$USER?>';
		</script>
		<meta charset="utf-8">
		<title>DevOOPS</title>
		<meta name="description" content="description">
		<script src="/js/jquery-1.11.1.min.js"></script>
		<script src="/js/bootstrap.min.js"></script>
		<script src="/js/slick.min.js"></script>
		<script src="/js/placeholdem.min.js"></script>
		<script src="/js/rs-plugin/js/jquery.themepunch.plugins.min.js"></script>
		<script src="/js/rs-plugin/js/jquery.themepunch.revolution.min.js"></script>
		<script src="/js/waypoints.min.js"></script>
		<script src="/js/scripts.js"></script>
		<meta name="author" content="DevOOPS">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link href="plugins/bootstrap/bootstrap.css" rel="stylesheet">
		<link href="plugins/jquery-ui/jquery-ui.min.css" rel="stylesheet">
		<link href="http://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css" rel="stylesheet">
		<link href='http://fonts.googleapis.com/css?family=Righteous' rel='stylesheet' type='text/css'>
		<link href="plugins/fancybox/jquery.fancybox.css" rel="stylesheet">
		<link href="plugins/fullcalendar/fullcalendar.css" rel="stylesheet">
		<link href="plugins/xcharts/xcharts.min.css" rel="stylesheet">
		<link href="plugins/select2/select2.css" rel="stylesheet">
		<link href="css/style.css" rel="stylesheet">
		<!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
		<!--[if lt IE 9]>
				<script src="http://getbootstrap.com/docs-assets/js/html5shiv.js"></script>
				<script src="http://getbootstrap.com/docs-assets/js/respond.min.js"></script>
		<![endif]-->
	</head>
<body>
<div id="screensaver">
	<canvas id="canvas"></canvas>
	<i class="fa fa-lock" id="screen_unlock"></i>
</div>
<div id="modalbox">
	<div class="devoops-modal">
		<div class="devoops-modal-header">
			<div class="modal-header-name">
				<span>Basic table</span>
			</div>
			<div class="box-icons">
				<a class="close-link">
					<i class="fa fa-times"></i>
				</a>
			</div>
		</div>
		<div class="devoops-modal-inner">
		</div>
		<div class="devoops-modal-bottom">
		</div>
	</div>
</div>
<header class="navbar">
	<div class="container-fluid expanded-panel">
		<div class="row">
			<div id="logo" class="col-xs-12 col-sm-12">
				<a href="/">Back</a>
			</div>
		</div>
	</div>
</header>
<div id="main" class="container-fluid">
	<div class="row">
		<div id="sidebar-left" class="col-xs-2 col-sm-2">
			<ul class="nav main-menu">
				<li>
					<a href="ajax/dashboard.html" class="ajax-link">
						<i class="fa fa-dashboard"></i>
						<span class="hidden-xs">[ <?=$USER?> ]</span>
					</a>
				</li>
				<li>
					<a href="ajax/" class="active ajax-link">
						<i class="fa fa-dashboard"></i>
						<span class="hidden-xs">Статистика</span>
					</a>
				</li>
				<li>
					<a href="/data/users/<?=$USER?>/log.csv" target="_blank">
						<i class="fa fa-dashboard"></i>
						<span class="hidden-xs">Скачать все данные</span>
					</a>
				</li>
				<li>
					<a href="http://logout@xls-prod.cloudapp.net/" target="_blank">
						<i class="fa fa-dashboard"></i>
						<span class="hidden-xs">Выход</span>
					</a>
				</li>
			</ul>
		</div>
		<div id="content" class="col-xs-12 col-sm-10">
			<div class="preloader">
				<img src="img/devoops_getdata.gif" class="devoops-getdata" alt="preloader"/>
			</div>
			<div id="ajax-content"></div>
		</div>
	</div>
</div>
<script src="plugins/jquery/jquery-2.1.0.min.js"></script>
<script src="plugins/jquery-ui/jquery-ui.min.js"></script>
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables/dataTables.bootstrap.js"></script>
<script src="plugins/datatables/ZeroClipboard.js"></script>
<script src="plugins/datatables/TableTools.js"></script>
<script src="plugins/bootstrap/bootstrap.min.js"></script>
<script src="plugins/justified-gallery/jquery.justifiedgallery.min.js"></script>
<script src="plugins/tinymce/tinymce.min.js"></script>
<script src="plugins/tinymce/jquery.tinymce.min.js"></script>
<script src="js/devoops.js"></script>
</body>
</html>
