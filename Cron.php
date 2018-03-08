<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cron {
	private $statsd = null;
	private $company_tag = null;
	private $mysql = null;
	private $db_table = null;
	
	public function __construct($statsd, $mysql, $company_tag='null', $db_table='asteriskcdrdb') {
		$this->statsd = $statsd;
		$this->company_tag = $company_tag;
		$this->mysql = $mysql;
		$this->db_table = $db_table;
	}
	
	public function calls($from, $to) {
		$this->print_stdout("Calculate calls for interval ".date("Y-m-d H:i", $from)." - ".date("Y-m-d H:i", $to));
		$query = 'SELECT ADDTIME(calldate,duration) as calldate,duration,billsec,disposition,channel,dstchannel FROM '.$this->db_table.' WHERE ADDTIME(calldate,duration) >= :from AND ADDTIME(calldate,duration) < :to ORDER BY calldate ASC';
		$sth = $this->mysql->prepare($query);
		$sth->bindvalue(':from', date("Y-m-d H:i", $from), PDO::PARAM_STR);
		$sth->bindvalue(':to', date("Y-m-d H:i", $to), PDO::PARAM_STR);
		$sth->execute();
		
		$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
		
		if(!empty($rows)) {
			$this->print_csv($rows);
		}
		
		/* 
		 * Am facut prin ciclu pentru ca la un numar mic de rinduri 
		 * e mai rapit sa le numeri in php decit sa faci request la db, 
		 * citeodata pentru 0 rows.
		 */
		$totals = array(
			'average_waiting' => array(),
			'average_duration' => array(),
			'duration' => array(),
			'count' => array(
				'by_company' => array(),
				'by_operator' => array()
			)
		);
		
		
		if (!empty($rows)) {
			foreach ($rows as $key => $item) {
				
				$call_validation = FALSE;
				$operator        = FALSE;
				
				/*-------------
				 * INBOUND cals
				 --------------*/
				preg_match("/\SIP\/((?:[0-9]{3}))-/im", $item['dstchannel'], $manager_dst);
				if (isset($manager_dst[1]) && is_numeric($manager_dst[1]) && $manager_dst[1] < 999) {
					$call_validation = 'inbound';
					$operator        = $manager_dst[1];
					
					//echo "INBOUND - Manager: ".$manager_dst[1].'\n';
				}
				/* -----> */
				
				
				/*-------------
				 * OUTBOUND cals
				 --------------*/
				preg_match("/\SIP\/((?:[0-9]{3}))-/im", $item['channel'], $manager_src);
				if (isset($manager_src[1]) && is_numeric($manager_src[1]) && $manager_src[1] < 999) {
					$call_validation = 'outbound';
					$operator        = $manager_src[1];
					
					//echo "OUTBOUND - Manager: ".$manager_src[1].'\n';
				}
				/* -----> */
				
				
				
				if ($call_validation !== FALSE && in_array($call_validation, array(
					'inbound',
					'outbound'
				)) && $operator !== FALSE && is_numeric($operator) && !empty($item['dstchannel'])) {
					/* VALID CALL */
					
					// COUNT - Total by COMPANY
					$totals['count']['by_company']['total']['total']          = isset($totals['count']['by_company']['total']['total']) ? $totals['count']['by_company']['total']['total'] + 1 : 1;
					// COUNT - Total by COMPANY and call_type
					$totals['count']['by_company'][$call_validation]['total'] = isset($totals['count']['by_company'][$call_validation]['total']) ? $totals['count']['by_company'][$call_validation]['total'] + 1 : 1;
					
					// COUNT - Total by OPERATOR
					$totals['count']['by_operator'][$operator]['total']['total']          = isset($totals['count']['by_operator'][$operator]['total']['total']) ? $totals['count']['by_operator'][$operator]['total']['total'] + 1 : 1;
					// COUNT - Total by OPERATOR and call_type
					$totals['count']['by_operator'][$operator][$call_validation]['total'] = isset($totals['count']['by_operator'][$operator][$call_validation]['total']) ? $totals['count']['by_operator'][$operator][$call_validation]['total'] + 1 : 1;
					
					/*-------------
					 * DISPOSITION
					 --------------*/
					if ($item['disposition'] == "ANSWERED") { // ------------------------------------------------------ ANSWERED
						//$totals['duration']['answered'] += $item['duration'];// increment duration
						
						$totals['average_waiting'][$operator][$call_validation]['time']  = isset($totals['average_waiting'][$operator][$call_validation]['time']) ? $totals['average_waiting'][$operator][$call_validation]['time'] + ($item['duration'] - $item['billsec']) : $item['duration'] - $item['billsec']; // increment duration
						$totals['average_waiting'][$operator][$call_validation]['count'] = isset($totals['average_waiting'][$operator][$call_validation]['count']) ? $totals['average_waiting'][$operator][$call_validation]['count'] + 1 : 1;
						
						$totals['average_duration'][$operator][$call_validation]['time']  = isset($totals['average_duration'][$operator][$call_validation]['time']) ? $totals['average_duration'][$operator][$call_validation]['time'] + $item['billsec'] : $item['billsec']; // increment duration
						$totals['average_duration'][$operator][$call_validation]['count'] = isset($totals['average_duration'][$operator][$call_validation]['count']) ? $totals['average_duration'][$operator][$call_validation]['count'] + 1 : 1;
						
						$totals['duration']['by_company']['total']                      = isset($totals['duration']['by_company']['total']) ? $totals['duration']['by_company']['total'] + $item['billsec'] : $item['billsec'];
						$totals['duration']['by_company'][$call_validation]             = isset($totals['duration']['by_company'][$call_validation]) ? $totals['duration']['by_company'][$call_validation] + $item['billsec'] : $item['billsec']; // increment duration
						$totals['duration']['by_operator'][$operator][$call_validation] = isset($totals['duration']['by_operator'][$operator][$call_validation]) ? $totals['duration']['by_operator'][$operator][$call_validation] + $item['billsec'] : $item['billsec']; // increment duration
						$totals['duration']['by_operator'][$operator]['total']          = isset($totals['duration']['by_operator'][$operator]['total']) ? $totals['duration']['by_operator'][$operator]['total'] + $item['billsec'] : $item['billsec']; // increment duration
						
						
						// COUNT - Total answered cals for company
						$totals['count']['by_company']['total']['answered']          = isset($totals['count']['by_company']['total']['answered']) ? $totals['count']['by_company']['total']['answered'] + 1 : 1;
						// COUNT - Total answered cals by call_type for company
						$totals['count']['by_company'][$call_validation]['answered'] = isset($totals['count']['by_company'][$call_validation]['answered']) ? $totals['count']['by_company'][$call_validation]['answered'] + 1 : 1;
						
						
						// COUNT - Total answered cals for operator
						$totals['count']['by_operator'][$operator]['total']['answered']          = isset($totals['count']['by_operator'][$operator]['total']['answered']) ? $totals['count']['by_operator'][$operator]['total']['answered'] + 1 : 1;
						// COUNT - Total answered cals by call_type for operator
						$totals['count']['by_operator'][$operator][$call_validation]['answered'] = isset($totals['count']['by_operator'][$operator][$call_validation]['answered']) ? $totals['count']['by_operator'][$operator][$call_validation]['answered'] + 1 : 1;
						
					} else if ($item['disposition'] == "NO ANSWER") { // ----------------------------------------------- NO ANSWER
						//$totals['duration']['no_answer'] += $item['duration'];// increment duration
						
						
						
						// COUNT - Total no_answer cals for company
						$totals['count']['by_company']['total']['no_answer']          = isset($totals['count']['by_company']['total']['no_answer']) ? $totals['count']['by_company']['total']['no_answer'] + 1 : 1;
						// COUNT - Total answered cals by call_type for company
						$totals['count']['by_company'][$call_validation]['no_answer'] = isset($totals['count']['by_company'][$call_validation]['no_answer']) ? $totals['count']['by_company'][$call_validation]['no_answer'] + 1 : 1;
						
						
						// COUNT - Total no_answer cals for operator
						$totals['count']['by_operator'][$operator]['total']['no_answer']          = isset($totals['count']['by_operator'][$operator]['total']['no_answer']) ? $totals['count']['by_operator'][$operator]['total']['no_answer'] + 1 : 1;
						// COUNT - Total no_answer cals by call_type  for operator
						$totals['count']['by_operator'][$operator][$call_validation]['no_answer'] = isset($totals['count']['by_operator'][$operator][$call_validation]['no_answer']) ? $totals['count']['by_operator'][$operator][$call_validation]['no_answer'] + 1 : 1;
						
						
					} else if ($item['disposition'] == "BUSY") { // ------------------------------------------------------ BUSY
						//$totals['duration']['busy'] += $item['duration'];// increment duration
						
						
						
						// COUNT - Total busy cals for company
						$totals['count']['by_company']['total']['busy']          = isset($totals['count']['by_company']['total']['busy']) ? $totals['count']['by_company']['total']['busy'] + 1 : 1;
						// COUNT - Total answered cals by call_type for company
						$totals['count']['by_company'][$call_validation]['busy'] = isset($totals['count']['by_company'][$call_validation]['busy']) ? $totals['count']['by_company'][$call_validation]['busy'] + 1 : 1;
						
						// COUNT - Total busy cals for operator
						$totals['count']['by_operator'][$operator]['total']['busy']          = isset($totals['count']['by_operator'][$operator]['total']['busy']) ? $totals['count']['by_operator'][$operator]['total']['busy'] + 1 : 1;
						// COUNT - Total busy cals for by call_type  operator
						$totals['count']['by_operator'][$operator][$call_validation]['busy'] = isset($totals['count']['by_operator'][$operator][$call_validation]['busy']) ? $totals['count']['by_operator'][$operator][$call_validation]['busy'] + 1 : 1;
						
						
					} else if ($item['disposition'] == "FAILED") { // ------------------------------------------------------ FAILED
						//$totals['duration']['failed'] += $item['duration'];// increment duration
						
						// COUNT - Total failed cals for company
						$totals['count']['by_company']['total']['failed']          = isset($totals['count']['by_company']['total']['failed']) ? $totals['count']['by_company']['total']['failed'] + 1 : 1;
						// COUNT - Total answered cals by call_type for company
						$totals['count']['by_company'][$call_validation]['failed'] = isset($totals['count']['by_company'][$call_validation]['failed']) ? $totals['count']['by_company'][$call_validation]['failed'] + 1 : 1;
						
						// COUNT - Total failed cals for operator
						$totals['count']['by_operator'][$operator]['total']['failed']          = isset($totals['count']['by_operator'][$operator]['total']['failed']) ? $totals['count']['by_operator'][$operator]['total']['failed'] + 1 : 1;
						// COUNT - Total failed cals by call_type  for operator
						$totals['count']['by_operator'][$operator][$call_validation]['failed'] = isset($totals['count']['by_operator'][$operator][$call_validation]['failed']) ? $totals['count']['by_operator'][$operator][$call_validation]['failed'] + 1 : 1;
						
					}
					/*----->*/
					
				}
				
			}
		}
		
		
		
		foreach ($totals['average_waiting'] as $key => $value) {
			if (isset($value['outbound']) && is_array($value['outbound'])) {
				$avg_w_out                                   = $value['outbound']['time'] / $value['outbound']['count'];
				$totals['average_waiting'][$key]['outbound'] = $avg_w_out;
			}
			
			if (isset($value['inbound']) && is_array($value['inbound'])) {
				$avg_w_in                                   = $value['inbound']['time'] / $value['inbound']['count'];
				$totals['average_waiting'][$key]['inbound'] = $avg_w_in;
			}
		}
		
		foreach ($totals['average_duration'] as $key => $value) {
			if (isset($value['outbound']) && is_array($value['outbound'])) {
				$avg_d_out                                    = $value['outbound']['time'] / $value['outbound']['count'];
				$totals['average_duration'][$key]['outbound'] = $avg_d_out;
			}
			
			if (isset($value['inbound']) && is_array($value['inbound'])) {
				$avg_d_in                                    = $value['inbound']['time'] / $value['inbound']['count'];
				$totals['average_duration'][$key]['inbound'] = $avg_d_in;
			}
		}
		
		//pr($totals); //die();
		
		// send data to graphiti
		foreach ($totals as $key => $group) {
			foreach ($group as $key2 => $value) {
				if (is_array($value)) {
					foreach ($value as $key3 => $item) {
						if (is_array($item)) {
							foreach ($item as $key4 => $item_f) {
								
								if (is_array($item_f)) {
									foreach ($item_f as $key5 => $item5) {
										$this->statsd->sendToGraphite("calls." . $this->company_tag . ".". $key."." . $key2 . "." . $key3 . "." . $key4 . "." . $key5, $item5);
									}
								} else {
									$this->statsd->sendToGraphite("calls." . $this->company_tag . "." . $key . "." . $key2 . "." . $key3 . "." . $key4, $item_f);
								}
							}
							
						} else {
							$this->statsd->sendToGraphite("calls." . $this->company_tag . "." . $key . "." . $key2 . "." . $key3, $item);
						}
					}
				} else {
					$this->statsd->sendToGraphite("calls." . $this->company_tag . "." . $key . "." . $key2, $value);
				}
			}
		}
		
		//die();
		
// 		// save $reference_point_val
// 		if (isset($totals['count']['by_company']['total']['total']) && $totals['count']['by_company']['total']['total'] > 0) {
// 			$rows_last_item = end($rows);
// 			$this->mysql->update('services', array(
// 				'reference_point_val' => $rows_last_item['calldate']
// 			), array(
// 				'sys_name' => $service_key
// 			));
// 		} else {
// 			$this->mysql->update('services', array(
// 				'reference_point_val' => date('Y-m-d H:i:s')
// 			), array(
// 				'sys_name' => $service_key
// 			));
// 		}
// 		set_log(isset($totals['count']['by_company']['total']['total']) ? $totals['count']['by_company']['total']['total'] : 0, $service_key);
	}
	public function print_stderr($msg) {
		$date = date("d-m-Y h:i");
		file_put_contents('php://stderr', '['.$date.'] '.$msg."\n");
	}
	public function print_stdout($msg) {
		$date = date("d-m-Y h:i");
		echo('['.$date.'] '.$msg."\n");
	}
	public function print_csv($array) {
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
}