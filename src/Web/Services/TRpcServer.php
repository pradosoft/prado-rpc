<?php

/**
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 3.2.0
 */

namespace Prado\Web\Services;

/**
 * TRpcServer class
 *
 * Intermediate between {@see TRpcService} and {@see TRpcApiProvider}: reads the
 * raw request body, delegates to the protocol handler, and returns the encoded response.
 *
 * This base class suits most needs. Subclass it for logging, auditing, or
 * request/response transformation, then reference it via the `server` attribute:
 *
 * ```xml
 * <service id="rpc" class="Prado\Web\Services\TRpcService">
 *     <rpcapi id="customers" class="Application.Api.CustomersApi"
 *             server="Application.Api.MyRpcServer" />
 * </service>
 * ```
 *
 * PHP configuration style:
 * ```php
 * return [
 *     'services' => [
 *         'rpc' => [
 *             'class' => 'Prado\Web\Services\TRpcService',
 *             'apis' => [
 *                 'customers' => [
 *                     'class' => 'Application\Api\CustomersApi',
 *                     'server' => 'Application\Api\MyRpcServer',
 *                 ],
 *             ],
 *         ],
 *     ],
 * ];
 * ```
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @since 3.2.0
 **/
class TRpcServer extends \Prado\TModule
{
	/**
	 * @var TRpcProtocol protocol handler
	 */
	protected TRpcProtocol $handler;

	/**
	 * @param TRpcProtocol $protocolHandler protocol handler for this server
	 */
	public function __construct(TRpcProtocol $protocolHandler)
	{
		$this->handler = $protocolHandler;
		parent::__construct();
	}

	/**
	 * Registers an RPC method with the protocol handler.
	 * @param string $methodName RPC method name
	 * @param array $methodDetails handler descriptor
	 */
	public function addRpcMethod($methodName, $methodDetails)
	{
		$this->handler->addMethod($methodName, $methodDetails);
	}

	/**
	 * Returns the raw request body.
	 * @return string request payload
	 */
	public function getPayload()
	{
		return file_get_contents('php://input');
	}

	/**
	 * Dispatches the request to the protocol handler and returns the encoded response.
	 *
	 * Catches any {@see TRpcException} that escapes the protocol (safety net) and
	 * converts it to an error response.
	 *
	 * @return string encoded RPC response, or empty string for notifications
	 */
	public function processRequest()
	{
		try {
			return $this->handler->callMethod($this->getPayload());
		} catch (TRpcException $e) {
			return $this->handler->createErrorResponse($e);
		}
	}
}
