<?php

/**
 * @see http://code.google.com/p/multirequest
 * @author Barbushin Sergey http://www.linkedin.com/in/barbushin
 *
 */
class MultiRequest_Handler {

	protected $timeLimit = 60;
	protected $connectionsLimit = 60;
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

		$this->mcurlHandle = curl_multi_init();
		$mcurlStatus = null;
		$mcurlIsActive = false;

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

			$mcurlStatus = curl_multi_exec($this->mcurlHandle, $mcurlIsActive);
			if($mcurlIsActive && curl_multi_select($this->mcurlHandle, 3) == -1) {
				throw new Exception('There are some errors in multi curl requests');
			}

			$completeCurlInfo = curl_multi_info_read($this->mcurlHandle);
			if($completeCurlInfo !== false) {
				$completeRequestId = MultiRequest_Request::getRequestIdByCurlHandle($completeCurlInfo['handle']);
				$completeRequest = $this->activeRequests[$completeRequestId];
				unset($this->activeRequests[$completeRequestId]);
				$this->handleCompleteRequest($completeRequest);
				$mcurlIsActive = true;
			}

			if($this->timeLimit && time() - $startTime > $this->timeLimit) {
				set_time_limit($oldTimeLimit);
				throw new Exception('Exec time limit expired');
			}

		} while( $mcurlStatus === CURLM_CALL_MULTI_PERFORM || $mcurlIsActive);

		set_time_limit($oldTimeLimit);
	}
}