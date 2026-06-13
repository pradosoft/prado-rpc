<?php

/**
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Prado\IO\Rpc;

use Prado\Exceptions\TApplicationException;

/**
 * TRpcClientResponseException class
 *
 * Thrown when the RPC server returns a fault or an unparseable response.
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @since 1.0.0
 */
class TRpcClientResponseException extends TApplicationException
{
	/**
	 * @param string $errorMessage error message
	 * @param null|int $errorCode protocol-level error code from the server (optional)
	 */
	public function __construct($errorMessage, $errorCode = null)
	{
		parent::__construct($errorMessage);
		$this->setErrorCode($errorCode);
	}
}
