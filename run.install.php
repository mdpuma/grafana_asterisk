<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

define('BASEPATH', dirname(__FILE__));
include BASEPATH.'/Cron.php';
include BASEPATH.'/StatsD.php';

$DB['host'] = '';
$DB['user'] = '';
$DB['pass'] = '';
$DB['name'] = '';
$DB['table'] = 'cdr';


$mysql = new PDO("mysql:host=".$DB['host'].";port=3306;dbname=".$DB['name'].";charset=UTF8;", $DB['user'], $DB['pass'], array(PDO::ATTR_PERSISTENT=>false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
$mysql->query("SET NAMES utf8;");


$unixtimestamp = intval(strtotime(date("Y-m-d H:i")) - 1800);

$sth = $mysql->prepare('SELECT ADDTIME(calldate,duration) as calldate,duration,billsec,disposition,channel,dstchannel FROM '.$DB['table'].' WHERE ADDTIME(calldate,duration) >= :calldate ORDER BY calldate ASC');
$sth->bindvalue(':calldate', date("Y-m-d H:i", $unixtimestamp), PDO::PARAM_STR);
$sth->execute();

$result = $sth->fetchAll(PDO::FETCH_ASSOC);

var_dump($result);
$statsd = new StatsD('', '2003', $unixtimestamp);
$cron = new Cron($statsd);
$cron->calls($result);
$statsd->close();
?>