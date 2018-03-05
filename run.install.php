<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Chisinau');

define('BASEPATH', dirname(__FILE__));
include BASEPATH.'/Cron.php';
include BASEPATH.'/StatsD.php';

$DB['host'] = 'localhost';
$DB['user'] = 'calls';
$DB['pass'] = '';
$DB['name'] = 'asteriskcdrdb';
$DB['table'] = 'cdr';

$Company = 'company';
$Grafana_Host = '';

/*

Add mysql user
CREATE USER 'calls'@'localhost' IDENTIFIED BY PASSWORD '*7A7E9D37C849086D1C98F1E36C0AD877D326EE47';
GRANT SELECT ON `asteriskcdrdb`.* TO 'calls'@'localhost';
*/


$mysql = new PDO("mysql:host=".$DB['host'].";port=3306;dbname=".$DB['name'].";charset=UTF8;", $DB['user'], $DB['pass'], array(PDO::ATTR_PERSISTENT=>false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
$mysql->query("SET NAMES utf8;");


$unixtimestamp = intval(strtotime(date("Y-m-d H:i")) - 1800);

$sth = $mysql->prepare('SELECT ADDTIME(calldate,duration) as calldate,duration,billsec,disposition,channel,dstchannel FROM '.$DB['table'].' WHERE ADDTIME(calldate,duration) >= :calldate ORDER BY calldate ASC');
$sth->bindvalue(':calldate', date("Y-m-d H:i", $unixtimestamp), PDO::PARAM_STR);
$sth->execute();

$result = $sth->fetchAll(PDO::FETCH_ASSOC);

//var_dump($result);
$statsd = new StatsD($Grafana_Host, '2003', $unixtimestamp);
$cron = new Cron($statsd, $Company);
$cron->calls($result);
$statsd->close();
?>
