<?php
/**
 * Created by PhpStorm.
 * User: agent
 * Date: 05.10.2015
 * Time: 21:49
 */
$USER = null;
// получаем юзера
session_start();
if (isset($config[ 'httpauth' ])) {
	$valid_passwords = array();
	foreach ($config[ 'users' ] as $user) {
		$conf = unserialize(file_get_contents($_SERVER[ 'DOCUMENT_ROOT' ] . "/data/users/$user/config"));
		$valid_passwords[ $user ] = array($conf[ 'httpauth' ][ 1 ], $conf[ 'httpauth' ][ 2 ]);
	}
	$valid_users = array_keys($valid_passwords);
	if (is_null($_SERVER[ 'PHP_AUTH_USER' ])) {
		if (is_null($_SESSION[ 'PHP_AUTH_USER' ]))
			$user = $_SESSION[ 'PHP_AUTH_USER' ];
	} else
		$user = $_SERVER[ 'PHP_AUTH_USER' ];

	if (is_null($_SERVER[ 'PHP_AUTH_PW' ])) {
		if (is_null($_SESSION[ 'PHP_AUTH_PW' ]))
			$pass = $_SESSION[ 'PHP_AUTH_PW' ];
	} else
		$pass = $_SERVER[ 'PHP_AUTH_PW' ];
	$validated = (in_array($user, $valid_users))&&($pass == $valid_passwords[ $user ][0]);
	$validated_stat = (in_array($user, $valid_users))&&($pass == $valid_passwords[ $user ][1]);
	if ($validated || $validated_stat) {
		$USER = $user;
		$config = unserialize(file_get_contents($_SERVER[ 'DOCUMENT_ROOT' ] . "/data/users/$USER/config"));
		if ($validated_stat)
			if (strpos($_SERVER['REQUEST_URI'], '/stats/')===false)
				header('location: /stats/');
	} else {
		header('WWW-Authenticate: Basic realm="ACCESS AREA!"');
		header('HTTP/1.0 401 Unauthorized');
		die ("Not authorized");
	}
}