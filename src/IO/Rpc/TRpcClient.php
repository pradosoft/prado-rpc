<?php

/**
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Prado\IO\Rpc;

use Prado\Exceptions\TApplicationException;
use Prado\IO\HttpClient\THttpClient;
use Prado\IO\HttpClient\THttpClientException;
use Prado\Prado;
use Prado\TPropertyValue;
use Prado\Web\THttpHeaderName;

/**
 * TRpcClient class
 *
 * Base class for RPC clients. Subclasses implement protocol-specific encoding and decoding.
 *
 * Note: When using setIsNotification(true), *every* following request is also
 * considered to be a notification until you use setIsNotification(false).
 *
 * Usage:
 *
 * First, you can use the factory:
 * <pre>
 * $_rpcClient = TRpcClient::create('xml', 'http://host/server');
 * $_result = $_rpcClient->remoteMethodName($param, $otherParam);
 * </pre>
 *
 * or as oneliner:
 * <pre>
 * $_result = TRpcClient::create('json', 'http://host/server')->remoteMethod($param, ...);
 * </pre>
 *
 * Second, you can also use the specific implementation directly:
 * <pre>
 * $_rpcClient = new TXmlRpcClient('http://host/server');
 * $_result = $_rpcClient->remoteMethod($param, ...);
 * </pre>
 *
 * or as oneliner:
 * <pre>
 * $_result = TXmlRpcClient('http://host/server')->hello();
 * </pre>
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @since 1.0.0
 * @phpstan-consistent-constructor
 */
class TRpcClient extends \Prado\TApplicationComponent
{
	/**
	 * @var string url of the RPC server
	 */
	private string $_serverUrl;

	/**
	 * @var bool whether the request is a notification and therefore should not care about the result (default: false)
	 */
	private bool $_isNotification = false;

	/**
	 * @var ?THttpClient HTTP transport, lazily created on first use.
	 */
	private ?THttpClient $_downloader = null;

	// magics

	/**
	 * @param string $serverUrl url to RPC server
	 * @param bool $isNotification whether requests are considered to be notifications (completely ignoring the response) (default: false)
	 */
	public function __construct(string $serverUrl, bool $isNotification = false)
	{
		$this->setServerUrl($serverUrl);
		$this->setIsNotification($isNotification);
		parent::__construct();
	}

	// methods

	/**
	 * Creates an RPC client of the given type.
	 * @param string $type protocol type key, case-insensitive (e.g. `'json'`, `'xml'`)
	 * @param string $serverUrl RPC server URL
	 * @param bool $isNotification whether requests are notifications (default: false)
	 * @throws TApplicationException when the type key does not match a known protocol
	 * @return TRpcClient
	 */
	public static function create($type, $serverUrl, $isNotification = false): self
	{
		$handler = match (strtolower((string) $type)) {
			'json' => TJsonRpcClient::class,
			'xml' => TXmlRpcClient::class,
			default => throw new TApplicationException('rpcclient_unsupported_handler', (string) $type),
		};

		return Prado::createComponent($handler, $serverUrl, $isNotification);
	}

	/**
	 * Performs the HTTP POST request.
	 *
	 * Delegates to the configured {@see getDownloader() downloader}. Throws
	 * {@see TRpcClientRequestException} on both transport-level failures and
	 * non-2xx HTTP responses.
	 *
	 * @param string $serverUrl RPC server URL
	 * @param string $payload encoded request body
	 * @param string $mimeType Content-Type header value
	 * @throws TRpcClientRequestException on transport failure or non-2xx HTTP response.
	 * @return string raw response body
	 */
	protected function performRequest(string $serverUrl, string $payload, string $mimeType): string
	{
		try {
			$response = $this->getDownloader()->download('POST', $serverUrl, [
				THttpHeaderName::ContentType => $mimeType,
			], $payload);
		} catch (THttpClientException $e) {
			throw new TRpcClientRequestException('Request failed ("' . $e->getMessage() . '")');
		}

		if (!$response->isSuccess()) {
			throw new TRpcClientRequestException('Request failed ("HTTP ' . $response->getStatusCode() . '")');
		}

		return $response->getBody();
	}

	// getter/setter

	/**
	 * @return bool whether requests are considered to be notifications (completely ignoring the response)
	 */
	public function getIsNotification(): bool
	{
		return $this->_isNotification;
	}

	/**
	 * @param bool|string $bool boolean whether the requests are considered to be notifications (completely ignoring the response) (default: false)
	 */
	public function setIsNotification($bool): void
	{
		$this->_isNotification = TPropertyValue::ensureBoolean($bool);
	}

	/**
	 * @return string url of the RPC server
	 */
	public function getServerUrl(): string
	{
		return $this->_serverUrl;
	}

	/**
	 * @param string $value url of the RPC server
	 */
	public function setServerUrl(string $value): void
	{
		$this->_serverUrl = $value;
	}

	/**
	 * Returns the HTTP transport, creating a default one on first access.
	 * @return THttpClient
	 */
	public function getDownloader(): THttpClient
	{
		if ($this->_downloader === null) {
			$this->_downloader = THttpClient::create();
		}
		return $this->_downloader;
	}

	/**
	 * Replaces the HTTP transport.
	 * @param THttpClient $value downloader instance
	 */
	public function setDownloader(THttpClient $value): void
	{
		$this->_downloader = $value;
	}
}
