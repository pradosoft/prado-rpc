<?php

use Prado\Exceptions\TApplicationException;
use Prado\IO\HttpClient\THttpClient;
use Prado\IO\HttpClient\THttpClientException;
use Prado\IO\HttpClient\THttpClientResponse;
use Prado\IO\Rpc\TJsonRpcClient;
use Prado\IO\Rpc\TRpcClient;
use Prado\IO\Rpc\TRpcClientRequestException;
use Prado\IO\Rpc\TXmlRpcClient;

// ── Mock HTTP transport ───────────────────────────────────────────────────────

/**
 * Controllable THttpClient for TRpcClient transport tests.
 */
class RpcMockHttpClient extends THttpClient
{
	public array $calls = [];
	public ?THttpClientException $throwException = null;
	public THttpClientResponse $response;

	public function __construct(?THttpClientResponse $response = null)
	{
		$this->response = $response ?? new THttpClientResponse(200, [], '');
		parent::__construct();
	}

	public function download(string $method, string $url, array $headers = [], ?string $body = null): THttpClientResponse
	{
		$this->calls[] = compact('method', 'url', 'headers', 'body');
		if ($this->throwException !== null) {
			throw $this->throwException;
		}
		return $this->response;
	}
}

// ── Testable subclasses ───────────────────────────────────────────────────────

/**
 * Exposes performRequest for real-transport tests.
 */
class ExposedTRpcClient extends TRpcClient
{
	public function exposePerformRequest(string $serverUrl, string $payload, string $mimeType): string
	{
		return $this->performRequest($serverUrl, $payload, $mimeType);
	}
}

/**
 * Intercepts performRequest for higher-level tests.
 */
class TestableTRpcClient extends TRpcClient
{
	public array $calls = [];
	public ?string $nextResponse = null;
	public bool $shouldFail = false;

	protected function performRequest(string $serverUrl, string $payload, string $mimeType): string
	{
		$this->calls[] = compact('serverUrl', 'payload', 'mimeType');
		if ($this->shouldFail) {
			throw new TRpcClientRequestException('Request failed ("HTTP/1.1 503 Service Unavailable")');
		}
		return (string) ($this->nextResponse ?? '');
	}
}

// ── TRpcClientTest ────────────────────────────────────────────────────────────

/**
 * Tests for TRpcClient — properties, factory, downloader, and performRequest.
 */
class TRpcClientTest extends PHPUnit\Framework\TestCase
{
	private TestableTRpcClient $client;

	protected function setUp(): void
	{
		$this->client = new TestableTRpcClient('http://example.test/rpc');
	}

	// ── Constructor ───────────────────────────────────────────────────────────

	public function testConstructorSetsServerUrl(): void
	{
		$this->assertSame('http://example.test/rpc', $this->client->getServerUrl());
	}

	public function testConstructorDefaultIsNotificationFalse(): void
	{
		$this->assertFalse($this->client->getIsNotification());
	}

	public function testConstructorSetsIsNotification(): void
	{
		$c = new TestableTRpcClient('http://example.test/rpc', true);
		$this->assertTrue($c->getIsNotification());
	}

	// ── getServerUrl / setServerUrl ───────────────────────────────────────────

	public function testSetServerUrlUpdatesValue(): void
	{
		$this->client->setServerUrl('http://other.test/api');
		$this->assertSame('http://other.test/api', $this->client->getServerUrl());
	}

	// ── getIsNotification / setIsNotification ─────────────────────────────────

	public function testSetIsNotificationTrue(): void
	{
		$this->client->setIsNotification(true);
		$this->assertTrue($this->client->getIsNotification());
	}

	public function testSetIsNotificationFalse(): void
	{
		$this->client->setIsNotification(true);
		$this->client->setIsNotification(false);
		$this->assertFalse($this->client->getIsNotification());
	}

	public function testSetIsNotificationCoercesStringTrue(): void
	{
		$this->client->setIsNotification('1');
		$this->assertTrue($this->client->getIsNotification());
	}

	public function testSetIsNotificationCoercesStringFalse(): void
	{
		$this->client->setIsNotification(true);
		$this->client->setIsNotification('0');
		$this->assertFalse($this->client->getIsNotification());
	}

	// ── getDownloader / setDownloader ─────────────────────────────────────────

	public function testGetDownloaderReturnsHttpClient(): void
	{
		$this->assertInstanceOf(THttpClient::class, $this->client->getDownloader());
	}

	public function testSetDownloaderReplacesDownloader(): void
	{
		$mock = new RpcMockHttpClient();
		$this->client->setDownloader($mock);
		$this->assertSame($mock, $this->client->getDownloader());
	}

	public function testGetDownloaderReturnsSameInstanceOnRepeatCalls(): void
	{
		$first = $this->client->getDownloader();
		$second = $this->client->getDownloader();
		$this->assertSame($first, $second);
	}

	// ── create() factory ──────────────────────────────────────────────────────

	public function testCreateReturnsJsonClientForLowercaseType(): void
	{
		$c = TRpcClient::create('json', 'http://example.test/rpc');
		$this->assertInstanceOf(TJsonRpcClient::class, $c);
	}

	public function testCreateReturnsXmlClientForLowercaseType(): void
	{
		$c = TRpcClient::create('xml', 'http://example.test/rpc');
		$this->assertInstanceOf(TXmlRpcClient::class, $c);
	}

