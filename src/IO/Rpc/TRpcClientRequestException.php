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
 * TRpcClientRequestException class
 *
 * Thrown when the RPC request fails at the transport level, such as when no
 * RPC server responds at the given remote host or the server returns a
 * non-2xx HTTP status.
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @since 1.0.0
 */
class TRpcClientRequestException extends TApplicationException
{
}
