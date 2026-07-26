<?php

/**
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 3.2.0
 */

namespace Prado\IO\Rpc;

use Prado\Prado;
use Prado\Web\TMediaType;

/**
 * TXmlRpcClient class
 *
 * RPC client that communicates using the XML-RPC protocol.
 *
 * Note: When using setIsNotification(true), *every* following request is also
 * considered to be a notification until you use setIsNotification(false).
 *
 * Usage:
 * <pre>
 * $_rpcClient = new TXmlRpcClient('http://remotehost/rpcserver');
 * $_rpcClient->remoteMethod($param, $otherParam);
 * </pre>
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @since 3.2.0
 */
class TXmlRpcClient extends TRpcClient
{
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
		// send request
		$_response = $this->performRequest($this->getServerUrl(), $this->encodeRequest($method, $parameters), TMediaType::XML_TEXT);

		// skip response handling if the request was just a notification request
		if ($this->getIsNotification()) {
			return true;
		}

		// decode response
		$_response = TXmlRpcCodec::decodeResponse($_response);
		if ($_response === false) {
			throw new TRpcClientResponseException('Empty response received');
		}

		// handle error response
		if (TXmlRpcCodec::isFault($_response)) {
			throw new TRpcClientResponseException($_response['faultString'], $_response['faultCode']);
		}

		return $_response;
	}

	// methods

	/**
	 * @param string $method method name
	 * @param array $parameters method parameters
	 * @return string XML-RPC encoded request
	 */
	public function encodeRequest(string $method, array $parameters): string
	{
		return TXmlRpcCodec::encodeRequest($method, $parameters);
	}

	/**
	 * Creates an instance of TXmlRpcClient
	 * @param mixed $type unused
	 * @param string $serverUrl url of the rpc server
	 * @param bool $isNotification whether the requests are considered to be notifications (completely ignoring the response) (default: false)
	 */
	public static function create($type, $serverUrl, $isNotification = false): static
	{
		return Prado::createComponent(static::class, $serverUrl, $isNotification);
	}
}
