<?php

use Prado\IO\Rpc\TXmlRpcBase64;
use Prado\IO\Rpc\TXmlRpcCodec;

/**
 * Tests for TXmlRpcCodec — XML-RPC encoding, decoding, and fault detection.
 */
class TXmlRpcCodecTest extends PHPUnit\Framework\TestCase
{
	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function successXml(string $typeFragment): string
	{
		return '<?xml version="1.0"?><methodResponse><params><param>'
			. '<value>' . $typeFragment . '</value>'
			. '</param></params></methodResponse>';
	}

	private static function faultXml(int $code, string $message): string
	{
		return '<?xml version="1.0"?><methodResponse><fault><value><struct>'
			. '<member><name>faultCode</name><value><int>' . $code . '</int></value></member>'
			. '<member><name>faultString</name><value><string>' . htmlspecialchars($message, ENT_XML1, 'UTF-8') . '</string></value></member>'
			. '</struct></value></fault></methodResponse>';
	}

	// ── encodeRequest ─────────────────────────────────────────────────────────

	public function testEncodeRequestProducesXmlDeclaration(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('ping', []);
		$this->assertStringContainsString('<?xml version="1.0"', $xml);
	}

	public function testEncodeRequestUtf8Encoding(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('ping', []);
		$this->assertStringContainsString('encoding="UTF-8"', $xml);
	}

	public function testEncodeRequestContainsMethodName(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('myMethod', []);
		$this->assertStringContainsString('<methodName>myMethod</methodName>', $xml);
	}

	public function testEncodeRequestEscapesMethodNameXml(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('a&b', []);
		$this->assertStringContainsString('<methodName>a&amp;b</methodName>', $xml);
		$this->assertStringNotContainsString('<methodName>a&b</methodName>', $xml);
	}

	public function testEncodeRequestWithNoParams(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('ping', []);
		$this->assertStringContainsString('<params>', $xml);
		$this->assertStringNotContainsString('<param>', $xml);
	}

	public function testEncodeRequestEncodesStringParam(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('echo', ['hello']);
		$this->assertStringContainsString('<string>hello</string>', $xml);
	}

	public function testEncodeRequestEscapesStringXml(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('echo', ['<tag>']);
		$this->assertStringContainsString('<string>&lt;tag&gt;</string>', $xml);
	}

	public function testEncodeRequestEncodesIntegerParam(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('add', [42]);
		$this->assertStringContainsString('<int>42</int>', $xml);
	}

	public function testEncodeRequestEncodesNegativeInteger(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('sub', [-7]);
		$this->assertStringContainsString('<int>-7</int>', $xml);
	}

	public function testEncodeRequestEncodesDoubleParam(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('calc', [3.14]);
		$this->assertStringContainsString('<double>3.14</double>', $xml);
	}

	public function testEncodeRequestEncodesBooleanTrue(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('flag', [true]);
		$this->assertStringContainsString('<boolean>1</boolean>', $xml);
	}

	public function testEncodeRequestEncodesBooleanFalse(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('flag', [false]);
		$this->assertStringContainsString('<boolean>0</boolean>', $xml);
	}

	public function testEncodeRequestEncodesNull(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('nullable', [null]);
		$this->assertStringContainsString('<nil/>', $xml);
	}

	public function testEncodeRequestEncodesDateTimeInterface(): void
	{
		$dt = new \DateTimeImmutable('2026-06-15 09:30:00');
		$xml = TXmlRpcCodec::encodeRequest('scheduled', [$dt]);
		$this->assertStringContainsString('<dateTime.iso8601>20260615T09:30:00</dateTime.iso8601>', $xml);
	}

	public function testEncodeRequestEncodesMutableDateTime(): void
	{
		$dt = new \DateTime('2026-01-01 00:00:00');
		$xml = TXmlRpcCodec::encodeRequest('ts', [$dt]);
		$this->assertStringContainsString('<dateTime.iso8601>20260101T00:00:00</dateTime.iso8601>', $xml);
	}

