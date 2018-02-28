<?php

class StatsD {
	private $host;
	private $port;
	private $connection=null;
	private $unixtimestamp=null;
	private $bytes_sent=0;
	
	public function __construct($host, $port, $unixtimestamp=0) {
		$this->host = $host;
		$this->port = $port;
		$this->unixtimestamp = $unixtimestamp;
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
		fclose($this->connection);
		echo "\n bytes sent ".$this->bytes_sent."\n";
	}
	
	private function sendData($data) {
		if($this->connection==null) {
			$this->connect();
		}
		
		$bytes = fwrite($this->connection, $data);
		$this->bytes_sent+=$bytes;
		return $data;
	}
}