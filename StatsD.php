<?php

class StatsD {
	private $host;
	private $port;
	private $connection=null;
	private $unixtimestamp=null;
	private $bytes_sent=0;
	private $dry_run=null;
	
	public function __construct($host, $port, $unixtimestamp=0, $dry_run=false) {
		$this->host = $host;
		$this->port = $port;
		$this->unixtimestamp = $unixtimestamp;
		$this->dry_run = $dry_run;
	}
	
	public function sendToGraphite($key,$value) {
		$data = $key." ".(!empty($value)?$value:0)." ".$this->unixtimestamp.PHP_EOL;
		return $this->sendData($data); 
	}
	
	private function connect() {
		if(!$this->connection) {
			$this->connection = fsockopen($this->host, $this->port, $errno, $errstr);
			if($this->connection===false) {
				die($errno.", ".$errstr.", ".$this->connection);
			}
		}
	}
	public function close() {
		if($this->connection) {
			fclose($this->connection);
			echo "\n bytes sent ".$this->bytes_sent."\n";
		}
	}
	
	private function sendData($data) {
		printf("%s [%s]\n", trim($data), date("d-m-Y H:i", $this->unixtimestamp));
		if($this->dry_run==true) {
			return $data;
		}
		if($this->connection==null) {
			$this->connect();
		}
		
		$bytes = fwrite($this->connection, $data);
		$this->bytes_sent+=$bytes;
		return $data;
	}
}