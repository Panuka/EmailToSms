<?php
/**
 * Created by PhpStorm.
 * User: panuka
 * Date: 03.11.15
 * Time: 10:38
 */


use Web\Index;
use Api\APISMS;

require __DIR__.'/vendor/autoload.php';

$sms_key_public='6360d81fbd524589e6a93b558b5fe71c';
$sms_key_private='b84c6c2ac4039a67d2cd41f66df9bfe5';
$URL_GAREWAY='http://atompark.com/api/sms/';
$testMode=false;

$Gateway=new APISMS($sms_key_private,$sms_key_public, $URL_GAREWAY);
$d = $Gateway->execCommad('getUserBalance', array(
	'currency' => 'RUB'
));
var_dump($d['result']['balance_currency']);
