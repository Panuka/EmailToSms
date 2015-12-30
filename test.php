<?php

global $config;
$config = unserialize(file_get_contents(__DIR__ . '/data/config'));
require __DIR__.'/vendor/autoload.php';
use Web\Index2;

$user = $config['users'][0];
$config = unserialize(file_get_contents(__DIR__ . "/data/users/$user/config"));
$i = new Index2($config, $user);
echo '<pre>';