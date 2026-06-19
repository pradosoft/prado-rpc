<?php

use Prado\Exceptions\THttpException;
use Prado\Web\Services\TJsonRpcProtocol;
use Prado\Web\Services\TRpcException;

/**
 * Tests for TJsonRpcProtocol — JSON-RPC 2.0 dispatch, error handling,
 * notification mode, and parameter normalization.
 */
class TJsonRpcProtocolTest extends PHPUnit\Framework\TestCase
{
	private TJsonRpcProtocol $protocol;

	protected function setUp(): void
	{
		$this->protocol = new TJsonRpcProtocol();
		$this->protocol->addMethod('add', ['method' => fn($a, $b) => $a + $b]);
		$this->protocol->addMethod('greet', ['method' => fn($name) => 'Hello, ' . $name]);
		$this->protocol->addMethod('nothing', ['method' => fn() => null]);
		$this->protocol->addMethod('boom', ['method' => function () {
			throw new TRpcException('Custom error', -99);
		}]);
		$this->protocol->addMethod('httpBoom', ['method' => function () {
			throw new THttpException(403, 'Forbidden');
		}]);
		$this->protocol->addMethod('crash', ['method' => function () {
			throw new \RuntimeException('Unexpected');
		}]);
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function req(string $method, array $params = [], mixed $id = 1): string
	{
		return json_encode(['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => $id]);
	}

	// ── Request dispatch ──────────────────────────────────────────────────────

	public function testCallMethodReturnsResult(): void
	{
		$response = json_decode($this->protocol->callMethod(self::req('add', [3, 4])), true);
		$this->assertSame(7, $response['result']);
	}

	public function testCallMethodResponseHasJsonrpcField(): void
	{
		$response = json_decode($this->protocol->callMethod(self::req('add', [1, 2])), true);
		$this->assertSame('2.0', $response['jsonrpc']);
	}

	public function testCallMethodResponseHasId(): void
	{
		$response = json_decode($this->protocol->callMethod(self::req('add', [1, 2], 99)), true);
		$this->assertSame(99, $response['id']);
	}

	public function testCallMethodSuccessResponseHasNoErrorKey(): void
	{
		$response = json_decode($this->protocol->callMethod(self::req('add', [1, 2])), true);
		$this->assertArrayNotHasKey('error', $response);
	}

	public function testCallMethodNullResultDoesNotThrow(): void
	{
		$response = json_decode($this->protocol->callMethod(self::req('nothing', [])), true);
		$this->assertNull($response['result']);
	}

	public function testCallMethodAcceptsRequestWithoutJsonrpcField(): void
	{
		// Requests without the jsonrpc field are treated as JSON-RPC 1.0.
		$payload = json_encode(['method' => 'add', 'params' => [1, 2], 'id' => 1]);
		$response = json_decode($this->protocol->callMethod($payload), true);
		$this->assertSame(3, $response['result']);
		$this->assertArrayNotHasKey('jsonrpc', $response);
	}

	// ── Notification (no id) ──────────────────────────────────────────────────

	public function testCallMethodNotificationReturnsEmptyString(): void
	{
		$payload = json_encode(['jsonrpc' => '2.0', 'method' => 'add', 'params' => [1, 2]]);
		$result = $this->protocol->callMethod($payload);
		$this->assertSame('', $result);
	}

	public function testCallMethodIdDoesNotLeakFromPreviousCall(): void
	{
		// A regular call sets $_id, then a notification must NOT inherit it.
		$this->protocol->callMethod(self::req('add', [1, 2], 99));
		$payload = json_encode(['jsonrpc' => '2.0', 'method' => 'add', 'params' => [1, 2]]);
		$result = $this->protocol->callMethod($payload);
		$this->assertSame('', $result);
	}

	public function testCallMethodNotificationUnknownMethodReturnsEmptyString(): void
	{
		// Spec: server MUST NOT reply to notifications, even on error (method not found).
		$payload = json_encode(['jsonrpc' => '2.0', 'method' => 'ghost', 'params' => []]);
		$this->assertSame('', $this->protocol->callMethod($payload));
	}

	public function testCallMethodNotificationThrowingMethodReturnsEmptyString(): void
	{
		// Spec: server MUST NOT reply to notifications, even when the handler throws.
		$payload = json_encode(['jsonrpc' => '2.0', 'method' => 'boom', 'params' => []]);
		$this->assertSame('', $this->protocol->callMethod($payload));
	}

	public function testCallMethodNotificationCrashingMethodReturnsEmptyString(): void
	{
		// Spec: server MUST NOT reply to notifications, including on internal errors.
		$payload = json_encode(['jsonrpc' => '2.0', 'method' => 'crash', 'params' => []]);
		$this->assertSame('', $this->protocol->callMethod($payload));
	}

	public function testCallMethodParseErrorBeforeIdStillResponds(): void
	{
		// Spec: parse errors (before id is known) must still return an error response.
		$response = json_decode($this->protocol->callMethod('not valid json'), true);
		$this->assertNotNull($response['error']);
		$this->assertSame(-32700, $response['error']['code']);
	}

	// ── Parameter normalization ───────────────────────────────────────────────

	public function testCallMethodWithMissingParamsDefaultsToEmpty(): void
	{
		$protocol = new TJsonRpcProtocol();
		$called = false;
		$protocol->addMethod('noparams', ['method' => function () use (&$called) {
			$called = true;
			return 'ok';
		}]);
		$payload = json_encode(['jsonrpc' => '2.0', 'method' => 'noparams', 'id' => 1]);
		$protocol->callMethod($payload);
		$this->assertTrue($called);
	}

	public function testCallApiMethodHandlesNullParams(): void
	{
		$this->protocol->addMethod('ping', ['method' => fn() => 'pong']);
		$result = $this->protocol->callApiMethod('ping', null);
		$this->assertSame('pong', $result);
	}

	public function testCallApiMethodCoercesScalarParams(): void
	{
		$this->protocol->addMethod('echo', ['method' => fn($v) => $v]);
		$result = $this->protocol->callApiMethod('echo', 'scalar');
		$this->assertSame('scalar', $result);
	}

	// ── Unknown method ────────────────────────────────────────────────────────

	public function testCallMethodUnknownMethodReturnsFault(): void
	{
		$response = json_decode($this->protocol->callMethod(self::req('noSuchMethod')), true);
		$this->assertNotNull($response['error']);
		$this->assertSame('2.0', $response['jsonrpc']);
	}

	public function testCallApiMethodThrowsForUnknownMethodWithJsonRpcCode(): void
	{
		try {
			$this->protocol->callApiMethod('ghost', []);
			$this->fail('Expected TRpcException');
		} catch (TRpcException $e) {
			$this->assertSame(-32601, $e->getErrorCode());
		}
	}

	// ── Error handling ────────────────────────────────────────────────────────

	public function testCallMethodTRpcExceptionReturnsFaultResponse(): void
	{
		$response = json_decode($this->protocol->callMethod(self::req('boom')), true);
		$this->assertNotNull($response['error']);
	}

	public function testCallMethodTRpcExceptionPreservesMessage(): void
	{
		$response = json_decode($this->protocol->callMethod(self::req('boom')), true);
		$this->assertStringContainsString('Custom error', $response['error']['message']);
	}

	public function testCallMethodTRpcExceptionPreservesCode(): void
	{
		$response = json_decode($this->protocol->callMethod(self::req('boom')), true);
		$this->assertSame(-99, $response['error']['code']);
	}

	public function testCallMethodTHttpExceptionIsRethrown(): void
	{
		$this->expectException(THttpException::class);
		$this->protocol->callMethod(self::req('httpBoom'));
	}

	public function testCallMethodGenericExceptionReturnsInternalError(): void
	{
		$response = json_decode($this->protocol->callMethod(self::req('crash')), true);
		$this->assertNotNull($response['error']);
		$this->assertStringContainsString('internal error', $response['error']['message']);
	}

	public function testCallMethodMissingMethodKeyReturnsFault(): void
	{
		$payload = json_encode(['jsonrpc' => '2.0', 'params' => [], 'id' => 1]);
		$response = json_decode($this->protocol->callMethod($payload), true);
		$this->assertNotNull($response['error']);
	}

	public function testCallMethodMalformedJsonReturnsParseFault(): void
	{
		$response = json_decode($this->protocol->callMethod('not valid json {{{'), true);
		$this->assertSame('2.0', $response['jsonrpc']);
		$this->assertNotNull($response['error']);
		$this->assertSame(-32700, $response['error']['code']);
	}

	public function testCallMethodEmptyPayloadReturnsParseFault(): void
	{
		$response = json_decode($this->protocol->callMethod(''), true);
		$this->assertNotNull($response['error']);
		$this->assertSame(-32700, $response['error']['code']);
	}

	// ── JSON-RPC 1.0 (no jsonrpc field) ─────────────────────────────────────

	public function testCallMethod10SuccessResponseHasNoJsonrpcField(): void
	{
		$payload = json_encode(['method' => 'add', 'params' => [1, 2], 'id' => 1]);
		$response = json_decode($this->protocol->callMethod($payload), true);
		$this->assertArrayNotHasKey('jsonrpc', $response);
	}

	public function testCallMethod10SuccessResponseHasResult(): void
	{
		$payload = json_encode(['method' => 'add', 'params' => [3, 4], 'id' => 1]);
		$response = json_decode($this->protocol->callMethod($payload), true);
		$this->assertSame(7, $response['result']);
	}

	public function testCallMethod10SuccessResponseHasNullError(): void
	{
		$payload = json_encode(['method' => 'add', 'params' => [1, 2], 'id' => 1]);
		$response = json_decode($this->protocol->callMethod($payload), true);
		$this->assertNull($response['error']);
	}

	public function testCallMethod10ErrorResponseHasNullResult(): void
	{
		$payload = json_encode(['method' => 'boom', 'params' => [], 'id' => 1]);
		$response = json_decode($this->protocol->callMethod($payload), true);
		$this->assertNull($response['result']);
	}

	public function testCallMethod10ErrorResponseHasStringError(): void
	{
		$payload = json_encode(['method' => 'boom', 'params' => [], 'id' => 1]);
		$response = json_decode($this->protocol->callMethod($payload), true);
		$this->assertIsString($response['error']);
		$this->assertStringContainsString('Custom error', $response['error']);
	}

	public function testCallMethod10NotificationNullIdReturnsEmptyString(): void
	{
		// JSON-RPC 1.0 notification: id is null (not absent).
		$payload = json_encode(['method' => 'add', 'params' => [1, 2], 'id' => null]);
		$result = $this->protocol->callMethod($payload);
		$this->assertSame('', $result);
	}

	public function testCallMethod10DoesNotLeakVersionIntoNextCall(): void
	{
		// After a 1.0 call, a subsequent 2.0 call must still return 2.0 format.
		$this->protocol->callMethod(json_encode(['method' => 'add', 'params' => [1, 2], 'id' => 1]));
		$response = json_decode($this->protocol->callMethod(self::req('add', [1, 2])), true);
		$this->assertSame('2.0', $response['jsonrpc']);
	}

	// ── createErrorResponse ───────────────────────────────────────────────────

	public function testCreateErrorResponseHasJsonrpcField(): void
	{
		$e = new TRpcException('Something broke', -5);
		$response = json_decode($this->protocol->createErrorResponse($e), true);
		$this->assertSame('2.0', $response['jsonrpc']);
	}

	public function testCreateErrorResponseHasErrorField(): void
	{
		$e = new TRpcException('Something broke', -5);
		$response = json_decode($this->protocol->createErrorResponse($e), true);
		$this->assertArrayHasKey('error', $response);
	}

	public function testCreateErrorResponseUsesErrorCode(): void
	{
		$e = new TRpcException('Something broke', -5);
		$response = json_decode($this->protocol->createErrorResponse($e), true);
		$this->assertSame(-5, $response['error']['code']);
	}

	public function testCreateErrorResponseHasNoResultKey(): void
	{
		$e = new TRpcException('err');
		$response = json_decode($this->protocol->createErrorResponse($e), true);
		$this->assertArrayNotHasKey('result', $response);
	}

	// ── decode / encode ───────────────────────────────────────────────────────

	public function testDecodeReturnsPhpArray(): void
	{
		$result = $this->protocol->decode('{"jsonrpc":"2.0","method":"ping","params":[],"id":1}');
		$this->assertIsArray($result);
		$this->assertSame('ping', $result['method']);
	}

	public function testEncodeReturnsJsonString(): void
	{
		$result = $this->protocol->encode(['jsonrpc' => '2.0', 'result' => 42, 'id' => 1]);
		$decoded = json_decode($result, true);
		$this->assertSame(42, $decoded['result']);
	}

	// ── createResponseHeaders ─────────────────────────────────────────────────

	public function testCreateResponseHeadersSetsContentType(): void
	{
		$response = new class {
			public string $contentType = '';
			public string $charset = '';

			public function setContentType(string $v): void
			{
				$this->contentType = $v;
			}

			public function setCharset(string $v): void
			{
				$this->charset = $v;
			}
		};
		$this->protocol->createResponseHeaders($response);
		$this->assertSame('application/json', $response->contentType);
	}

	public function testCreateResponseHeadersSetsCharset(): void
	{
		$response = new class {
			public string $contentType = '';
			public string $charset = '';

			public function setContentType(string $v): void
			{
				$this->contentType = $v;
			}

			public function setCharset(string $v): void
			{
				$this->charset = $v;
			}
		};
		$this->protocol->createResponseHeaders($response);
		$this->assertSame('UTF-8', $response->charset);
	}
}
