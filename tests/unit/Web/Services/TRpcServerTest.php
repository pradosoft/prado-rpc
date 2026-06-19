<?php

use Prado\Web\Services\TJsonRpcProtocol;
use Prado\Web\Services\TRpcException;
use Prado\Web\Services\TRpcServer;

// ── Controllable subclass ─────────────────────────────────────────────────────

/**
 * Overrides getPayload() so tests can inject a request body without php://input.
 */
class ControllableRpcServer extends TRpcServer
{
	public string $simulatedPayload = '{}';

	public function getPayload(): string
	{
		return $this->simulatedPayload;
	}
}

// ── Protocol that re-throws TRpcException (for dead-code path test) ──────────

class RethrowingProtocol extends TJsonRpcProtocol
{
	public function callMethod($requestPayload)
	{
		throw new TRpcException('Rethrown error');
	}
}

// ── TRpcServerTest ────────────────────────────────────────────────────────────

/**
 * Tests for TRpcServer — method registration, request dispatch, and error handling.
 */
class TRpcServerTest extends PHPUnit\Framework\TestCase
{
	private TJsonRpcProtocol $protocol;
	private ControllableRpcServer $server;

	protected function setUp(): void
	{
		$this->protocol = new TJsonRpcProtocol();
		$this->server = new ControllableRpcServer($this->protocol);
	}

	// ── addRpcMethod ──────────────────────────────────────────────────────────

	public function testAddRpcMethodDelegatesToProtocol(): void
	{
		$this->server->addRpcMethod('ping', ['method' => fn() => 'pong']);
		$this->server->simulatedPayload = json_encode(['method' => 'ping', 'params' => [], 'id' => 1]);
		$response = json_decode($this->server->processRequest(), true);
		$this->assertSame('pong', $response['result']);
	}

	public function testAddMultipleRpcMethods(): void
	{
		$this->server->addRpcMethod('a', ['method' => fn() => 1]);
		$this->server->addRpcMethod('b', ['method' => fn() => 2]);

		$this->server->simulatedPayload = json_encode(['method' => 'b', 'params' => [], 'id' => 1]);
		$response = json_decode($this->server->processRequest(), true);
		$this->assertSame(2, $response['result']);
	}

	// ── processRequest ────────────────────────────────────────────────────────

	public function testProcessRequestCallsHandlerWithPayload(): void
	{
		$this->server->addRpcMethod('add', ['method' => fn($a, $b) => $a + $b]);
		$this->server->simulatedPayload = json_encode(['method' => 'add', 'params' => [3, 4], 'id' => 1]);
		$response = json_decode($this->server->processRequest(), true);
		$this->assertSame(7, $response['result']);
	}

	public function testProcessRequestReturnsHandlerResult(): void
	{
		$this->server->addRpcMethod('ping', ['method' => fn() => 'pong']);
		$this->server->simulatedPayload = json_encode(['method' => 'ping', 'params' => [], 'id' => 1]);
		$result = $this->server->processRequest();
		$this->assertIsString($result);
		$this->assertStringContainsString('pong', $result);
	}

	public function testProcessRequestHandlesUnknownMethodAsFault(): void
	{
		$this->server->simulatedPayload = json_encode(['method' => 'ghost', 'params' => [], 'id' => 1]);
		$response = json_decode($this->server->processRequest(), true);
		// callMethod internally catches TRpcException — error in response, no throw
		$this->assertArrayHasKey('error', $response);
	}

	public function testProcessRequestReturnsEmptyStringForNotification(): void
	{
		$this->server->addRpcMethod('ping', ['method' => fn() => 'pong']);
		$this->server->simulatedPayload = json_encode(['jsonrpc' => '2.0', 'method' => 'ping', 'params' => []]);
		$result = $this->server->processRequest();
		$this->assertSame('', $result);
	}

	public function testProcessRequestCatchesTRpcExceptionFromProtocol(): void
	{
		// TRpcServer::processRequest catches TRpcException; use a protocol that
		// re-throws rather than encoding it, to exercise the server-level catch.
		$protocol = new RethrowingProtocol();
		$protocol->addMethod('x', ['method' => fn() => null]);
		$server = new ControllableRpcServer($protocol);
		$server->simulatedPayload = json_encode(['method' => 'x', 'params' => [], 'id' => 1]);

		// Should not throw — server catches it and converts to error response.
		$result = $server->processRequest();
		$this->assertStringContainsString('error', $result);
	}
}
