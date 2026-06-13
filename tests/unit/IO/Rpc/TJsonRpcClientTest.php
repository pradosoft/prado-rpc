<?php

use Prado\IO\Rpc\TJsonRpcClient;
use Prado\IO\Rpc\TRpcClientRequestException;
use Prado\IO\Rpc\TRpcClientResponseException;

// ── Testable subclass ─────────────────────────────────────────────────────────

/**
 * Intercepts transport for TJsonRpcClient tests.
 */
class TestableJsonRpcClient extends TJsonRpcClient
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

// ── TJsonRpcClientTest ────────────────────────────────────────────────────────

/**
 * Tests for TJsonRpcClient — encoding, dispatch, notification mode, and error handling.
 */
class TJsonRpcClientTest extends PHPUnit\Framework\TestCase
{
	private TestableJsonRpcClient $client;

	protected function setUp(): void
	{
		$this->client = new TestableJsonRpcClient('http://example.test/rpc');
	}

	private static function successResponse(mixed $result, int $id = 1): string
	{
		return json_encode(['jsonrpc' => '2.0', 'result' => $result, 'id' => $id]);
	}

	private static function errorResponse(mixed $error, int $id = 1): string
	{
		return json_encode(['jsonrpc' => '2.0', 'error' => $error, 'id' => $id]);
	}

	// ── encodeRequest ─────────────────────────────────────────────────────────

	public function testEncodeRequestReturnsValidJson(): void
	{
		$encoded = $this->client->encodeRequest('add', [1, 2]);
		$decoded = json_decode($encoded, true);
		$this->assertSame('add', $decoded['method']);
		$this->assertSame([1, 2], $decoded['params']);
		$this->assertIsInt($decoded['id']);
	}

	public function testEncodeRequestIncludesJsonrpcField(): void
	{
		$decoded = json_decode($this->client->encodeRequest('ping', []), true);
		$this->assertSame('2.0', $decoded['jsonrpc']);
	}

	public function testEncodeRequestJsonrpcFieldComesFirst(): void
	{
		$raw = $this->client->encodeRequest('ping', []);
		$this->assertStringStartsWith('{"jsonrpc"', $raw);
	}

	public function testEncodeRequestIdIncrementsAcrossCalls(): void
	{
		$first = json_decode($this->client->encodeRequest('a', []), true)['id'];
		$second = json_decode($this->client->encodeRequest('b', []), true)['id'];
		$this->assertSame($first + 1, $second);
	}

	public function testEncodeRequest20NotificationOmitsIdKey(): void
	{
		$this->client->setIsNotification(true);
		$decoded = json_decode($this->client->encodeRequest('ping', []), true);
		$this->assertArrayNotHasKey('id', $decoded);
	}

	public function testEncodeRequestNonNotificationHasNonNullId(): void
	{
		$this->client->setIsNotification(false);
		$decoded = json_decode($this->client->encodeRequest('ping', []), true);
		$this->assertNotNull($decoded['id']);
	}

	public function testEncodeRequestWithEmptyParams(): void
	{
		$decoded = json_decode($this->client->encodeRequest('noArgs', []), true);
		$this->assertSame([], $decoded['params']);
		$this->assertSame('noArgs', $decoded['method']);
	}

	public function testEncodeRequestWithAssociativeParams(): void
	{
		$params = ['name' => 'Alice', 'age' => 30];
		$decoded = json_decode($this->client->encodeRequest('create', $params), true);
		$this->assertSame($params, $decoded['params']);
	}

	// ── __call dispatch ───────────────────────────────────────────────────────

	public function testCallReturnsResultOnSuccess(): void
	{
		$this->client->nextResponse = self::successResponse('hello');
		$result = $this->client->greet();
		$this->assertSame('hello', $result);
	}

	public function testCallPassesServerUrlToTransport(): void
	{
		$this->client->nextResponse = self::successResponse(true);
		$this->client->ping();
		$this->assertSame('http://example.test/rpc', $this->client->calls[0]['serverUrl']);
	}

	public function testCallPassesJsonMimeType(): void
	{
		$this->client->nextResponse = self::successResponse(null);
		$this->client->ping();
		$this->assertSame('application/json', $this->client->calls[0]['mimeType']);
	}

