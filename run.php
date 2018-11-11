<?php

define('BASEPATH', dirname(__FILE__));

if(!is_file(BASEPATH.'/config.php')) {
	die("Missing ".BASEPATH."/config.php\n");
}

require_once BASEPATH.'/config.php';
require_once BASEPATH.'/Cron.php';
require_once BASEPATH.'/StatsD.php';

$mysql = new PDO("mysql:host=".$DB['host'].";port=3306;dbname=".$DB['name'].";charset=UTF8;", $DB['user'], $DB['pass'], array(PDO::ATTR_PERSISTENT=>false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
$mysql->query("SET NAMES utf8;");

$to = strtotime(date("Y-m-d H:i"));
$from = intval($to - 1800);

$statsd = new StatsD($grafana_host, '2003', $from, $dry_run);
$cron = new Cron($statsd, $mysql, $company, $DB['table']);
$cron->calls($from, $to);
$statsd->close();
