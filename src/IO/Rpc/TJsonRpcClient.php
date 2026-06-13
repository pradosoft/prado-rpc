<?php

/**
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Prado\IO\Rpc;

use Prado\Exceptions\TInvalidDataValueException;
use Prado\Web\Javascripts\TJavaScript;
use Prado\Web\TMediaType;

/**
 * TJsonRpcClient class
 *
 * RPC client that communicates using the JSON-RPC 2.0 protocol by default.
 * Set {@see setVersion()} to `'1.0'` to send JSON-RPC 1.0 requests instead.
 *
 * Note: When using setIsNotification(true), *every* following request is also
 * considered to be a notification until you use setIsNotification(false).
 *
 * Usage:
 * <pre>
 * $_rpcClient = new TJsonRpcClient('http://host/server');
 * $_result = $_rpcClient->remoteMethod($param, $otherParam);
 * // or
 * $_result = TJsonRpcClient::create('http://host/server')->remoteMethod($param, $otherParam);
 * </pre>
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @since 1.0.0
 * @phpstan-consistent-constructor
 */
class TJsonRpcClient extends TRpcClient
{
	/**
	 * @var string JSON-RPC protocol version used for outgoing requests ('1.0' or '2.0')
	 */
	private $_version = '2.0';

	// magics

	/**
	 * @param string $method RPC method name
	 * @param array $parameters RPC method parameters
	 * @throws TRpcClientRequestException if the client fails to connect to the server
	 * @throws TRpcClientResponseException if the response represents an RPC fault
	 * @return mixed RPC request result
	 */
	public function __call($method, $parameters): mixed
	{
		$_response = $this->performRequest($this->getServerUrl(), $this->encodeRequest($method, $parameters), TMediaType::JSON);

		if ($this->getIsNotification()) {
			return true;
		}

		try {
			$_response = TJavaScript::jsonDecode($_response, true);
		} catch (\JsonException $e) {
			throw new TRpcClientResponseException('Empty response received');
		}
		if ($_response === null || !is_array($_response)) {
			throw new TRpcClientResponseException('Empty response received');
		}

		$error = $_response['error'] ?? null;
		if ($error !== null) {
			if (is_array($error)) {
				throw new TRpcClientResponseException(
					$error['message'] ?? 'RPC error',
					$error['code'] ?? null
				);
			}
			throw new TRpcClientResponseException($error);
		}

		return $_response['result'] ?? null;
	}

	// methods

	/**
	 * Encodes a JSON-RPC request payload.
	 *
	 * In version 2.0, the `"jsonrpc"` field is included. Notifications omit the `id` key
	 * entirely. In version 1.0, the `"jsonrpc"` field is omitted and notifications use
	 * `"id": null`.
	 *
	 * @param string $method method name
	 * @param array $parameters method parameters
	 * @return string JSON-encoded request
	 */
	public function encodeRequest(string $method, array $parameters): string
	{
		static $_requestId;
		$_requestId = ($_requestId === null) ? 1 : $_requestId + 1;

		$request = [
			'method' => $method,
			'params' => $parameters,
		];

		if ($this->getIsNotification()) {
			if ($this->_version === '1.0') {
				$request['id'] = null;
			}
			// 2.0 notifications omit the id key entirely
		} else {
			$request['id'] = $_requestId;
		}

		if ($this->_version !== '1.0') {
			$request = ['jsonrpc' => '2.0'] + $request;
		}

		return TJavaScript::jsonEncode($request);
	}

	/**
	 * Creates an instance of TJsonRpcClient.
	 * @param mixed $type unused
	 * @param string $serverUrl url of the rpc server
	 * @param bool $isNotification whether the requests are considered to be notifications (completely ignoring the response) (default: false)
	 */
	public static function create($type, $serverUrl, $isNotification = false): static
	{
		return new static($serverUrl, $isNotification);
	}

	// getter/setter

	/**
	 * Returns the JSON-RPC protocol version used for outgoing requests.
	 * @return string '1.0' or '2.0'
	 */
	public function getVersion(): string
	{
		return $this->_version;
	}

	/**
	 * Sets the JSON-RPC protocol version for outgoing requests.
	 * @param string $value '1.0' or '2.0' (default: '2.0')
	 */
	public function setVersion($value): void
	{
		$value = (string) $value;
		if ($value !== '1.0' && $value !== '2.0') {
			throw new TInvalidDataValueException('rpcclient_unsupported_version', $value);
		}
		$this->_version = $value;
	}
}
