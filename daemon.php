<?php
/**
 * Created by PhpStorm.
 * User: agent
 * Date: 03.10.2015
 * Time: 21:16
 */
global $config;
$config = unserialize(file_get_contents(__DIR__ . '/data/config'));
require __DIR__.'/vendor/autoload.php';
use Web\Index;

define("ROOT_PATH", __DIR__ );

$users = $config['users'];
foreach ($users as $user) {
	$config = unserialize(file_get_contents(__DIR__ . "/data/users/$user/config"));
	if (!$config[ 'on' ] || !isset($config[ 'on' ]))
		continue;
	ob_start();
	$date = \date('d/m/y H:i');
	echo "\n== Запуск скрипта [$date] ==\n";

	Index::$api_private = $config[ 'priv_key' ];
	Index::$api_public = $config[ 'pub_key' ];

	$d = new Index($config, $user);
	$d->checkBalance();
	$d->sendMessages();
	$d->writeStats();
	$h = date('G');
	$m = date('i');
	if ($m==30 || $m==0) {
		foreach ($config[ 'report_time' ] as $r)
			if ($h == $r[0] && $m == $r[1])
				$d->sendReport(86400, str_replace("%DATE%", \date('d/m/Y'), $config['mail_theme_1']));
		foreach ($config[ 'report_time_single' ] as $r)
			if ($h == $r[ 0 ]&&$m == $r[ 1 ])
				$d->sendReport(9999999999999, $config['mail_theme_2']);
	}
	$d->END_ACTIVITY();
}
die();