	public function testCallPayloadContainsMethodName(): void
	{
		$this->client->nextResponse = self::successResponse(42);
		$this->client->getCount();
		$payload = json_decode($this->client->calls[0]['payload'], true);
		$this->assertSame('getCount', $payload['method']);
	}

	public function testCallPayloadContainsParameters(): void
	{
		$this->client->nextResponse = self::successResponse(3);
		$this->client->add(1, 2);
		$payload = json_decode($this->client->calls[0]['payload'], true);
		$this->assertSame([1, 2], $payload['params']);
	}

	public function testCallReturnsTrueForNotification(): void
	{
		$this->client->setIsNotification(true);
		$this->client->nextResponse = self::successResponse('ignored');
		$result = $this->client->log('event');
		$this->assertTrue($result);
	}

	public function testCallStillSendsRequestForNotification(): void
	{
		$this->client->setIsNotification(true);
		$this->client->nextResponse = self::successResponse('ignored');
		$this->client->log('event');
		$this->assertCount(1, $this->client->calls);
	}

	public function testCallThrowsResponseExceptionOnEmptyBody(): void
	{
		$this->client->nextResponse = '';
		$this->expectException(TRpcClientResponseException::class);
		$this->client->ping();
	}

	public function testCallThrowsResponseExceptionOnNullJson(): void
	{
		$this->client->nextResponse = 'null';
		$this->expectException(TRpcClientResponseException::class);
		$this->client->ping();
	}

	public function testCallThrowsResponseExceptionOnErrorField(): void
	{
		$this->client->nextResponse = self::errorResponse('Something went wrong');
		$this->expectException(TRpcClientResponseException::class);
		$this->client->ping();
	}

	public function testCallThrowsRequestExceptionOnTransportFailure(): void
	{
		$this->client->shouldFail = true;
		$this->expectException(TRpcClientRequestException::class);
		$this->client->ping();
	}

	public function testCallReturnsComplexResultIntact(): void
	{
		$data = ['id' => 7, 'name' => 'Alice', 'roles' => ['admin', 'user']];
		$this->client->nextResponse = self::successResponse($data);
		$result = $this->client->fetchUser(7);
		$this->assertSame($data, $result);
	}

	public function testCallWithNullResultDoesNotThrow(): void
	{
		$this->client->nextResponse = self::successResponse(null);
		$result = $this->client->ping();
		$this->assertNull($result);
	}

	public function testCallThrowsOnScalarJsonResponse(): void
	{
		$this->client->nextResponse = '42';
		$this->expectException(TRpcClientResponseException::class);
		$this->client->ping();
	}

	public function testCallThrowsOnJsonBooleanResponse(): void
	{
		$this->client->nextResponse = 'true';
		$this->expectException(TRpcClientResponseException::class);
		$this->client->ping();
	}

	public function testCallErrorFieldValuePropagatesIntoExceptionMessage(): void
	{
		$this->client->nextResponse = self::errorResponse('Method not found');
		try {
			$this->client->ping();
			$this->fail('Expected TRpcClientResponseException');
		} catch (TRpcClientResponseException $e) {
			$this->assertStringContainsString('Method not found', $e->getMessage());
		}
	}

	public function testCallResponseMissingResultKeyReturnsNull(): void
	{
		$this->client->nextResponse = json_encode(['jsonrpc' => '2.0', 'error' => null, 'id' => 1]);
		$result = $this->client->ping();
		$this->assertNull($result);
	}

	public function testCallResponseMissingErrorKeyDoesNotThrow(): void
	{
		$this->client->nextResponse = json_encode(['jsonrpc' => '2.0', 'result' => 'ok', 'id' => 1]);
		$result = $this->client->ping();
		$this->assertSame('ok', $result);
	}

	public function testNotificationModeSwitchRestoresNonNullId(): void
	{
		$this->client->setIsNotification(true);
		$this->assertArrayNotHasKey('id', json_decode($this->client->encodeRequest('a', []), true));

		$this->client->setIsNotification(false);
		$nonNullId = json_decode($this->client->encodeRequest('b', []), true)['id'];
		$this->assertNotNull($nonNullId);
	}

	// ── Structured error handling ─────────────────────────────────────────────

