<?php

namespace Bigcommerce\Api;

/**
 * HTTP connection.
 */
class Connection
{
	/**
	 * @var \stdClass cURL resource
	 */
	private $curl;

	/**
	 * @var array hash of HTTP request headers
	 */
	private $headers = array();

	/**
	 * @var array hash of headers from HTTP response
	 */
	private $responseHeaders = array();

	/**
	 * The status line of the response.
	 * @var string
	 */
	private $responseStatusLine;

	/**
	 * @var string hash of headers from HTTP response
	 */
	private $responseBody;

	/**
	 * @var boolean
	 */
	private $failOnError = false;

	/**
	 * Manually follow location redirects. Used if CURLOPT_FOLLOWLOCATION
	 * is unavailable due to open_basedir restriction.
	 * @var boolean
	 */
	private $followLocation = false;

	/**
	 * Maximum number of redirects to try.
	 * @var int
	 */
	private $maxRedirects = 20;

	/**
	 * Number of redirects followed in a loop.
	 * @var int
	 */
	private $redirectsFollowed = 0;

	/**
	 * Deal with failed requests if failOnError is not set.
	 * @var mixed
	 */
	private $lastError = false;

	/**
	 * Determines whether the response body should be returned as a raw string.
	 */
	private $rawResponse = false;

	/**
	 * Determines the default content type to use with requests and responses.
	 */
	private $contentType;

	/**
	 * @var bool determines if another attempt should be made if the request
	 * failed due to too many requests.
	 */
	private $autoRetry = true;

	/** @var int current count of retry attempts */
	private $retryAttempts = 0;

	/**
	 * Maximum number of retries for a request before reporting a failure.
	 */
	const MAX_RETRY = 5;

	/**
	 * XML media type.
	 */
	const MEDIA_TYPE_XML = 'application/xml';

	/**
	 * JSON media type.
	 */
	const MEDIA_TYPE_JSON = 'application/json';

	/**
	 * Default urlencoded media type.
	 */
	const MEDIA_TYPE_WWW = 'application/x-www-form-urlencoded';

	/**
	 * Initializes the connection object.
	 */
	public function __construct()
	{
		if (!defined('STDIN')) {
			define('STDIN', fopen('php://stdin', 'r'));
		}

		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, array($this, 'parseHeader'));
		curl_setopt($this->curl, CURLOPT_WRITEFUNCTION, array($this, 'parseBody'));

		// Set to a blank string to make cURL include all encodings it can handle (gzip, deflate, identity) in the 'Accept-Encoding' request header and respect the 'Content-Encoding' response header
		curl_setopt($this->curl, CURLOPT_ENCODING, '');

		// using TLSv1 cipher by default
		$this->setCipher('TLSv1');

		if (!ini_get("open_basedir")) {
			curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
		} else {
			$this->followLocation = true;
		}

