<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Chisinau');

/*
  Add mysql user

CREATE USER 'calls'@'localhost';
SET PASSWORD FOR calls@localhost = PASSWORD('calls');
GRANT SELECT ON `asteriskcdrdb`.* TO 'calls'@'localhost';
*/

$DB['host'] = 'localhost';
$DB['user'] = 'calls';
$DB['pass'] = 'calls';
$DB['name'] = 'asteriskcdrdb';
$DB['table'] = 'cdr';

$company = '';
$grafana_host = '';
$dry_run = false; 


function print_stderr($msg) {
    $date = date("d-m-Y h:i");
    file_put_contents('php://stderr', '['.$date.'] '.$msg."\n");
}
function print_stdout($msg) {
    $date = date("d-m-Y h:i");
    echo('['.$date.'] '.$msg."\n");
} 
function print_csv($array) {
	foreach($array[0] as $i => $j) {
		printf("%s, ", $i);
	}
	print "\n";
	foreach($array as $i) {
		foreach($i as $j) {
			printf("%s, ", $j);
		}
		print "\n";
	}
}
