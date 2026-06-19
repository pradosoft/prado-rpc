<?php

/**
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Prado\Web\Services;

/**
 * TRpcProtocol class
 *
 * Base class for RPC protocol implementations used with {@see \Prado\Web\Services\TRpcService}.
 * {@see \Prado\Web\Services\TJsonRpcProtocol} (JSON-RPC 1.0 and 2.0) and
 * {@see \Prado\Web\Services\TXmlRpcProtocol} (XML-RPC) extend this class.
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @since 1.0.0
 **/
abstract class TRpcProtocol
{
	/**
	 * @var array<string, array> method name to handler-details mapping
	 */
	protected array $rpcMethods = [];

	// abstracts

	/**
	 * Processes the request payload and returns the encoded response.
	 * @param string $requestPayload raw request body
	 * @return string encoded response
	 */
	abstract public function callMethod($requestPayload);

	/**
	 * Encodes a fault condition as a protocol response.
	 * @param TRpcException $exception exception with error details
	 * @return string encoded error response
	 */
	abstract public function createErrorResponse(TRpcException $exception);

	/**
	 * Sets the protocol-specific response headers (content-type, charset).
	 * @param \Prado\Web\THttpResponse $response
	 */
	abstract public function createResponseHeaders($response);

	/**
	 * Encodes a PHP value as a protocol response body.
	 * @param mixed $data value to encode
	 * @return string encoded response
	 */
	abstract public function encode($data);

	/**
	 * Decodes a raw request payload.
	 * @param string $data raw request body
	 * @return mixed decoded request
	 */
	abstract public function decode($data);

	// methods

	/**
	 * Registers an RPC method name and its handler details.
	 * @param string $methodName RPC method name
	 * @param array $handlerDetails handler descriptor with at minimum a `method` key
	 */
	public function addMethod($methodName, $handlerDetails)
	{
		$this->rpcMethods[$methodName] = $handlerDetails;
	}

	/**
	 * Dispatches a call to the registered handler for the given method.
	 * @param string $methodName RPC method name
	 * @param array $parameters positional parameters from the client
	 * @throws TRpcException when the method name is not registered
	 * @return mixed return value from the handler
	 */
	public function callApiMethod($methodName, $parameters)
	{
		if (!isset($this->rpcMethods[$methodName])) {
			throw new TRpcException('Method "' . $methodName . '" not found');
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
