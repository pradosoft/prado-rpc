<?php

/**
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Prado\Web\Services;

use Prado\Exceptions\THttpException;
use Prado\IO\Rpc\TXmlRpcCodec;
use Prado\Web\TMediaType;

/**
 * TXmlRpcProtocol class
 *
 * TXmlRpcProtocol implements the XML-RPC server protocol for {@see TRpcService}.
 * Uses the pure-PHP {@see TXmlRpcCodec} in place of the removed PECL `xmlrpc` extension.
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @since 1.0.0
 */
class TXmlRpcProtocol extends TRpcProtocol
{
	/**
	 * Handles the RPC request.
	 * @param string $requestPayload raw XML-RPC methodCall document
	 * @return string XML-RPC response document
	 */
	public function callMethod($requestPayload)
	{
		try {
			$request = TXmlRpcCodec::decodeRequest($requestPayload);
			if ($request === false) {
				throw new TRpcException('Invalid XML-RPC request');
			}
			return TXmlRpcCodec::encodeResponse($this->callApiMethod($request['method'], $request['params']));
		} catch (TRpcException $e) {
			return $this->createErrorResponse($e);
		} catch (THttpException $e) {
			throw $e;
		} catch (\Exception $e) {
			return $this->createErrorResponse(new TRpcException('An internal error occurred'));
		}
	}

	/**
	 * Encodes a TRpcException as an XML-RPC fault response.
	 * @param TRpcException $exception
	 * @return string XML-RPC fault document
	 */
	public function createErrorResponse(TRpcException $exception)
	{
		return TXmlRpcCodec::encodeFaultResponse((int) $exception->getErrorCode(), $exception->getMessage());
	}

	/**
	 * Sets the correct response headers.
	 * @param \Prado\Web\THttpResponse $response
	 */
	public function createResponseHeaders($response)
	{
		$response->setContentType(TMediaType::XML_TEXT);
		$response->setCharset('UTF-8');
	}

	/**
	 * Decodes an XML-RPC request payload.
	 * @param string $data raw XML-RPC request document
	 * @return array{method: string, params: array}|false decoded request or `false` on parse failure
	 */
	public function decode($data)
	{
		return TXmlRpcCodec::decodeRequest($data);
	}

	/**
	 * Encodes a PHP value as an XML-RPC methodResponse document.
	 * @param mixed $data PHP value to encode
	 * @return string XML-RPC response document
	 */
	public function encode($data)
	{
		return TXmlRpcCodec::encodeResponse($data);
	}
}