		$this->setTimeout(60);
	}

	/**
	 * Controls whether requests and responses should be treated
	 * as XML. Defaults to false (using JSON).
	 *
	 * @param bool $option
	 */
	public function useXml($option=true)
	{
		if ($option) {
			$this->contentType = self::MEDIA_TYPE_XML;
			$this->rawResponse = true;
		} else {
			$this->contentType = self::MEDIA_TYPE_JSON;
			$this->rawResponse = false;
		}
	}

	/**
	 * Controls whether requests or responses should be treated
	 * as urlencoded form data.
	 *
	 * @param bool $option
	 */
	public function useUrlencoded($option=true)
	{
		if ($option) {
			$this->contentType = self::MEDIA_TYPE_WWW;
		}
	}

	/**
	 * Throw an exception if the request encounters an HTTP error condition.
	 *
	 * <p>An error condition is considered to be:</p>
	 *
	 * <ul>
	 * 	<li>400-499 - Client error</li>
	 *	<li>500-599 - Server error</li>
	 * </ul>
	 *
	 * <p><em>Note that this doesn't use the builtin CURL_FAILONERROR option,
	 * as this fails fast, making the HTTP body and headers inaccessible.</em></p>
	 *
	 * @param bool $option
	 */
	public function failOnError($option = true)
	{
		$this->failOnError = $option;
	}

	/**
	 * Sets the HTTP basic authentication.
	 *
	 * @param string $username
	 * @param string $password
	 */
	public function authenticate($username, $password)
	{
		$this->removeHeader('X-Auth-Client');
		$this->removeHeader('X-Auth-Token');

		curl_setopt($this->curl, CURLOPT_USERPWD, "$username:$password");
	}

	/**
	 * Sets Oauth authentication headers
	 *
	 * @param string $clientId
	 * @param string $authToken
	 */
	public function authenticateOauth($clientId, $authToken)
	{
		$this->addHeader('X-Auth-Client', $clientId);
		$this->addHeader('X-Auth-Token', $authToken);

		curl_setopt($this->curl, CURLOPT_USERPWD, "");
	}

	/**
	 * Sets the auto retry parameter
	 *
	 * @param bool $retry
	 */
	public function setAutoRetry($retry = true)
	{
		$this->autoRetry = (bool)$retry;
	}

	/**
	 * Set a default timeout for the request. The client will error if the
	 * request takes longer than this to respond.
	 *
	 * @param int $timeout number of seconds to wait on a response
	 */
	public function setTimeout($timeout)
	{
		curl_setopt($this->curl, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $timeout);
	}

	/**
	 * Set a proxy server for outgoing requests to tunnel through.
	 *
	 * @param string $server
	 * @param bool|int $port
	 */
	public function useProxy($server, $port=false)
	{
		curl_setopt($this->curl, CURLOPT_PROXY, $server);

		if ($port) {
			curl_setopt($this->curl, CURLOPT_PROXYPORT, $port);
		}
	}

	/**
	 * @todo may need to handle CURLOPT_SSL_VERIFYHOST and CURLOPT_CAINFO as well
	 * @param boolean
	 */
	public function verifyPeer($option=false)
	{
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, $option);
	}

	/**
	 * Set which cipher to use during SSL requests.
	 * @param string $cipher the name of the cipher
	 */
	public function setCipher($cipher='TLSv1')
	{
		curl_setopt($this->curl, CURLOPT_SSL_CIPHER_LIST, $cipher);
	}

	/**
	 * Add a custom header to the request.
	 *
	 * @param string $header
	 * @param string $value
	 */
	public function addHeader($header, $value)
	{
		$this->headers[$header] = "$header: $value";
	}

	/**
	 * Removes a custom header from the request
	 *
	 * @param $header
	 */
	public function removeHeader($header)
	{
		if (isset($this->headers[$header])) {
			unset($this->headers[$header]);
		}
	}

	/**
	 * Get the MIME type that should be used for this request.
	 *
	 * Defaults to JSON.
	 */
	private function getContentType()
	{
		return ($this->contentType) ? $this->contentType : self::MEDIA_TYPE_JSON;
	}

	/**
	 * Clear previously cached request data and prepare for
	 * making a fresh request.
	 */
	private function initializeRequest()
	{
		$this->responseBody = '';
		$this->responseHeaders = array();
		$this->lastError = false;
		$this->addHeader('Accept', $this->getContentType());
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);
	}

	/**
	 * Check the response for possible errors and handle the response body returned.
	 *
	 * If failOnError is true, a client or server error is raised, otherwise returns false
	 * on error.
	 *
	 * @throws NetworkError
	 * @throws ClientError
	 * @throws ServerError
	 */
	private function handleResponse()
	{
		if (curl_errno($this->curl)) {
			throw new NetworkError(curl_error($this->curl), curl_errno($this->curl));
		}

		$body = ($this->rawResponse) ? $this->getBody() : json_decode($this->getBody());

		$status = $this->getStatus();

		if ($status >= 400 && $status <= 499) {
			if ($this->failOnError) {
				if (is_object($body) && property_exists($body, 'error')) {
					throw new ClientError($body->error, $status);
				}

				throw new ClientError($body, $status);
			} else {
				$this->lastError = $body;
				return false;
			}
		} elseif ($status >= 500 && $status <= 599) {
			if ($this->failOnError) {
				throw new ServerError($body, $status);
			} else {
				$this->lastError = $body;
				return false;
			}
		}

		// reset retry attempts on a successful request
		$this->retryAttempts = 0;

		if ($this->followLocation) {
			$this->followRedirectPath();
		}

		return $body;
	}

	/**
	 * Return an representation of an error returned by the last request, or false
	 * if the last request was not an error.
	 */
	public function getLastError()
	{
		return $this->lastError;
	}

	/**
	 * Recursively follow redirect until an OK response is received or
	 * the maximum redirects limit is reached.
	 *
	 * Only 301 and 302 redirects are handled. Redirects from POST and PUT requests will
	 * be converted into GET requests, as per the HTTP spec.
	 *
	 * @throws NetworkError
	 * @throws ClientError
	 * @throws ServerError
	 */
	private function followRedirectPath()
	{
		$this->redirectsFollowed++;

		if ($this->getStatus() == 301 || $this->getStatus() == 302) {
			if ($this->redirectsFollowed < $this->maxRedirects) {
				$location = $this->getHeader('Location');
				$forwardTo = parse_url($location);

				if (isset($forwardTo['scheme']) && isset($forwardTo['host'])) {
					$url = $location;
				} else {
					$forwardFrom = parse_url(curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL));
					$url = $forwardFrom['scheme'] . '://' . $forwardFrom['host'] . $location;
				}

				$this->get($url);
			} else {
				$errorString = "Too many redirects when trying to follow location.";
				throw new NetworkError($errorString, CURLE_TOO_MANY_REDIRECTS);
			}
		} else {
			$this->redirectsFollowed = 0;
		}
	}

	/**
	 * Make an HTTP GET request to the specified endpoint.
	 *
	 * @param string $url
	 * @param bool|array $query
	 * @return mixed
	 *
	 * @throws ClientError
	 * @throws NetworkError
	 * @throws ServerError
	 */
	public function get($url, $query=false)
	{
		$this->initializeRequest();

		$requestUrl = $url;

		if (is_array($query)) {
			$requestUrl .= '?' . http_build_query($query);
		}

		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($this->curl, CURLOPT_URL, $requestUrl);
		curl_setopt($this->curl, CURLOPT_POST, false);
		curl_setopt($this->curl, CURLOPT_PUT, false);
		curl_setopt($this->curl, CURLOPT_HTTPGET, true);
		curl_exec($this->curl);

		try {
			return $this->handleResponse();
		} catch (ClientError $ce) {
			if ($this->canRetryRequest($ce)) {
				$delayMs = (int)$this->getHeader('X-Rate-Limit-Time-Reset-Ms');
				usleep($delayMs * 1000);

				return $this->get($url, $query);
			}

			throw $ce;
		} catch (NetworkError $ne) {
			if ($this->canRetryNetworkError($ne)) {
				sleep(60);
				$this->retryAttempts++;

				return $this->get($url, $query);
			}

			throw $ne;
		} catch (ServerError $se) {
			if ($this->canRetryServerError($se)) {
				sleep(60);
				$this->retryAttempts++;

				return $this->get($url, $query);
			}

			throw $se;
		}
	}

	/**
	 * Make an HTTP POST request to the specified endpoint.
	 *
	 * @param string $url
	 * @param array $body
	 * @return mixed
	 *
	 * @throws ClientError
	 * @throws ServerError
	 * @throws NetworkError
	 */
	public function post($url, $body)
	{
		$contentType = $this->getContentType();
		$this->addHeader('Content-Type', $contentType);

		$postData = $body;

		if (!is_string($postData)) {
			if ($contentType === self::MEDIA_TYPE_JSON) {
				$postData = json_encode($postData);
			} else {
				$postData = http_build_query($postData, '', '&');
			}
		}

		$this->initializeRequest();

		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_POST, true);
		curl_setopt($this->curl, CURLOPT_PUT, false);
		curl_setopt($this->curl, CURLOPT_HTTPGET, false);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postData);
		curl_exec($this->curl);

		try {
			return $this->handleResponse();
		} catch (ClientError $ce) {
			if ($this->canRetryRequest($ce)) {
				$delayMs = (int)$this->getHeader('X-Rate-Limit-Time-Reset-Ms');
				usleep($delayMs * 1000);

				return $this->post($url, $body);
			}

			throw $ce;
		} catch (NetworkError $ne) {
			if ($this->canRetryNetworkError($ne)) {
				sleep(60);
				$this->retryAttempts++;

				return $this->post($url, $body);
			}

			throw $ne;
		} catch (ServerError $se) {
			if ($this->canRetryServerError($se)) {
				sleep(60);
				$this->retryAttempts++;

				return $this->post($url, $body);
			}

			throw $se;
		}
	}

	/**
	 * Make an HTTP HEAD request to the specified endpoint.
	 *
	 * @param string $url
	 * @return mixed
	 *
	 * @throws ClientError
	 * @throws NetworkError
	 * @throws ServerError
	 */
	public function head($url)
	{
		$this->initializeRequest();

		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_NOBODY, true);
		curl_exec($this->curl);

		try {
			return $this->handleResponse();
		} catch (ClientError $ce) {
			if ($this->canRetryRequest($ce)) {
				$delay = (int)$this->getHeader('x-retry-after');
				sleep($delay);

				return $this->head($url);
			}

			throw $ce;
		} catch (NetworkError $ne) {
			if ($this->canRetryNetworkError($ne)) {
				sleep(60);
				$this->retryAttempts++;

				return $this->head($url);
			}

			throw $ne;
		} catch (ServerError $se) {
			if ($this->canRetryServerError($se)) {
				sleep(60);
				$this->retryAttempts++;

				return $this->head($url);
			}

			throw $se;
		}
	}

	/**
	 * Make an HTTP PUT request to the specified endpoint.
	 *
	 * Requires a tmpfile() handle to be opened on the system, as the cURL
	 * API requires it to send data.
	 *
	 * @param string $url
	 * @param array $body
	 * @return mixed
	 *
	 * @throws ClientError
	 * @throws NetworkError
	 * @throws ServerError
	 */
	public function put($url, $body)
	{
		$this->addHeader('Content-Type', $this->getContentType());

		$bodyData = $body;

		if (!is_string($bodyData)) {
			$bodyData = json_encode($bodyData);
		}

		$this->initializeRequest();

		$handle = tmpfile();
		fwrite($handle, $bodyData);
		fseek($handle, 0);
		curl_setopt($this->curl, CURLOPT_INFILE, $handle);
		curl_setopt($this->curl, CURLOPT_INFILESIZE, strlen($bodyData));

		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_HTTPGET, false);
		curl_setopt($this->curl, CURLOPT_POST, false);
		curl_setopt($this->curl, CURLOPT_PUT, true);
		curl_exec($this->curl);

		fclose($handle);
		curl_setopt($this->curl, CURLOPT_INFILE, STDIN);

		try {
			return $this->handleResponse();
		} catch (ClientError $ce) {
			if ($this->canRetryRequest($ce)) {
				$delayMs = (int)$this->getHeader('X-Rate-Limit-Time-Reset-Ms');
				usleep($delayMs * 1000);

				return $this->put($url, $body);
			}

			throw $ce;
		} catch (NetworkError $ne) {
			if ($this->canRetryNetworkError($ne)) {
				sleep(60);
				$this->retryAttempts++;

				return $this->put($url, $body);
			}

			throw $ne;
		} catch (ServerError $se) {
			if ($this->canRetryServerError($se)) {
				sleep(60);
				$this->retryAttempts++;

				return $this->put($url, $body);
			}

			throw $se;
		}
	}

	/**
	 * Make an HTTP DELETE request to the specified endpoint.
	 *
	 * @param string $url
	 * @return mixed
	 *
	 * @throws ClientError
	 * @throws NetworkError
	 * @throws ServerError
	 */
	public function delete($url)
	{
		$this->initializeRequest();

		curl_setopt($this->curl, CURLOPT_PUT, false);
		curl_setopt($this->curl, CURLOPT_POST, false);
		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_exec($this->curl);

		try {
			return $this->handleResponse();
		} catch (ClientError $ce) {
			if ($this->canRetryRequest($ce)) {
				$delayMs = (int)$this->getHeader('X-Rate-Limit-Time-Reset-Ms');
				usleep($delayMs * 1000);

				return $this->delete($url);
			}

			throw $ce;
		} catch (NetworkError $ne) {
			if ($this->canRetryNetworkError($ne)) {
				sleep(60);
				$this->retryAttempts++;

				return $this->delete($url);
			}

			throw $ne;
		} catch (ServerError $ne) {
			if ($this->canRetryServerError($ne)) {
				sleep(60);
				$this->retryAttempts++;

				return $this->delete($url);
			}

			throw $ne;
		}
	}

	/**
	 * Callback methods collects header lines from the response.
	 *
	 * @param \stdClass $curl
	 * @param string $headers
	 * @return int
	 */
	private function parseHeader($curl, $headers)
	{
		if (!$this->responseStatusLine && strpos($headers, 'HTTP/') === 0) {
			$this->responseStatusLine = $headers;
		} else {
			$parts = explode(': ', $headers);
			if (isset($parts[1])) {
				$this->responseHeaders[strtolower($parts[0])] = trim($parts[1]);
			}
		}

		return strlen($headers);
	}

	/**
	 * Callback method collects body content from the response.
	 *
	 * @param \stdClass $curl
	 * @param string $body
	 * @return int
	 */
	private function parseBody($curl, $body)
	{
		$this->responseBody .= $body;
		return strlen($body);
	}

	/**
	 * returns true if another attempt should be made on the request
	 *
	 * @param ClientError $ce
	 * @return bool
	 */
	private function canRetryRequest(ClientError $ce)
	{
		return ($this->autoRetry && in_array((int)$ce->getCode(), array( 408, 429 )));
	}

	/**
	 * returns true if another attempt should be made
	 *
	 * @param ServerError $se
	 * @return bool
	 */
	private function canRetryServerError(ServerError $se)
	{
		if (
			$this->autoRetry
			&& in_array((int)$se->getCode(), array( 500, 502 ))
			&& $this->retryAttempts < self::MAX_RETRY
		) {
			return true;
		}

		return false;
	}

	private function canRetryNetworkError(NetworkError $ne)
	{
		if (
			$this->autoRetry
			&& in_array((int)$ne->getCode(), array( CURLE_OPERATION_TIMEDOUT, CURLE_GOT_NOTHING, CURLE_RECV_ERROR ))
			&& $this->retryAttempts < self::MAX_RETRY
		) {
			return true;
		}

		return false;
	}

	/**
	 * Access the status code of the response.
	 * @return string
	 */
	public function getStatus()
	{
		return curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
	}

	/**
	 * Access the message string from the status line of the response.
	 * @return string
	 */
	public function getStatusMessage()
	{
		return $this->responseStatusLine;
	}

	/**
	 * Access the content body of the response
	 */
	public function getBody()
	{
		return $this->responseBody;
	}

	/**
	 * Access given header from the response.
	 *
	 * @param string $header
	 * @return string|bool
	 */
	public function getHeader($header)
	{
		$header = strtolower($header);

		if (array_key_exists($header, $this->responseHeaders)) {
			return $this->responseHeaders[$header];
		}

		return false;
	}

	/**
	 * Return the full list of response headers
	 */
	public function getHeaders()
	{
		return $this->responseHeaders;
	}

	/**
	 * Close the cURL resource when the instance is garbage collected
	 */
	public function __destruct()
	{
		curl_close($this->curl);
	}

}
