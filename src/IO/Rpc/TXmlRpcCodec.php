<?php

/**
 * TXmlRpcCodec class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Prado\IO\Rpc;

use DateTimeImmutable;
use DateTimeInterface;
use SimpleXMLElement;

/**
 * TXmlRpcCodec class
 *
 * Pure-PHP encoder and decoder for the XML-RPC wire format. Eliminates the
 * dependency on the PECL `xmlrpc` extension.
 *
 * ## Encoding — PHP → XML-RPC type mapping
 *
 * | PHP type | XML-RPC element |
 * |---|---|
 * | `null` | `<nil/>` |
 * | `bool` | `<boolean>` |
 * | `int` | `<int>` |
 * | `float` | `<double>` |
 * | `string` | `<string>` |
 * | `DateTimeInterface` | `<dateTime.iso8601>` |
 * | `TXmlRpcBase64` | `<base64>` |
 * | sequential `array` | `<array>` |
 * | associative `array` or `object` | `<struct>` |
 *
 * ## Decoding — XML-RPC type → PHP type mapping
 *
 * | XML-RPC element | PHP type |
 * |---|---|
 * | `<string>` or bare value | `string` |
 * | `<int>` / `<i4>` / `<i8>` | `int` |
 * | `<double>` | `float` |
 * | `<boolean>` | `bool` |
 * | `<nil/>` | `null` |
 * | `<dateTime.iso8601>` | `DateTimeImmutable` |
 * | `<base64>` | `string` (decoded binary) |
 * | `<array>` | `array` (indexed) |
 * | `<struct>` | `array` (associative) |
 *
 * @internal Used by {@see TXmlRpcClient} and {@see \Prado\Web\Services\TXmlRpcProtocol}.
 * @since 1.0.0
 */
final class TXmlRpcCodec
{
	/**
	 * Encodes a methodCall XML-RPC request.
	 * @param string $method RPC method name.
	 * @param array $params Positional parameters.
	 * @return string XML-RPC request document.
	 */
	public static function encodeRequest(string $method, array $params): string
	{
		$body = '<methodCall>' . "\n"
			. '  <methodName>' . htmlspecialchars($method, ENT_XML1, 'UTF-8') . '</methodName>' . "\n"
			. '  <params>' . "\n";
		foreach ($params as $param) {
			$body .= '    <param><value>' . self::encodeValue($param) . '</value></param>' . "\n";
		}
		$body .= '  </params>' . "\n"
			. '</methodCall>';
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $body;
	}

	/**
	 * Decodes an XML-RPC methodCall request document.
	 *
	 * Returns an array with keys `method` (string) and `params` (array) on success,
	 * or `false` when the document cannot be parsed or is not a `<methodCall>`.
	 *
	 * @param string $xml Raw XML-RPC request document.
	 * @return array{method: string, params: array}|false Decoded request, or `false` on parse failure.
	 */
	public static function decodeRequest(string $xml): array|false
	{
		$prev = libxml_use_internal_errors(true);
		$sxml = simplexml_load_string($xml);
		libxml_use_internal_errors($prev);

		if ($sxml === false || $sxml->getName() !== 'methodCall') {
			return false;
		}

		$params = [];
		if (count($sxml->params) > 0) {
			foreach ($sxml->params->param as $param) {
				$params[] = self::decodeValue($param->value);
			}
		}
		return ['method' => (string) $sxml->methodName, 'params' => $params];
	}

	/**
	 * Encodes a PHP value as an XML-RPC methodResponse document.
	 *
	 * @param mixed $value Return value to encode.
	 * @return string XML-RPC response document.
	 */
	public static function encodeResponse(mixed $value): string
	{
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<methodResponse>' . "\n"
			. '  <params><param><value>' . self::encodeValue($value) . '</value></param></params>' . "\n"
			. '</methodResponse>';
	}

	/**
	 * Encodes an XML-RPC fault response document.
	 *
	 * @param int $code Fault code.
	 * @param string $message Fault string.
	 * @return string XML-RPC fault response document.
	 */
	public static function encodeFaultResponse(int $code, string $message): string
	{
		$struct = '<struct>'
			. '<member><name>faultCode</name><value><int>' . $code . '</int></value></member>'
			. '<member><name>faultString</name><value><string>' . htmlspecialchars($message, ENT_XML1, 'UTF-8') . '</string></value></member>'
			. '</struct>';
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<methodResponse>' . "\n"
			. '  <fault><value>' . $struct . '</value></fault>' . "\n"
			. '</methodResponse>';
	}

	/**
	 * Decodes an XML-RPC methodResponse document.
	 *
	 * Returns the decoded PHP value on success. Faults are returned as
	 * `['faultCode' => int, 'faultString' => string]`; pass the result to
	 * {@see isFault()} to distinguish them from normal struct responses.
	 * Returns `false` when the document cannot be parsed.
	 *
	 * @param string $xml Raw XML-RPC response document.
	 * @return mixed Decoded value, or `false` on parse failure.
	 */
	public static function decodeResponse(string $xml): mixed
	{
		$prev = libxml_use_internal_errors(true);
		$sxml = simplexml_load_string($xml);
		libxml_use_internal_errors($prev);

		if ($sxml === false) {
			return false;
		}

		if (count($sxml->fault) > 0) {
			return self::decodeValue($sxml->fault->value);
		}

		if (count($sxml->params) > 0 && count($sxml->params->param) > 0) {
			return self::decodeValue($sxml->params->param->value);
		}

		return false;
	}

