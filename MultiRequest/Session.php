<?php

/**
 * @see http://code.google.com/p/multirequest
 * @author Barbushin Sergey http://www.linkedin.com/in/barbushin
 *
 */
class MultiRequest_Session {
	
	protected $mrHandler;
	protected $cookiesFilepath;
	protected $lastRequest;
	protected $enableAutoReferer;
	protected $defaultHeaders = array();
	protected $defaultCurlOptions = array();
	protected $requestsCallbacks = array();

	public function __construct(MultiRequest_Handler $mrHandler, $cookiesBasedir, $enableAutoReferer = false) {
		$this->mrHandler = $mrHandler;
		$this->cookiesFilepath = tempnam($cookiesBasedir, '_');
		$this->enableAutoReferer = $enableAutoReferer;
	}

	public function setDefaultCurlOptions(array $options) {
		$this->defaultCurlOptions = $options;
	}

	public function setDefaultHeaders(array $headers) {
		$this->defaultHeaders = $headers;
	}

	public function onRequestComplete(MultiRequest_Request $request, MultiRequest_Handler $mrHandler) {
		$this->lastRequest = $request;
		
		$requestId = self::getRequestId($request);
		if($this->requestsCallbacks[$requestId]) {
			call_user_func_array($this->requestsCallbacks[$requestId], array($request, $this, $mrHandler));
			unset($this->requestsCallbacks[$requestId]);
		}
	}

	public function request(MultiRequest_Request $request, $callback = null) {
		$request->addCallback(array($this, 'onRequestComplete'));
		
		$request->setCookieStorage($this->cookiesFilepath);
		if($this->enableAutoReferer && $this->lastRequest) {
			$request->setCurlOption(CURLOPT_REFERER, $this->lastRequest->getUrl());
		}
		
		foreach($this->defaultCurlOptions as $option => $value) {
			$request->setCurlOption($option, $value);
		}
		foreach($this->defaultHeaders as $header) {
			$request->addHeader($header);
		}
		
		if($callback && !is_callable($callback)) {
			throw new Exception('Wrong callback');
		}
		$this->requestsCallbacks[self::getRequestId($request)] = $callback;
		
		$id = self::getRequestId($request);
		$this->requestsCallbacks[$id] = $callback;
		$this->mrHandler->pushRequestToQueue($request);
		$this->mrHandler->exec();
	}

	protected static function getRequestId(MultiRequest_Request $request) {
		return spl_object_hash($request);
	}

	public function __destruct() {
		@unlink($this->cookiesFilepath);
	}
}