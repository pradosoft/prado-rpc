<?php

/**
 * TXmlRpcBase64 class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 4.4.0
 */

namespace Prado\IO\Rpc;

/**
 * TXmlRpcBase64 class
 *
 * Value object that wraps a raw binary string for transmission as an XML-RPC
 * `<base64>` element. Pass an instance to {@see TXmlRpcCodec::encodeRequest()}
 * whenever a parameter must be base64-encoded rather than sent as a plain string.
 *
 * ```php
 * $client->upload(new TXmlRpcBase64(file_get_contents('/path/to/file.bin')));
 * ```
 *
 * @since 4.4.0
 */
final class TXmlRpcBase64
{
	/**
	 * @param string $data Raw binary data to be base64-encoded on the wire.
	 */
	public function __construct(
		public readonly string $data
	) {
	}
}