	public function testCallHandlesStructuredErrorWithMessage(): void
	{
		$this->client->nextResponse = json_encode([
			'jsonrpc' => '2.0',
			'id' => 1,
			'error' => ['code' => -32601, 'message' => 'Method not found'],
		]);
		try {
			$this->client->ping();
			$this->fail('Expected TRpcClientResponseException');
		} catch (TRpcClientResponseException $e) {
			$this->assertStringContainsString('Method not found', $e->getMessage());
		}
	}

	public function testCallHandlesStructuredErrorWithCode(): void
	{
		$this->client->nextResponse = json_encode([
			'jsonrpc' => '2.0',
			'id' => 1,
			'error' => ['code' => -32600, 'message' => 'Invalid Request'],
		]);
		try {
			$this->client->ping();
			$this->fail('Expected TRpcClientResponseException');
		} catch (TRpcClientResponseException $e) {
			$this->assertSame(-32600, $e->getErrorCode());
		}
	}

	public function testCallHandlesStructuredErrorMissingMessage(): void
	{
		$this->client->nextResponse = json_encode([
			'jsonrpc' => '2.0',
			'id' => 1,
			'error' => ['code' => -32700],
		]);
		$this->expectException(TRpcClientResponseException::class);
		$this->client->ping();
	}

	public function testCallHandlesPlainStringError(): void
	{
		$this->client->nextResponse = json_encode(['jsonrpc' => '2.0', 'error' => 'something went wrong', 'id' => 1]);
		try {
			$this->client->ping();
			$this->fail('Expected TRpcClientResponseException');
		} catch (TRpcClientResponseException $e) {
			$this->assertStringContainsString('something went wrong', $e->getMessage());
		}
	}

	// ── Version property ─────────────────────────────────────────────────────

	public function testDefaultVersionIs20(): void
	{
		$this->assertSame('2.0', $this->client->getVersion());
	}

	public function testSetVersionChangesToVersion10(): void
	{
		$this->client->setVersion('1.0');
		$this->assertSame('1.0', $this->client->getVersion());
	}

	public function testSetVersionRejectsInvalidValue(): void
	{
		$this->expectException(\Prado\Exceptions\TInvalidDataValueException::class);
		$this->client->setVersion('3.0');
	}

	public function testEncodeRequestVersion10OmitsJsonrpcField(): void
	{
		$this->client->setVersion('1.0');
		$decoded = json_decode($this->client->encodeRequest('ping', []), true);
		$this->assertArrayNotHasKey('jsonrpc', $decoded);
	}

	public function testEncodeRequestVersion10HasMethodAndParams(): void
	{
		$this->client->setVersion('1.0');
		$decoded = json_decode($this->client->encodeRequest('greet', ['world']), true);
		$this->assertSame('greet', $decoded['method']);
		$this->assertSame(['world'], $decoded['params']);
	}

	public function testEncodeRequestVersion10HasNonNullId(): void
	{
		$this->client->setVersion('1.0');
		$decoded = json_decode($this->client->encodeRequest('ping', []), true);
		$this->assertNotNull($decoded['id']);
	}

	public function testEncodeRequestVersion10NotificationHasNullId(): void
	{
		$this->client->setVersion('1.0');
		$this->client->setIsNotification(true);
		$decoded = json_decode($this->client->encodeRequest('ping', []), true);
		$this->assertNull($decoded['id']);
	}

	public function testSetVersionBackTo20IncludesJsonrpcField(): void
	{
		$this->client->setVersion('1.0');
		$this->client->setVersion('2.0');
		$decoded = json_decode($this->client->encodeRequest('ping', []), true);
		$this->assertSame('2.0', $decoded['jsonrpc']);
	}

	// ── create() override ─────────────────────────────────────────────────────

	public function testCreateReturnsJsonRpcClient(): void
	{
		$c = TJsonRpcClient::create('ignored', 'http://example.test/rpc');
		$this->assertInstanceOf(TJsonRpcClient::class, $c);
	}

	public function testCreateSetsServerUrl(): void
	{
		$c = TJsonRpcClient::create('ignored', 'http://json.test/rpc');
		$this->assertSame('http://json.test/rpc', $c->getServerUrl());
	}

	public function testCreatePropagatesIsNotification(): void
	{
		$c = TJsonRpcClient::create('ignored', 'http://example.test/rpc', true);
		$this->assertTrue($c->getIsNotification());
	}
}