	/**
	 * Returns true when a decoded value is an XML-RPC fault.
	 *
	 * A fault is a struct with `faultCode` (int) and `faultString` (string) keys,
	 * as produced by {@see decodeResponse()} for fault responses.
	 *
	 * @param mixed $value Decoded value to test.
	 * @return bool
	 */
	public static function isFault(mixed $value): bool
	{
		return is_array($value)
			&& array_key_exists('faultCode', $value)
			&& array_key_exists('faultString', $value);
	}

	/**
	 * Encodes a single PHP value as an XML-RPC type fragment (without the `<value>` wrapper).
	 * @param mixed $value Value to encode.
	 * @return string XML-RPC type element string.
	 */
	private static function encodeValue(mixed $value): string
	{
		if ($value === null) {
			return '<nil/>';
		}
		if (is_bool($value)) {
			return '<boolean>' . ($value ? '1' : '0') . '</boolean>';
		}
		if (is_int($value)) {
			return '<int>' . $value . '</int>';
		}
		if (is_float($value)) {
			return '<double>' . $value . '</double>';
		}
		if ($value instanceof TXmlRpcBase64) {
			return '<base64>' . base64_encode($value->data) . '</base64>';
		}
		if ($value instanceof DateTimeInterface) {
			return '<dateTime.iso8601>' . $value->format('Ymd\TH:i:s') . '</dateTime.iso8601>';
		}
		if (is_string($value)) {
			return '<string>' . htmlspecialchars($value, ENT_XML1, 'UTF-8') . '</string>';
		}
		if (is_array($value)) {
			if (array_is_list($value)) {
				$items = implode('', array_map(
					static fn ($v) => '<value>' . self::encodeValue($v) . '</value>',
					$value
				));
				return '<array><data>' . $items . '</data></array>';
			}
			$members = '';
			foreach ($value as $key => $item) {
				$members .= '<member>'
					. '<name>' . htmlspecialchars((string) $key, ENT_XML1, 'UTF-8') . '</name>'
					. '<value>' . self::encodeValue($item) . '</value>'
					. '</member>';
			}
			return '<struct>' . $members . '</struct>';
		}
		if (is_object($value)) {
			$members = '';
			foreach (get_object_vars($value) as $key => $item) {
				$members .= '<member>'
					. '<name>' . htmlspecialchars($key, ENT_XML1, 'UTF-8') . '</name>'
					. '<value>' . self::encodeValue($item) . '</value>'
					. '</member>';
			}
			return '<struct>' . $members . '</struct>';
		}
		return '<string>' . htmlspecialchars((string) $value, ENT_XML1, 'UTF-8') . '</string>';
	}

	/**
	 * Decodes a `<value>` SimpleXML element to a PHP value.
	 * @param SimpleXMLElement $node `<value>` element.
	 * @return mixed Decoded PHP value.
	 */
	private static function decodeValue(SimpleXMLElement $node): mixed
	{
		$children = $node->children();
		if (count($children) === 0) {
			return (string) $node;
		}
		$el = $children[0];
		return match ($el->getName()) {
			'string' => (string) $el,
			'int', 'i4', 'i8' => (int) (string) $el,
			'double' => (float) (string) $el,
			'boolean' => (string) $el === '1',
			'nil' => null,
			'base64' => base64_decode((string) $el),
			'dateTime.iso8601' => self::decodeDatetime((string) $el),
			'array' => self::decodeArray($el),
			'struct' => self::decodeStruct($el),
			default => (string) $el,
		};
	}

	/**
	 * Parses an XML-RPC `dateTime.iso8601` string into a `DateTimeImmutable`.
	 *
	 * Tries the canonical `YYYYMMDDTHH:MM:SS` format first, then falls back to
	 * PHP's generic `DateTimeImmutable` constructor for extended formats. Returns
	 * the raw string when all parsing attempts fail.
	 *
	 * @param string $s ISO 8601 date/time string from the XML-RPC document.
	 * @return DateTimeImmutable|string Parsed date, or the original string on failure.
	 */
	private static function decodeDatetime(string $s): DateTimeImmutable|string
	{
		$dt = DateTimeImmutable::createFromFormat('Ymd\TH:i:s', $s);
		if ($dt !== false) {
			return $dt;
		}
		try {
			return new DateTimeImmutable($s);
		} catch (\Exception) {
			return $s;
		}
	}

	/**
	 * Decodes an `<array>` element to a PHP indexed array.
	 * @param SimpleXMLElement $arrayEl `<array>` element.
	 * @return array Decoded array.
	 */
	private static function decodeArray(SimpleXMLElement $arrayEl): array
	{
		$result = [];
		foreach ($arrayEl->data->value as $item) {
			$result[] = self::decodeValue($item);
		}
		return $result;
	}

	/**
	 * Decodes a `<struct>` element to a PHP associative array.
	 * @param SimpleXMLElement $structEl `<struct>` element.
	 * @return array Decoded associative array.
	 */
	private static function decodeStruct(SimpleXMLElement $structEl): array
	{
		$result = [];
		foreach ($structEl->member as $member) {
			$result[(string) $member->name] = self::decodeValue($member->value);
		}
		return $result;
	}
}