	public function testEncodeRequestEncodesBase64Value(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('upload', [new TXmlRpcBase64('binary data')]);
		$this->assertStringContainsString('<base64>' . base64_encode('binary data') . '</base64>', $xml);
	}

	public function testEncodeRequestBase64EmptyString(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('upload', [new TXmlRpcBase64('')]);
		$this->assertStringContainsString('<base64></base64>', $xml);
	}

	public function testEncodeRequestBase64BinaryData(): void
	{
		$binary = "\x00\x01\x02\xff\xfe";
		$xml = TXmlRpcCodec::encodeRequest('upload', [new TXmlRpcBase64($binary)]);
		$this->assertStringContainsString(base64_encode($binary), $xml);
	}

	public function testEncodeRequestEncodesObjectAsStruct(): void
	{
		$obj = new \stdClass();
		$obj->name = 'Alice';
		$obj->age = 30;
		$xml = TXmlRpcCodec::encodeRequest('create', [$obj]);
		$this->assertStringContainsString('<struct>', $xml);
		$this->assertStringContainsString('<name>name</name>', $xml);
		$this->assertStringContainsString('<string>Alice</string>', $xml);
		$this->assertStringContainsString('<int>30</int>', $xml);
	}

	public function testEncodeRequestObjectWithNoPublicProperties(): void
	{
		$obj = new \stdClass();
		$xml = TXmlRpcCodec::encodeRequest('empty', [$obj]);
		$this->assertStringContainsString('<struct></struct>', $xml);
	}

	public function testEncodeRequestEncodesSequentialArray(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('list', [[1, 2, 3]]);
		$this->assertStringContainsString('<array>', $xml);
		$this->assertStringContainsString('<data>', $xml);
		$this->assertStringContainsString('<int>1</int>', $xml);
		$this->assertStringContainsString('<int>3</int>', $xml);
	}

	public function testEncodeRequestEncodesAssociativeArrayAsStruct(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('obj', [['key' => 'val']]);
		$this->assertStringContainsString('<struct>', $xml);
		$this->assertStringContainsString('<name>key</name>', $xml);
		$this->assertStringContainsString('<string>val</string>', $xml);
	}

	public function testEncodeRequestEscapesStructMemberName(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('obj', [['a<b' => 1]]);
		$this->assertStringContainsString('<name>a&lt;b</name>', $xml);
	}

	public function testEncodeRequestEncodesMultipleParams(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('add', [1, 2]);
		$this->assertSame(2, substr_count($xml, '<param>'));
	}

	public function testEncodeRequestProducesWellFormedXml(): void
	{
		$xml = TXmlRpcCodec::encodeRequest('test', ['str', 42, true]);
		$prev = libxml_use_internal_errors(true);
		$result = simplexml_load_string($xml);
		libxml_use_internal_errors($prev);
		$this->assertNotFalse($result);
	}

	// ── decodeResponse — scalar types ─────────────────────────────────────────

	public function testDecodeResponseReturnsString(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<string>pong</string>'));
		$this->assertSame('pong', $result);
	}

	public function testDecodeResponseReturnsBareString(): void
	{
		// <value>text</value> without a type element is treated as string
		$result = TXmlRpcCodec::decodeResponse(self::successXml('bare'));
		$this->assertSame('bare', $result);
	}

	public function testDecodeResponseReturnsInt(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<int>42</int>'));
		$this->assertSame(42, $result);
	}

	public function testDecodeResponseReturnsNegativeInt(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<int>-7</int>'));
		$this->assertSame(-7, $result);
	}

	public function testDecodeResponseReturnsI4(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<i4>99</i4>'));
		$this->assertSame(99, $result);
	}

	public function testDecodeResponseReturnsDouble(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<double>3.14</double>'));
		$this->assertEqualsWithDelta(3.14, $result, 0.0001);
	}

