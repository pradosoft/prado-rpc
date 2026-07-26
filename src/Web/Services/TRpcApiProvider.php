<?php

/**
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 3.2.0
 */

namespace Prado\Web\Services;

/**
 * TRpcApiProvider class
 *
 * Abstract base for RPC API providers used with {@see TRpcService}.
 * Subclasses implement {@see registerMethods()} to declare the available methods
 * and their handlers.
 *
 * ```php
 * public function registerMethods(): array
 * {
 *     return [
 *         'apiMethodName1' => ['method' => [$this, 'objectMethodName1']],
 *         'apiMethodName2' => ['method' => ['ClassName', 'staticMethodName']],
 *     ];
 * }
 * ```
 *
 * Each handler receives the client-supplied request parameters as positional arguments.
 * Use a variadic signature (`...$args`) or explicit parameters with defaults to handle
 * varying or missing arguments safely.
 *
 * Providers must be registered in the service configuration as explained in
 * {@see TRpcService}'s documentation.
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @since 3.2.0
 */
abstract class TRpcApiProvider extends \Prado\TModule
{
	/**
	 * @var TRpcServer RPC server instance
	 */
	protected $rpcServer;

	/**
	 * Returns the method name-to-handler map for this provider.
	 * @return array<string, array> method registry
	 */
	abstract public function registerMethods();

	/**
	 * Stores the server reference, calls the parent constructor, then registers all methods.
	 * @param TRpcServer $rpcServer
	 */
	public function __construct(TRpcServer $rpcServer)
	{
		$this->rpcServer = $rpcServer;
		parent::__construct();
		foreach ($this->registerMethods() as $_methodName => $_methodDetails) {
			$this->rpcServer->addRpcMethod($_methodName, $_methodDetails);
		}
	}

	/**
	 * Delegates the current request to the RPC server.
	 * @return string encoded RPC response
	 */
	public function processRequest()
	{
		return $this->rpcServer->processRequest();
	}

	/**
	 * Returns the RPC server instance.
	 * @return TRpcServer
	 */
	public function getRpcServer()
	{
		return $this->rpcServer;
	}
}
