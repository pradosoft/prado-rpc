<?php

use Prado\Exceptions\THttpException;
use Prado\Web\Services\TRpcException;
use Prado\Web\Services\TRpcProtocol;

// ── Minimal concrete subclass ─────────────────────────────────────────────────

class StubRpcProtocol extends TRpcProtocol
{
	public function callMethod($requestPayload)
	{
		return null;
	}

	public function createErrorResponse(TRpcException $e)
	{
		return 'error:' . $e->getMessage();
	}

	public function createResponseHeaders($response): void
	{
	}

	public function encode($data)
	{
		return json_encode($data);
	}

	public function decode($data)
	{
		return json_decode($data, true);
	}
}

// ── TRpcProtocolTest ──────────────────────────────────────────────────────────

/**
 * Tests for TRpcProtocol — addMethod storage and callApiMethod dispatch.
 */
class TRpcProtocolTest extends PHPUnit\Framework\TestCase
{
	private StubRpcProtocol $protocol;

	protected function setUp(): void
	{
		$this->protocol = new StubRpcProtocol();
	}

	// ── addMethod ─────────────────────────────────────────────────────────────

	public function testAddMethodStoresMethod(): void
	{
		$this->protocol->addMethod('ping', ['method' => fn() => 'pong']);
		$result = $this->protocol->callApiMethod('ping', []);
		$this->assertSame('pong', $result);
	}

	public function testAddMethodOverwritesExistingMethod(): void
	{
		$this->protocol->addMethod('ping', ['method' => fn() => 'v1']);
		$this->protocol->addMethod('ping', ['method' => fn() => 'v2']);
		$this->assertSame('v2', $this->protocol->callApiMethod('ping', []));
	}

	public function testAddMultipleMethods(): void
	{
		$this->protocol->addMethod('a', ['method' => fn() => 1]);
		$this->protocol->addMethod('b', ['method' => fn() => 2]);
		$this->assertSame(1, $this->protocol->callApiMethod('a', []));
		$this->assertSame(2, $this->protocol->callApiMethod('b', []));
	}

	// ── callApiMethod ─────────────────────────────────────────────────────────

	public function testCallApiMethodDispatchesToCallback(): void
	{
		$this->protocol->addMethod('add', ['method' => fn($a, $b) => $a + $b]);
		$this->assertSame(7, $this->protocol->callApiMethod('add', [3, 4]));
	}

	public function testCallApiMethodPassesParametersAsArguments(): void
	{
		$received = null;
		$this->protocol->addMethod('capture', ['method' => function () use (&$received) {
			$received = func_get_args();
		}]);
		$this->protocol->callApiMethod('capture', ['x', 'y']);
		$this->assertSame(['x', 'y'], $received);
	}

	public function testCallApiMethodThrowsForUnknownMethod(): void
	{
		$this->expectException(TRpcException::class);
		$this->protocol->callApiMethod('noSuchMethod', []);
	}

	public function testCallApiMethodThrowsWithMethodNameInMessage(): void
	{
		try {
			$this->protocol->callApiMethod('ghost', []);
			$this->fail('Expected TRpcException');
		} catch (TRpcException $e) {
			$this->assertStringContainsString('ghost', $e->getMessage());
		}
	}

	public function testCallApiMethodHandlesNullParams(): void
	{
		$this->protocol->addMethod('ping', ['method' => fn() => 'pong']);
		$result = $this->protocol->callApiMethod('ping', null);
		$this->assertSame('pong', $result);
	}

	public function testCallApiMethodCoercesScalarToArray(): void
	{
		$this->protocol->addMethod('echo', ['method' => fn($v) => $v]);
		$result = $this->protocol->callApiMethod('echo', 'scalar');
		$this->assertSame('scalar', $result);
	}

	public function testCallApiMethodWithStaticCallback(): void
	{
		$this->protocol->addMethod('compute', ['method' => [StubCallbackClass::class, 'double']]);
		$this->assertSame(10, $this->protocol->callApiMethod('compute', [5]));
	}
}

class StubCallbackClass
{
	public static function double(int $n): int
	{
		return $n * 2;
	}
}
