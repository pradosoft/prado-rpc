<?php

use Prado\IO\Rpc\TRpcClientRequestException;
use Prado\IO\Rpc\TRpcClientResponseException;
use Prado\IO\Rpc\TXmlRpcClient;

// ── Testable subclass ─────────────────────────────────────────────────────────

/**
 * Intercepts transport for TXmlRpcClient tests.
 */
class TestableXmlRpcClient extends TXmlRpcClient
{
	public array $calls = [];
	public ?string $nextResponse = null;
	public bool $shouldFail = false;

	protected function performRequest(string $serverUrl, string $payload, string $mimeType): string
	{
		$this->calls[] = compact('serverUrl', 'payload', 'mimeType');
		if ($this->shouldFail) {
			throw new TRpcClientRequestException('Request failed ("HTTP/1.1 503")');
		}
		return $this->nextResponse ?? '';
	}
}

// ── TXmlRpcClientTest ─────────────────────────────────────────────────────────

/**
 * Tests for TXmlRpcClient — encoding, dispatch, notification mode, and error handling.
 */
class TXmlRpcClientTest extends PHPUnit\Framework\TestCase
{
	private TestableXmlRpcClient $client;

	protected function setUp(): void
	{
		$this->client = new TestableXmlRpcClient('http://example.test/rpc');
	}

	private static function successXml(mixed $value): string
	{
		return '<?xml version="1.0"?><methodResponse><params><param>'
			. '<value>' . self::encodeValue($value) . '</value>'
			. '</param></params></methodResponse>';
	}

	private static function faultXml(int $code, string $message): string
	{
		return '<?xml version="1.0"?><methodResponse><fault><value><struct>'
			. '<member><name>faultCode</name><value><int>' . $code . '</int></value></member>'
			. '<member><name>faultString</name><value><string>' . htmlspecialchars($message) . '</string></value></member>'
			. '</struct></value></fault></methodResponse>';
	}

	private static function encodeValue(mixed $value): string
	{
		return match (true) {
			is_bool($value) => '<boolean>' . ($value ? '1' : '0') . '</boolean>',
			is_int($value) => '<int>' . $value . '</int>',
			default => '<string>' . htmlspecialchars((string) $value) . '</string>',
		};
	}

	// ── encodeRequest ─────────────────────────────────────────────────────────

	public function testEncodeRequestReturnsXmlString(): void
	{
		$encoded = $this->client->encodeRequest('greet', ['world']);
		$this->assertStringContainsString('<?xml', $encoded);
		$this->assertStringContainsString('greet', $encoded);
	}

	public function testEncodeRequestEmbeddsIntegerParameters(): void
	{
		$encoded = $this->client->encodeRequest('add', [3, 4]);
		$this->assertStringContainsString('<int>3</int>', $encoded);
		$this->assertStringContainsString('<int>4</int>', $encoded);
	}

	// ── __call dispatch ───────────────────────────────────────────────────────

	public function testCallReturnsDecodedStringResult(): void
	{
		$this->client->nextResponse = self::successXml('pong');
		$result = $this->client->ping();
		$this->assertSame('pong', $result);
	}

	public function testCallReturnsDecodedIntegerResult(): void
	{
		$this->client->nextResponse = self::successXml(42);
		$result = $this->client->getCount();
		$this->assertSame(42, $result);
	}

	public function testCallPassesServerUrlToTransport(): void
	{
		$this->client->nextResponse = self::successXml(true);
		$this->client->ping();
		$this->assertSame('http://example.test/rpc', $this->client->calls[0]['serverUrl']);
	}

	public function testCallPassesXmlMimeType(): void
	{
		$this->client->nextResponse = self::successXml(true);
		$this->client->ping();
		$this->assertSame('text/xml', $this->client->calls[0]['mimeType']);
	}

	public function testCallPayloadContainsMethodName(): void
	{
		$this->client->nextResponse = self::successXml(true);
		$this->client->myMethod('arg');
		$this->assertStringContainsString('myMethod', $this->client->calls[0]['payload']);
	}

	public function testCallReturnsTrueForNotification(): void
	{
		$this->client->setIsNotification(true);
		$this->client->nextResponse = '';
		$result = $this->client->log('event');
		$this->assertTrue($result);
	}

	public function testCallStillSendsRequestForNotification(): void
	{
		$this->client->setIsNotification(true);
		$this->client->nextResponse = '';
		$this->client->log('event');
		$this->assertCount(1, $this->client->calls);
	}

	public function testCallThrowsResponseExceptionOnEmptyResponse(): void
	{
		$this->client->nextResponse = '';
		$this->expectException(TRpcClientResponseException::class);
		$this->client->ping();
	}

	public function testCallThrowsResponseExceptionOnFault(): void
	{
		$this->client->nextResponse = self::faultXml(42, 'bad input');
		$this->expectException(TRpcClientResponseException::class);
		$this->client->ping();
	}

	public function testCallFaultExceptionCarriesErrorCode(): void
	{
		$this->client->nextResponse = self::faultXml(-32600, 'Invalid Request');
		try {
			$this->client->ping();
			$this->fail('Expected TRpcClientResponseException');
		} catch (TRpcClientResponseException $e) {
			$this->assertSame(-32600, $e->getErrorCode());
		}
	}

	public function testCallFaultExceptionCarriesErrorMessage(): void
	{
		$this->client->nextResponse = self::faultXml(1, 'server error');
		try {
			$this->client->ping();
			$this->fail('Expected TRpcClientResponseException');
		} catch (TRpcClientResponseException $e) {
			$this->assertStringContainsString('server error', $e->getMessage());
		}
	}

	public function testCallThrowsResponseExceptionOnMalformedXml(): void
	{
		// TXmlRpcCodec::decodeResponse() returns false on unparseable input;
		// the client must throw rather than returning false to the caller.
		$this->client->nextResponse = 'not valid xml at all';
		$this->expectException(TRpcClientResponseException::class);
		$this->client->ping();
	}

	public function testCallThrowsResponseExceptionOnPartialXml(): void
	{
		$this->client->nextResponse = '<?xml version="1.0"?><methodResponse>';
		$this->expectException(TRpcClientResponseException::class);
		$this->client->ping();
	}

	public function testCallReturnsNullForNilResponse(): void
	{
		// <nil/> is a valid XML-RPC value; decodeResponse returns null (not false).
		// The client must NOT throw — null is a legitimate return value.
		$this->client->nextResponse = '<?xml version="1.0"?><methodResponse><params><param>'
			. '<value><nil/></value>'
			. '</param></params></methodResponse>';
		$result = $this->client->getNullable();
		$this->assertNull($result);
	}

	public function testCallThrowsRequestExceptionOnTransportFailure(): void
	{
		$this->client->shouldFail = true;
		$this->expectException(TRpcClientRequestException::class);
		$this->client->ping();
	}

	// ── create() override ─────────────────────────────────────────────────────

	public function testCreateReturnsXmlRpcClient(): void
	{
		$c = TXmlRpcClient::create('ignored', 'http://example.test/rpc');
		$this->assertInstanceOf(TXmlRpcClient::class, $c);
	}

	public function testCreateSetsServerUrl(): void
	{
		$c = TXmlRpcClient::create('ignored', 'http://xml.test/rpc');
		$this->assertSame('http://xml.test/rpc', $c->getServerUrl());
	}

	public function testCreatePropagatesIsNotification(): void
	{
		$c = TXmlRpcClient::create('ignored', 'http://example.test/rpc', true);
		$this->assertTrue($c->getIsNotification());
	}
}
