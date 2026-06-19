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
 * TRpcClient is the base class for consuming a remote RPC server. It encodes a
 * method call, sends it as an HTTP POST through the PRADO HTTP client, and
 * decodes the response. Two protocols are provided by the subclasses
 * {@see TJsonRpcClient} (JSON-RPC 1.0 and 2.0) and {@see TXmlRpcClient} (XML-RPC).
 *
 * ## Calling remote methods
 *
 * Remote methods are invoked as native PHP method calls. The base class routes
 * an unknown method through `__call` in each subclass, which encodes the name
 * and arguments, performs the request, and returns the decoded result.
 *
 * ```php
 * use Prado\IO\Rpc\TRpcClient;
 *
 * // Factory: 'json' or 'xml' selects the protocol subclass.
 * $client = TRpcClient::create('json', 'https://api.example.com/rpc');
 *
 * $sum  = $client->add(2, 3);                 // POST {"method":"add","params":[2,3],...}
 * $user = $client->getUser(42);               // decoded result (array, scalar, etc.)
 *
 * // One-liner.
 * $pong = TRpcClient::create('xml', 'https://api.example.com/rpc')->ping();
 *
 * // A concrete subclass may be constructed directly.
 * $client = new \Prado\IO\Rpc\TXmlRpcClient('https://api.example.com/rpc');
 * ```
 *
 * ## Notifications
 *
 * A notification is a call whose response is ignored. Enable it with
 * {@see setIsNotification() IsNotification}; the next and every following call
 * returns `true` without decoding a response, until the flag is set back to
 * `false`.
 *
 * ```php
 * $client->setIsNotification(true);
 * $client->logEvent('user.login', $userId);   // returns true, response discarded
 * $client->setIsNotification(false);
 * ```
 *
 * ## Transport
 *
 * The transport is a {@see \Prado\IO\HttpClient\THttpClient}, created on first
 * use by {@see getDownloader()}. Replace it with {@see setDownloader()} to set
 * timeouts, add headers, or wrap it in {@see \Prado\IO\HttpClient\TCachedHttpClient}
 * for response caching.
 *
 * ```php
 * $transport = \Prado\IO\HttpClient\THttpClient::create();
 * $transport->setTimeout(5);
 * $client->setDownloader($transport);
 * ```
 *
 * ## Error handling
 *
 * | Condition | Exception |
 * |---|---|
 * | Connection failure, timeout, or non-2xx HTTP status | {@see TRpcClientRequestException} |
 * | Server fault, or an empty or unparseable response | {@see TRpcClientResponseException} |
 *
 * ```php
 * use Prado\IO\Rpc\TRpcClientRequestException;
 * use Prado\IO\Rpc\TRpcClientResponseException;
 *
 * try {
 *     $result = $client->getUser(42);
 * } catch (TRpcClientResponseException $e) {
 *     // $e->getMessage() is the fault string; $e->getErrorCode() the fault code.
 * } catch (TRpcClientRequestException $e) {
 *     // transport-level failure; the server was not reached or returned non-2xx.
 * }
 * ```
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