	public function testDecodeResponseReturnsBooleanTrue(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<boolean>1</boolean>'));
		$this->assertTrue($result);
	}

	public function testDecodeResponseReturnsBooleanFalse(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<boolean>0</boolean>'));
		$this->assertFalse($result);
	}

	public function testDecodeResponseReturnsNil(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<nil/>'));
		$this->assertNull($result);
	}

	public function testDecodeResponseReturnsDateTimeImmutable(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<dateTime.iso8601>20260101T00:00:00</dateTime.iso8601>'));
		$this->assertInstanceOf(\DateTimeImmutable::class, $result);
	}

	public function testDecodeResponseDateTimeHasCorrectDate(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<dateTime.iso8601>20260615T14:30:00</dateTime.iso8601>'));
		$this->assertInstanceOf(\DateTimeImmutable::class, $result);
		$this->assertSame('2026-06-15', $result->format('Y-m-d'));
	}

	public function testDecodeResponseDateTimeHasCorrectTime(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<dateTime.iso8601>20260615T14:30:45</dateTime.iso8601>'));
		$this->assertInstanceOf(\DateTimeImmutable::class, $result);
		$this->assertSame('14:30:45', $result->format('H:i:s'));
	}

	public function testDecodeResponseDateTimeFallsBackToStringOnUnparseable(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<dateTime.iso8601>not-a-date</dateTime.iso8601>'));
		$this->assertSame('not-a-date', $result);
	}

	public function testDecodeResponseReturnsBase64Decoded(): void
	{
		$encoded = base64_encode('binary data');
		$result = TXmlRpcCodec::decodeResponse(self::successXml('<base64>' . $encoded . '</base64>'));
		$this->assertSame('binary data', $result);
	}

	// ── decodeResponse — compound types ──────────────────────────────────────

	public function testDecodeResponseReturnsArray(): void
	{
		$xml = self::successXml('<array><data>'
			. '<value><int>1</int></value>'
			. '<value><int>2</int></value>'
			. '</data></array>');
		$result = TXmlRpcCodec::decodeResponse($xml);
		$this->assertSame([1, 2], $result);
	}

	public function testDecodeResponseReturnsEmptyArray(): void
	{
		$xml = self::successXml('<array><data></data></array>');
		$result = TXmlRpcCodec::decodeResponse($xml);
		$this->assertSame([], $result);
	}

	public function testDecodeResponseReturnsStruct(): void
	{
		$xml = self::successXml('<struct>'
			. '<member><name>foo</name><value><string>bar</string></value></member>'
			. '</struct>');
		$result = TXmlRpcCodec::decodeResponse($xml);
		$this->assertSame(['foo' => 'bar'], $result);
	}

	public function testDecodeResponseReturnsNestedStruct(): void
	{
		$xml = self::successXml('<struct>'
			. '<member><name>inner</name><value><struct>'
			. '<member><name>x</name><value><int>1</int></value></member>'
			. '</struct></value></member>'
			. '</struct>');
		$result = TXmlRpcCodec::decodeResponse($xml);
		$this->assertSame(['inner' => ['x' => 1]], $result);
	}

	// ── decodeResponse — faults ───────────────────────────────────────────────

