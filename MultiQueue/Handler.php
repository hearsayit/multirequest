<?php

class MultiRequest_Handler {

	public $timeLimit = 60;
	public $connectionsLimit = 60;
	protected $defaultRequestsOptions = array ();
	protected $queue;

	protected $ACTIVE_QUEUE;
	protected $total_bytes_transfered;
	protected $total_requested_finished;
	protected $exec_progres_handler;
	protected $onRequestCompleteCallbacks = array ();

	protected $mcurlHandle;
	protected $activeRequests = array ();

	public function __construct() {
		$this->queue = new MultiRequest_Queue();
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

	public function pushRequestToQueue(MultiRequest_Request $request) {
		$this->queue->push($request);
	}

	protected function sendRequestToMultiCurl(MultiRequest_Request $request) {
		foreach($this->defaultRequestsOptions as $option => $value) {
			$request->setCurlRequestOption($option, $value);
		}
		curl_multi_add_handle($this->mcurlHandle, $request->getCurlHandle());
	}

	public function handleCompleteRequest(MultiRequest_Request $request) {
		$request->notifyIsFinished($this);
		curl_multi_remove_handle($this->mcurlHandle, $request->getCurlHandle());
		foreach($this->onRequestCompleteCallbacks as $callback) {
			call_user_func_array($callback, array ($request, $this));
		}
	}

	public function exec() {
		if($this->activeRequests) {
			throw new Exception('MultiCurl handler already works');
		}

		$oldTimeLimit = ini_get('max_execution_time');
		set_time_limit(0);
		$startTime = time();

		do {
			if(count($this->activeRequests) < $this->connectionsLimit) {
				for($i = $this->connectionsLimit - count($this->activeRequests); $i; $i--) {
					$request = $this->queue->pop();
					if($request) {
						$this->sendRequestToMultiCurl($request);
						$this->activeRequests[$request->getId()] = $request;
					}
				}
			}

			$completeCurlInfo = curl_multi_info_read($this->mcurlHandle);
			if($completeCurlInfo !== false) {
				$completeRequestId = MultiRequest_Request::getRequestIdByCurlHandle($completeCurlInfo['handle']);
				$this->handleCompleteRequest($this->activeRequests[$completeRequestId]);
				unset($this->activeRequests[$completeRequestId]);
			}

			if($this->timeLimit && time() - $startTime > $this->timeLimit) {
				set_time_limit($oldTimeLimit);
				throw new Exception('Exec time limit expired');
			}
			$mcurlStatus = curl_multi_exec($this->mcurlHandle, $mcurlIsActive);
			if(curl_multi_select($this->mcurlHandle, 3) == -1) {
				throw new Exception('There are some errors in multi curl requests');
			}
		} while( $this->activeRequests && ($mcurlStatus === CURLM_CALL_MULTI_PERFORM || $mcurlIsActive));

		set_time_limit($oldTimeLimit);
	}
}