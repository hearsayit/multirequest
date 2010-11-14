<?php

/**
 * @see http://code.google.com/p/multirequest
 * @author Barbushin Sergey http://www.linkedin.com/in/barbushin
 *
 */
class MultiRequest_Handler {
	
	protected $timeLimit = 60;
	protected $connectionsLimit = 60;
	protected $defaultRequestsOptions = array();
	protected $queue;
	protected $total_bytes_transfered;
	protected $total_requested_finished;
	protected $exec_progres_handler;
	protected $onRequestCompleteCallbacks = array();
	protected $isActive;
	protected $activeRequests = array();

	public function __construct() {
		$this->queue = new MultiRequest_Queue();
	}

	public function setConnectionsLimit($connectionsCount) {
		$this->connectionsLimit = $connectionsCount;
	}

	public function setTimeLimit($timeLimit) {
		$this->timeLimit = $timeLimit;
	}

	public function addDefaultRequestsOption($optionName, $value) {
		$this->defaultRequestsOptions[$optionName] = $value;
	}

	public function addOnRequestCompleteCallback($callback) {
		if(!is_callable(($callback))) {
			throw new Exception('Callback is not callable');
		}
		$this->onRequestCompleteCallbacks[] = $callback;
	}

	public function getRequestsInQueueCount() {
		return $this->queue->count();
	}

	public function getActiveRequestsCount() {
		return count($this->activeRequests);
	}

	public function pushRequestToQueue(MultiRequest_Request $request) {
		$this->queue->push($request);
	}

	protected function sendRequestToMultiCurl($mcurlHandle, MultiRequest_Request $request) {
		foreach($this->defaultRequestsOptions as $option => $value) {
			$request->setCurlOption($option, $value);
		}
		curl_multi_add_handle($mcurlHandle, $request->getCurlHandle());
	}

	protected function handleCompleteRequest($mcurlHandle, MultiRequest_Request $request) {
		curl_multi_remove_handle($mcurlHandle, $request->getCurlHandle());
		$request->notifyIsComplete($this);
		foreach($this->onRequestCompleteCallbacks as $callback) {
			call_user_func_array($callback, array($request, $this));
		}
	}

	public function exec() {
		if($this->isActive) {
			return;
		}
		$this->isActive = true;
		
		try {
			
			$oldTimeLimit = ini_get('max_execution_time');
			set_time_limit(0);
			$startTime = time();
			
			$mcurlHandle = curl_multi_init();
			$mcurlStatus = null;
			$mcurlIsActive = false;
			
			do {
				
				if(count($this->activeRequests) < $this->connectionsLimit) {
					for($i = $this->connectionsLimit - count($this->activeRequests); $i; $i --) {
						$request = $this->queue->pop();
						if($request) {
							$this->sendRequestToMultiCurl($mcurlHandle, $request);
							$this->activeRequests[$request->getId()] = $request;
						}
					}
				}
				
				$mcurlStatus = curl_multi_exec($mcurlHandle, $mcurlIsActive);
				if($mcurlIsActive && curl_multi_select($mcurlHandle, 3) == -1) {
					throw new Exception('There are some errors in multi curl requests');
				}
				
				$completeCurlInfo = curl_multi_info_read($mcurlHandle);
				if($completeCurlInfo !== false) {
					$completeRequestId = MultiRequest_Request::getRequestIdByCurlHandle($completeCurlInfo['handle']);
					$completeRequest = $this->activeRequests[$completeRequestId];
					unset($this->activeRequests[$completeRequestId]);
					$this->handleCompleteRequest($mcurlHandle, $completeRequest);
					$mcurlIsActive = true;
				}
				
				if($this->timeLimit && time() - $startTime > $this->timeLimit) {
					set_time_limit($oldTimeLimit);
					throw new Exception('Exec time limit expired');
				}
			
			}
			while($mcurlStatus === CURLM_CALL_MULTI_PERFORM || $mcurlIsActive);
		
		}
		catch(Exception $exception) {
		}
		set_time_limit($oldTimeLimit);
		$this->isActive = false;
		curl_multi_close($mcurlHandle);
		
		if(!empty($exception)) {
			throw $exception;
		}
	}
}