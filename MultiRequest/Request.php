<?php

/**
 * @see http://code.google.com/p/multirequest
 * @author Barbushin Sergey http://www.linkedin.com/in/barbushin
 *
 */
class MultiRequest_Request {
	
	protected $url;
	protected $curlHandle;
	protected $headers = array('Expect:');
	protected $postData;
	protected $curlOptions = array(CURLOPT_HEADER => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_FOLLOWLOCATION => 3, CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_SSL_VERIFYHOST => 0);
	protected $curlInfo;
	protected $encoding = 'utf8';
	protected $callbacks = array();
	protected $responseHeaders = array();
	protected $responseContent;

	public function __construct($url) {
		$this->url = $url;
	}

	public function addHeader($header) {
		$this->headers[] = $header;
	}

	public function setPostData($postData) {
		$this->postData = $postData;
	}

	public function setPostVar($var, $value) {
		$this->postData[$var] = $value;
	}

	public function __set($var, $value) {
		$this->setPostVar($var, $value);
	}

	public function curlSetOpt($option, $value) {
		$this->curlOptions[$option] = $value;
	}

	public function setCookieStorage($filepath) {
		$this->curlOptions[CURLOPT_COOKIEJAR] = $filepath;
		$this->curlOptions[CURLOPT_COOKIEFILE] = $filepath;
	}

	protected function initCurlHandle() {
		$curlHandle = curl_init($this->url);
		$curlOptions = $this->curlOptions;
		$curlOptions[CURLINFO_HEADER_OUT] = true;
		
		if($this->headers) {
			$curlOptions[CURLOPT_HTTPHEADER] = $this->headers;
		}
		if($this->postData) {
			$postData = str_replace('+', '%20', http_build_query($this->postData));
			if($this->encoding != 'utf8') {
				$postData = mb_convert_encoding($postData, $this->encoding, 'UTF-8');
			}
			$curlOptions[CURLOPT_POST] = 1;
			$curlOptions[CURLOPT_POSTFIELDS] = $postData;
		}
		
		curl_setopt_array($curlHandle, $curlOptions);
		return $curlHandle;
	}

	public function setCurlOption($optionName, $value) {
		$this->curlOptions[$optionName] = $value;
	}

	public function getId() {
		return self::getRequestIdByCurlHandle($this->curlHandle);
	}

	public static function getRequestIdByCurlHandle($curlHandle) {
		return substr((string) $curlHandle, 13);
	}

	public function getUrl() {
		return $this->url;
	}

	public function getCurlHandle() {
		if(!$this->curlHandle) {
			$this->curlHandle = $this->initCurlHandle();
		}
		return $this->curlHandle;
	}

	public function getTime() {
		return $this->curlInfo['total_time'];
	}

	public function getCode() {
		return $this->curlInfo['http_code'];
	}

	public function addCallback($callback) {
		if(!is_callable($callback)) {
			throw new Exception('Wrong callback');
		}
		$this->callbacks[] = $callback;
	}

	protected function callCallbacks(MultiRequest_Handler $handler) {
		foreach($this->callbacks as $callback)
			call_user_func_array($callback, array($this, $handler));
	}

	public function notifyIsComplete(MultiRequest_Handler $handler) {
		$this->curlInfo = curl_getinfo($this->curlHandle);
		$responseData = curl_multi_getcontent($this->curlHandle);
		
		$this->responseHeaders = $this->parseHeaders(substr($responseData, 0, curl_getinfo($this->curlHandle, CURLINFO_HEADER_SIZE)));
		$this->responseContent = substr($responseData, curl_getinfo($this->curlHandle, CURLINFO_HEADER_SIZE));
		curl_close($this->curlHandle);
		$this->callCallbacks($handler);
	}

	public function getCurlInfo() {
		return $this->curlInfo;
	}

	public static function parseHeaders($headersString, $associative = false) {
		$headers = array();
		preg_match_all('/\n\s*((.*?)\s*\:\s*(.*?))[\r\n]/', $headersString, $m);
		foreach($m[1] as $i => $header) {
			if($associative) {
				$headers[$m[2][$i]] = $m[3][$i];
			}
			else {
				$headers[] = $header;
			}
		}
		return $headers;
	}

	public function getRespopnseCookies(&$deleted) {
		$cookies = array();
		$deleted = array();
		foreach($this->getResponseHeaders() as $header) {
			if(preg_match('/^Set-Cookie:\s*(.*?)=(.*?);/i', $header, $m)) {
				if($m[2] == 'deleted') {
					$deleted[] = $m[1];
				}
				else {
					$cookies[$m[1]] = $m[2];
				}
			}
		}
		return $cookies;
	}

	public function getRequestHeaders() {
		return $this->curlInfo ? $this->parseHeaders($this->curlInfo['request_header']) : $this->parseHeaders(implode("\n", $this->headers));
	}

	public function getResponseHeaders() {
		return $this->responseHeaders;
	}

	public function getContent() {
		return $this->responseContent;
	}
}