	public function testDecodeResponseReturnsFaultArray(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::faultXml(42, 'bad input'));
		$this->assertIsArray($result);
		$this->assertArrayHasKey('faultCode', $result);
		$this->assertArrayHasKey('faultString', $result);
	}

	public function testDecodeResponseFaultCodeIsInteger(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::faultXml(-32601, 'Method not found'));
		$this->assertSame(-32601, $result['faultCode']);
	}

	public function testDecodeResponseFaultStringIsString(): void
	{
		$result = TXmlRpcCodec::decodeResponse(self::faultXml(1, 'server error'));
		$this->assertSame('server error', $result['faultString']);
	}

	// ── decodeResponse — failure cases ───────────────────────────────────────

	public function testDecodeResponseReturnsFalseOnEmptyString(): void
	{
		$this->assertFalse(TXmlRpcCodec::decodeResponse(''));
	}

	public function testDecodeResponseReturnsFalseOnMalformedXml(): void
	{
		$this->assertFalse(TXmlRpcCodec::decodeResponse('not xml at all'));
	}

	public function testDecodeResponseReturnsFalseOnPartialXml(): void
	{
		$this->assertFalse(TXmlRpcCodec::decodeResponse('<?xml version="1.0"?><methodResponse>'));
	}

	public function testDecodeResponseReturnsFalseOnMissingParams(): void
	{
		$this->assertFalse(TXmlRpcCodec::decodeResponse('<?xml version="1.0"?><methodResponse></methodResponse>'));
	}

	// ── isFault ───────────────────────────────────────────────────────────────

	public function testIsFaultReturnsTrueForFaultArray(): void
	{
		$this->assertTrue(TXmlRpcCodec::isFault(['faultCode' => 1, 'faultString' => 'err']));
	}

	public function testIsFaultReturnsFalseForNull(): void
	{
		$this->assertFalse(TXmlRpcCodec::isFault(null));
	}

	public function testIsFaultReturnsFalseForString(): void
	{
		$this->assertFalse(TXmlRpcCodec::isFault('error'));
	}

	public function testIsFaultReturnsFalseForArrayMissingFaultCode(): void
	{
		$this->assertFalse(TXmlRpcCodec::isFault(['faultString' => 'err']));
	}

	public function testIsFaultReturnsFalseForArrayMissingFaultString(): void
	{
		$this->assertFalse(TXmlRpcCodec::isFault(['faultCode' => 1]));
	}

	public function testIsFaultReturnsFalseForEmptyArray(): void
	{
		$this->assertFalse(TXmlRpcCodec::isFault([]));
	}

	public function testIsFaultReturnsFalseForFalse(): void
	{
		$this->assertFalse(TXmlRpcCodec::isFault(false));
	}

	public function testIsFaultReturnsFalseForInt(): void
	{
		$this->assertFalse(TXmlRpcCodec::isFault(42));
	}

	// ── round-trip ────────────────────────────────────────────────────────────

	public function testRoundTripStringValue(): void
	{
		$encoded = TXmlRpcCodec::encodeRequest('echo', ['hello & <world>']);
		$prev = libxml_use_internal_errors(true);
		$sxml = simplexml_load_string($encoded);
		libxml_use_internal_errors($prev);
		$this->assertNotFalse($sxml);
	}

	// ── decodeRequest ─────────────────────────────────────────────────────────

	public function testDecodeRequestReturnsMethodAndParams(): void
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>calculateSum</methodName>'
			. '<params><param><value><int>5</int></value></param></params></methodCall>';
		$result = TXmlRpcCodec::decodeRequest($xml);
		$this->assertSame('calculateSum', $result['method']);
		$this->assertSame([5], $result['params']);
	}

	public function testDecodeRequestNoParams(): void
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>ping</methodName><params/></methodCall>';
		$result = TXmlRpcCodec::decodeRequest($xml);
		$this->assertSame('ping', $result['method']);
		$this->assertSame([], $result['params']);
	}

	public function testDecodeRequestMultipleParams(): void
	{
		$xml = '<?xml version="1.0"?><methodCall><methodName>add</methodName>'
			. '<params>'
			. '<param><value><int>3</int></value></param>'
			. '<param><value><string>hello</string></value></param>'
			. '</params></methodCall>';
		$result = TXmlRpcCodec::decodeRequest($xml);
		$this->assertSame('add', $result['method']);
		$this->assertSame([3, 'hello'], $result['params']);
	}

	public function testDecodeRequestReturnsFalseOnInvalidXml(): void
	{
		$this->assertFalse(TXmlRpcCodec::decodeRequest('not xml at all'));
	}

	public function testDecodeRequestReturnsFalseOnWrongRootElement(): void
	{
		$xml = '<?xml version="1.0"?><methodResponse><params/></methodResponse>';
		$this->assertFalse(TXmlRpcCodec::decodeRequest($xml));
	}

	public function testDecodeRequestReturnsFalseOnEmptyString(): void
	{
		$this->assertFalse(TXmlRpcCodec::decodeRequest(''));
	}

	// ── encodeResponse ────────────────────────────────────────────────────────

	public function testEncodeResponseContainsMethodResponse(): void
	{
		$result = TXmlRpcCodec::encodeResponse('hello');
		$this->assertStringContainsString('<methodResponse>', $result);
	}

	public function testEncodeResponseContainsParams(): void
	{
		$result = TXmlRpcCodec::encodeResponse('hello');
		$this->assertStringContainsString('<params>', $result);
		$this->assertStringContainsString('<string>hello</string>', $result);
	}

	public function testEncodeResponseEncodesInteger(): void
	{
		$result = TXmlRpcCodec::encodeResponse(42);
		$this->assertStringContainsString('<int>42</int>', $result);
	}

	public function testEncodeResponseEncodesNull(): void
	{
		$result = TXmlRpcCodec::encodeResponse(null);
		$this->assertStringContainsString('<nil/>', $result);
	}

	public function testEncodeResponseProducesValidXml(): void
	{
		$result = TXmlRpcCodec::encodeResponse(['key' => 'val']);
		$prev = libxml_use_internal_errors(true);
		$sxml = simplexml_load_string($result);
		libxml_use_internal_errors($prev);
		$this->assertNotFalse($sxml);
	}

	public function testEncodeResponseRoundTrip(): void
	{
		$original = ['status' => 'ok', 'count' => 7];
		$xml = TXmlRpcCodec::encodeResponse($original);
		$decoded = TXmlRpcCodec::decodeResponse($xml);
		$this->assertSame($original, $decoded);
	}

	// ── encodeFaultResponse ───────────────────────────────────────────────────

	public function testEncodeFaultResponseContainsFault(): void
	{
		$result = TXmlRpcCodec::encodeFaultResponse(-1, 'Something went wrong');
		$this->assertStringContainsString('<fault>', $result);
	}

	public function testEncodeFaultResponseContainsFaultCode(): void
	{
		$result = TXmlRpcCodec::encodeFaultResponse(-7, 'Error');
		$this->assertStringContainsString('faultCode', $result);
		$this->assertStringContainsString('<int>-7</int>', $result);
	}

	public function testEncodeFaultResponseContainsFaultString(): void
	{
		$result = TXmlRpcCodec::encodeFaultResponse(-1, 'Something went wrong');
		$this->assertStringContainsString('faultString', $result);
		$this->assertStringContainsString('Something went wrong', $result);
	}

	public function testEncodeFaultResponseEscapesFaultString(): void
	{
		$result = TXmlRpcCodec::encodeFaultResponse(-1, '<bad>&things');
		$this->assertStringNotContainsString('<bad>', $result);
		$this->assertStringContainsString('&lt;bad&gt;&amp;things', $result);
	}

	public function testEncodeFaultResponseProducesValidXml(): void
	{
		$result = TXmlRpcCodec::encodeFaultResponse(-32601, 'Method not found');
		$prev = libxml_use_internal_errors(true);
		$sxml = simplexml_load_string($result);
		libxml_use_internal_errors($prev);
		$this->assertNotFalse($sxml);
	}

	public function testEncodeFaultResponseIsDecodableAsFault(): void
	{
		$result = TXmlRpcCodec::encodeFaultResponse(-32601, 'Method not found');
		$decoded = TXmlRpcCodec::decodeResponse($result);
		$this->assertTrue(TXmlRpcCodec::isFault($decoded));
		$this->assertSame(-32601, $decoded['faultCode']);
		$this->assertSame('Method not found', $decoded['faultString']);
	}
}