	public function testCreateIsCaseInsensitiveForJson(): void
	{
		$c = TRpcClient::create('JSON', 'http://example.test/rpc');
		$this->assertInstanceOf(TJsonRpcClient::class, $c);
	}

	public function testCreateIsCaseInsensitiveForXml(): void
	{
		$c = TRpcClient::create('XML', 'http://example.test/rpc');
		$this->assertInstanceOf(TXmlRpcClient::class, $c);
	}

	public function testCreateSetsServerUrl(): void
	{
		$c = TRpcClient::create('json', 'http://factory.test/rpc');
		$this->assertSame('http://factory.test/rpc', $c->getServerUrl());
	}

	public function testCreatePropagatesIsNotificationTrue(): void
	{
		$c = TRpcClient::create('json', 'http://example.test/rpc', true);
		$this->assertTrue($c->getIsNotification());
	}

	public function testCreatePropagatesIsNotificationDefault(): void
	{
		$c = TRpcClient::create('json', 'http://example.test/rpc');
		$this->assertFalse($c->getIsNotification());
	}

	public function testCreateThrowsForUnknownType(): void
	{
		$this->expectException(TApplicationException::class);
		TRpcClient::create('soap', 'http://example.test/rpc');
	}

	public function testCreateWithEmptyStringTypeThrows(): void
	{
		$this->expectException(TApplicationException::class);
		TRpcClient::create('', 'http://example.test/rpc');
	}

	public function testCreateWithMixedCaseTypeWorks(): void
	{
		$c = TRpcClient::create('Json', 'http://example.test/rpc');
		$this->assertInstanceOf(TJsonRpcClient::class, $c);
	}

	// ── performRequest — real transport via RpcMockHttpClient ────────────────────

	public function testPerformRequestReturnsBodyOnSuccess(): void
	{
		$mock = new RpcMockHttpClient(new THttpClientResponse(200, [], 'response-body'));
		$client = new ExposedTRpcClient('http://example.test/rpc');
		$client->setDownloader($mock);
		$result = $client->exposePerformRequest('http://example.test/rpc', 'payload', 'application/json');
		$this->assertSame('response-body', $result);
	}

	public function testPerformRequestSendsPostMethod(): void
	{
		$mock = new RpcMockHttpClient(new THttpClientResponse(200, [], ''));
		$client = new ExposedTRpcClient('http://example.test/rpc');
		$client->setDownloader($mock);
		$client->exposePerformRequest('http://example.test/rpc', 'payload', 'application/json');
		$this->assertSame('POST', $mock->calls[0]['method']);
	}

	public function testPerformRequestSendsContentTypeHeader(): void
	{
		$mock = new RpcMockHttpClient(new THttpClientResponse(200, [], ''));
		$client = new ExposedTRpcClient('http://example.test/rpc');
		$client->setDownloader($mock);
		$client->exposePerformRequest('http://example.test/rpc', 'payload', 'text/xml');
		$this->assertSame('text/xml', $mock->calls[0]['headers']['Content-Type']);
	}

	public function testPerformRequestForwardsPayload(): void
	{
		$mock = new RpcMockHttpClient(new THttpClientResponse(200, [], ''));
		$client = new ExposedTRpcClient('http://example.test/rpc');
		$client->setDownloader($mock);
		$client->exposePerformRequest('http://example.test/rpc', 'my-payload', 'application/json');
		$this->assertSame('my-payload', $mock->calls[0]['body']);
	}

	public function testPerformRequestThrowsOnTransportFailure(): void
	{
		$mock = new RpcMockHttpClient();
		$mock->throwException = new THttpClientException('connection refused');
		$client = new ExposedTRpcClient('http://example.test/rpc');
		$client->setDownloader($mock);
		$this->expectException(TRpcClientRequestException::class);
		$client->exposePerformRequest('http://example.test/rpc', 'payload', 'application/json');
	}

	public function testPerformRequestThrowsOnHttpError(): void
	{
		$mock = new RpcMockHttpClient(new THttpClientResponse(503, [], 'Service Unavailable'));
		$client = new ExposedTRpcClient('http://example.test/rpc');
		$client->setDownloader($mock);
		$this->expectException(TRpcClientRequestException::class);
		$client->exposePerformRequest('http://example.test/rpc', 'payload', 'application/json');
	}

	public function testPerformRequestThrowsExceptionMessageContainsStatusCode(): void
	{
		$mock = new RpcMockHttpClient(new THttpClientResponse(404, [], ''));
		$client = new ExposedTRpcClient('http://example.test/rpc');
		$client->setDownloader($mock);
		try {
			$client->exposePerformRequest('http://example.test/rpc', 'payload', 'application/json');
			$this->fail('Expected TRpcClientRequestException');
		} catch (TRpcClientRequestException $e) {
			$this->assertStringContainsString('404', $e->getMessage());
		}
	}

	public function testPerformRequestThrowsOnRequestFailedMessage(): void
	{
		$mock = new RpcMockHttpClient();
		$mock->throwException = new THttpClientException('timeout');
		$client = new ExposedTRpcClient('http://example.test/rpc');
		$client->setDownloader($mock);
		try {
			$client->exposePerformRequest('http://example.test/rpc', 'payload', 'application/json');
			$this->fail('Expected TRpcClientRequestException');
		} catch (TRpcClientRequestException $e) {
			$this->assertStringContainsString('Request failed', $e->getMessage());
		}
	}
}
