<?php

define('BASEPATH', dirname(__FILE__));
include BASEPATH.'/config.php';
include BASEPATH.'/Cron.php';
include BASEPATH.'/StatsD.php';

$mysql = new PDO("mysql:host=".$DB['host'].";port=3306;dbname=".$DB['name'].";charset=UTF8;", $DB['user'], $DB['pass'], array(PDO::ATTR_PERSISTENT=>false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
$mysql->query("SET NAMES utf8;");

$unixtimestamp_now = strtotime(date("Y-m-d H:i"));
$unixtimestamp = intval($unixtimestamp_now - 1800);

$unixtimestamp_to = strtotime(date("Y-m-d"));
$unixtimestamp_from = $unixtimestamp_to - intval(24*31*3600);

for($i = $unixtimestamp_from; $i <= $unixtimestamp_to; $i+=1800) {
	$from = $i;
	$to = $i+1800;

	$statsd = new StatsD($grafana_host, '2003', $from, $dry_run);
	$cron = new Cron($statsd, $mysql, $company, $DB['table']);
	$cron->calls($from, $to);
	$statsd->close();
}
?>
