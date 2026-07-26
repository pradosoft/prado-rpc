<?php

/**
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 3.2.0
 */

namespace Prado\Web\Services;

use Prado\Exceptions\TException;

/**
 * TRpcException class
 *
 * Represents an RPC fault — an error caused by the client's request, such as an
 * unknown method, invalid parameters, or a protocol violation.
 *
 * The `$errorCode` carries a protocol-level fault code. JSON-RPC 2.0 uses the
 * reserved range -32768 to -32000 for pre-defined errors; other values are
 * application-defined.
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @since 3.2.0
 */
class TRpcException extends TException
{
	/**
	 * @param string $message human-readable fault description
	 * @param int|string $errorCode protocol-level fault code (default: -1)
	 */
	public function __construct($message, $errorCode = -1)
	{
		parent::__construct($message);
		$this->setErrorCode($errorCode);
	}
}
