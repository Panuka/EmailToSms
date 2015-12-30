<?php
$isAdmin = strpos($_SERVER['REQUEST_URI'], 'admin')>0;
?>

<!doctype html>
<!--[if lt IE 7]>
<html lang="en" class="no-js ie6"><![endif]-->
<!--[if IE 7]>
<html lang="en" class="no-js ie7"><![endif]-->
<!--[if IE 8]>
<html lang="en" class="no-js ie8"><![endif]-->
<!--[if gt IE 8]><!-->
<html lang="en" class="no-js">
<!--<![endif]-->

<head>
	<meta charset="UTF-8">
	<title>Parser</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"/>
	<link rel="shortcut icon" href="favicon.png">
	<link rel="stylesheet" href="/css/bootstrap.css">
	<link rel="stylesheet" href="/assets/style.css">
	<link rel="stylesheet" href="/css/animate.css">
	<link rel="stylesheet" href="/css/font-awesome.min.css">
	<link rel="stylesheet" href="/css/slick.css">
	<link rel="stylesheet" href="/js/rs-plugin/css/settings.css">
	<link rel="stylesheet" href="/css/freeze.css">
	<script type="text/javascript" src="/js/modernizr.custom.32033.js"></script>
	<!--[if lt IE 9]>
	<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
	<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
	<![endif]-->

</head>

<body>

<div class="pre-loader">
	<div class="load-con">
		<img src="/img/freeze/logo.png" class="animated fadeInDown" alt="">

		<div class="spinner">
			<div class="bounce1"></div>
			<div class="bounce2"></div>
			<div class="bounce3"></div>
		</div>
	</div>
</div>

<header>

	<nav class="navbar navbar-default navbar-fixed-top" role="navigation">
		<div class="container">
			<!-- Brand and toggle get grouped for better mobile display -->
			<div class="navbar-header">
				<button type="button" class="navbar-toggle" data-toggle="collapse"
				        data-target="#bs-example-navbar-collapse-1">
					<span class="fa fa-bars fa-lg"></span>
				</button>
				<a class="navbar-brand" href="/">
					<img src="/img/freeze/<?=!$isAdmin?'logo.png':'image.png'?>" alt="" class="logo" style="">
				</a>
			</div>

			<!-- Collect the nav links, forms, and other content for toggling -->
			<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">

				<ul class="nav navbar-nav navbar-right">
					<?php if ($isAdmin) {?>
						<li>
							<a href="/">< Home</a>
						</li>
					<?php } else {?>
					<li>
						<a href="#index">Home</a>
					</li>
					<li>
						<a href="#about">Log</a>
					</li>
					<li>
						<a href="/stats/">Статистика</a>
					</li>
						<?php if ($_SERVER[ 'PHP_AUTH_USER' ]=='admin'):?>
					<li>
						<a href="/admin/">> ADMIN_PANEL</a>
					</li>
						<?php endif;?>
					<?php }?>
					<li>
						<a>[ <?=$_SERVER[ 'PHP_AUTH_USER' ]?> ]</a>
					</li>
					<li>
						<a href="http://logout@xls-prod.cloudapp.net/">Выход</a>
					</li>
				</ul>
			</div>
			<!-- /.navbar-collapse -->
		</div>
		<!-- /.container-->
	</nav>
<div class="main-admin-form" id="index"></div>