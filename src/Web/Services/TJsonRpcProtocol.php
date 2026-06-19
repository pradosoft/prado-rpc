<?php

/**
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Prado\Web\Services;

use Prado\Exceptions\THttpException;
use Prado\Web\THttpResponse;
use Prado\Web\TMediaType;
use Prado\Web\Javascripts\TJavaScript;

/**
 * TJsonRpcProtocol class
 *
 * TJsonRpcProtocol implements the JSON-RPC 1.0 and 2.0 protocols for {@see TRpcService}.
 * The protocol version is detected per request: requests that include a `"jsonrpc"` field
 * are treated as JSON-RPC 2.0; requests without that field are treated as JSON-RPC 1.0.
 * Responses are formatted to match the detected version.
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @author Fabio Bas <ctrlaltca@gmail.com>
 * @since 1.0.0
 */
class TJsonRpcProtocol extends TRpcProtocol
{
	/**
	 * @var mixed request id from the current JSON-RPC call; null for notifications
	 */
	protected $_id;

	/**
	 * @var string protocol version detected from the current request ('1.0' or '2.0')
	 */
	protected $_requestVersion = '2.0';

	/**
	 * Handles the RPC request.
	 *
	 * The protocol version is auto-detected: a `"jsonrpc"` field → 2.0; absent → 1.0.
	 * Notifications are requests with no `id` key (2.0) or `"id": null` (1.0); both
	 * produce an empty-string return.
	 *
	 * @param string $requestPayload raw JSON request body
	 * @throws TRpcException
	 * @return string JSON-RPC response, or empty string for notifications
	 */
	public function callMethod($requestPayload)
	{
		$this->_id = null;
		$this->_requestVersion = '2.0';
		$idResolved = false;
		try {
			try {
				$_request = $this->decode($requestPayload);
			} catch (\Exception $e) {
				throw new TRpcException('Parse error', -32700);
			}

			if ($_request === null || !is_array($_request)) {
				throw new TRpcException('Parse error', -32700);
			}

			if (isset($_request['jsonrpc'])) {
				if ((float) $_request['jsonrpc'] > 2.0) {
					throw new TRpcException('Unsupported specification version', -32600);
				}
			} else {
				$this->_requestVersion = '1.0';
			}

			if (isset($_request['id'])) {
				$this->_id = $_request['id'];
			}
			$idResolved = true;

			if (!isset($_request['method'])) {
				throw new TRpcException('Missing request method', -32600);
			}

			$parameters = $_request['params'] ?? [];

			if (!is_array($parameters)) {
				$parameters = [$parameters];
			}

			if ($this->_id !== null) {
				return $this->encodeSuccessResponse($this->callApiMethod($_request['method'], $parameters));
			}
		} catch (TRpcException $e) {
			if ($idResolved && $this->_id === null) {
				return '';
			}
			return $this->createErrorResponse($e);
		} catch (THttpException $e) {
			throw $e;
		} catch (\Exception $e) {
			if ($idResolved && $this->_id === null) {
				return '';
			}
			return $this->createErrorResponse(new TRpcException('An internal error occurred', -32603));
		}
		return '';
	}

	/**
	 * Encodes a successful result as a version-appropriate response.
	 *
	 * JSON-RPC 1.0: `{"result": ..., "error": null, "id": ...}`
	 * JSON-RPC 2.0: `{"jsonrpc": "2.0", "result": ..., "id": ...}`
	 *
	 * @param mixed $result return value from the handler
	 * @return string encoded response
	 */
	private function encodeSuccessResponse($result)
	{
		if ($this->_requestVersion === '1.0') {
			return $this->encode([
				'result' => $result,
				'error' => null,
				'id' => $this->_id,
			]);
		}
		return $this->encode([
			'jsonrpc' => '2.0',
			'id' => $this->_id,
			'result' => $result,
		]);
	}

	/**
	 * Encodes a TRpcException as a version-appropriate error response.
	 *
	 * JSON-RPC 1.0: `{"result": null, "error": "message", "id": ...}`
	 * JSON-RPC 2.0: `{"jsonrpc": "2.0", "error": {"code": ..., "message": ..., "data": null}, "id": ...}`
	 *
	 * @param TRpcException $exception
	 * @return string encoded error response
	 */
	public function createErrorResponse(TRpcException $exception)
	{
		if ($this->_requestVersion === '1.0') {
			return $this->encode([
				'result' => null,
				'error' => $exception->getMessage(),
				'id' => $this->_id,
			]);
		}
		return $this->encode([
			'jsonrpc' => '2.0',
			'id' => $this->_id,
			'error' => [
				'code' => (int) $exception->getErrorCode(),
				'message' => $exception->getMessage(),
				'data' => null,
			],
		]);
	}

	/**
	 * Sets the correct response headers.
	 * @param THttpResponse $response
	 */
	public function createResponseHeaders($response)
	{
		$response->setContentType(TMediaType::JSON);
		$response->setCharset('UTF-8');
	}

	/**
	 * Decodes a JSON request payload.
	 * @param string $data request body in JSON format
	 * @return array decoded request
	 */
	public function decode($data)
	{
		return TJavaScript::jsonDecode($data, true);
	}

	/**
	 * Encodes a PHP value as a JSON response.
	 * @param mixed $data value to encode
	 * @return string JSON-encoded response
	 */
	public function encode($data)
	{
		return TJavaScript::jsonEncode($data);
	}

	/**
	 * Calls the registered handler for the given method.
	 *
	 * Overrides the base implementation to use JSON-RPC-specific error codes.
	 *
	 * @param string $methodName RPC method name
	 * @param array $parameters method parameters
	 * @throws TRpcException when the method name is not registered (code -32601)
	 * @return mixed return value from the handler
	 */
	public function callApiMethod($methodName, $parameters)
	{
		if (!isset($this->rpcMethods[$methodName])) {
			throw new TRpcException('Method "' . $methodName . '" not found', -32601);
		}

		if ($parameters === null) {
			$parameters = [];
		}

		if (!is_array($parameters)) {
			$parameters = [$parameters];
		}

		return call_user_func_array($this->rpcMethods[$methodName]['method'], $parameters);
	}
}
