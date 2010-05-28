<?php

class MultiRequest_Request {

	protected $url;
	protected $curlHandle;
	protected $curlInfo;
	protected $callbacks = array ();
	protected $responseContent;

	public function __construct($url) {
		$this->url = $url;
		$this->initCurlRequest();
	}

	protected function initCurlRequest() {
		$this->curlHandle = curl_init($this->url);
		$this->setCurlRequestOption(CURLOPT_HEADER, 0);
		$this->setCurlRequestOption(CURLOPT_RETURNTRANSFER, 1);
	}

	public function setCurlRequestOption($optionName, $value) {
		curl_setopt($this->curlHandle, $optionName, $value);
	}

	public function getId() {
		return self::getRequestIdByCurlHandle($this->curlHandle);
	}

	public static function getRequestIdByCurlHandle($curlHandle) {
		substr((string) $curlHandle, 13);
	}

	public function getUrl() {
		return $this->url;
	}

	public function getCurlHandle() {
		return $this->curlHandle;
	}

	public function getTime() {
		return $this->curlInfo['total_time'];
	}

	public function getCode() {
		return $this->curlInfo['http_code'];
	}

	public function addCallback($callback) {
		if(is_callable($callback))
			$this->callbacks[] = $callback;
	}

	protected function callCallbacks(MultiRequest_Handler $handler) {
		foreach($this->callbacks as $callback)
			call_user_func_array($callback, array ($this, $handler));
	}

	public function notifyIsFinished(MultiRequest_Handler $handler) {
		$this->curlInfo = curl_getinfo($this->curlHandle);
		$this->responseContent = curl_multi_getcontent($this->curlHandle);
		curl_close($this->curlHandle);
	}

	public function getContent() {
		return $this->responseContent;
	}
}
