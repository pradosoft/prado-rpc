<?php

use Prado\Web\Services\TJsonRpcProtocol;
use Prado\Web\Services\TRpcApiProvider;
use Prado\Web\Services\TRpcServer;

// ── Concrete providers for testing ────────────────────────────────────────────

class SimpleTestApiProvider extends TRpcApiProvider
{
	public function registerMethods(): array
	{
		return [
			'greet' => ['method' => fn($name) => 'Hello, ' . $name],
			'add' => ['method' => fn($a, $b) => $a + $b],
		];
	}
}

class EmptyTestApiProvider extends TRpcApiProvider
{
	public function registerMethods(): array
	{
		return [];
	}
}

class TrackingTestApiProvider extends TRpcApiProvider
{
	public static int $registerCallCount = 0;

	public function registerMethods(): array
	{
		self::$registerCallCount++;
		return ['noop' => ['method' => fn() => null]];
	}
}

// ── TRpcApiProviderTest ───────────────────────────────────────────────────────

/**
 * Tests for TRpcApiProvider — method registration and request delegation.
 */
class TRpcApiProviderTest extends PHPUnit\Framework\TestCase
{
	private TJsonRpcProtocol $protocol;
	private TRpcServer $server;

	protected function setUp(): void
	{
		$this->protocol = new TJsonRpcProtocol();
		// Controllable subclass so tests can inject request payloads.
		$this->server = new class($this->protocol) extends TRpcServer {
			public string $simulatedPayload = '{}';

			public function getPayload(): string
			{
				return $this->simulatedPayload;
			}
		};
	}

	// ── Constructor ───────────────────────────────────────────────────────────

	public function testConstructorCallsRegisterMethods(): void
	{
		TrackingTestApiProvider::$registerCallCount = 0;
		new TrackingTestApiProvider($this->server);
		$this->assertSame(1, TrackingTestApiProvider::$registerCallCount);
	}

	public function testConstructorRegistersMethodsWithServer(): void
	{
		$provider = new SimpleTestApiProvider($this->server);
		$this->server->simulatedPayload = json_encode(['jsonrpc' => '2.0', 'method' => 'add', 'params' => [3, 4], 'id' => 1]);
		$response = json_decode($provider->processRequest(), true);
		$this->assertSame(7, $response['result']);
	}

	public function testConstructorWithEmptyRegisterMethods(): void
	{
		// Must not throw when registerMethods returns [].
		$provider = new EmptyTestApiProvider($this->server);
		$this->assertInstanceOf(TRpcApiProvider::class, $provider);
	}

	// ── getRpcServer ──────────────────────────────────────────────────────────

	public function testGetRpcServerReturnsInjectedServer(): void
	{
		$provider = new SimpleTestApiProvider($this->server);
		$this->assertSame($this->server, $provider->getRpcServer());
	}

	// ── processRequest ────────────────────────────────────────────────────────

	public function testProcessRequestDelegatesToServer(): void
	{
		$provider = new SimpleTestApiProvider($this->server);
		$this->server->simulatedPayload = json_encode(['jsonrpc' => '2.0', 'method' => 'greet', 'params' => ['World'], 'id' => 1]);
		$result = $provider->processRequest();
		$this->assertStringContainsString('Hello, World', $result);
	}
}
