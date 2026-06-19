<?php

use Prado\Exceptions\THttpException;
use Prado\IO\Rpc\TXmlRpcCodec;
use Prado\Web\Services\TRpcException;
use Prado\Web\Services\TXmlRpcProtocol;

/**
 * Tests for TXmlRpcProtocol — XML-RPC server protocol handler.
 */
class TXmlRpcProtocolTest extends PHPUnit\Framework\TestCase
{
	private TXmlRpcProtocol $protocol;

	protected function setUp(): void
	{
		$this->protocol = new TXmlRpcProtocol();
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function buildRequest(string $method, array $params = []): string
	{
		$paramsXml = '';
		foreach ($params as $p) {
			$paramsXml .= '<param><value>' . self::encodeScalar($p) . '</value></param>';
		}
		return '<?xml version="1.0"?><methodCall><methodName>' . htmlspecialchars($method, ENT_XML1, 'UTF-8')
			. '</methodName><params>' . $paramsXml . '</params></methodCall>';
	}

	private static function encodeScalar(mixed $v): string
	{
		if (is_int($v)) {
			return '<int>' . $v . '</int>';
		}
		if (is_string($v)) {
			return '<string>' . htmlspecialchars($v, ENT_XML1, 'UTF-8') . '</string>';
		}
		return '<string>' . htmlspecialchars((string) $v, ENT_XML1, 'UTF-8') . '</string>';
	}

	// ── callMethod ────────────────────────────────────────────────────────────

	public function testCallMethodDispatchesAndReturnsEncodedResponse(): void
	{
		$this->protocol->addMethod('double', ['method' => fn($n) => $n * 2]);
		$response = $this->protocol->callMethod(self::buildRequest('double', [5]));
		$this->assertStringContainsString('<int>10</int>', $response);
		$this->assertStringContainsString('<methodResponse>', $response);
	}

	public function testCallMethodReturnsStringResult(): void
	{
		$this->protocol->addMethod('greet', ['method' => fn($name) => 'Hello, ' . $name]);
		$response = $this->protocol->callMethod(self::buildRequest('greet', ['World']));
		$decoded = TXmlRpcCodec::decodeResponse($response);
		$this->assertSame('Hello, World', $decoded);
	}

	public function testCallMethodInvalidXmlReturnsFaultResponse(): void
	{
		$response = $this->protocol->callMethod('not valid xml');
		$this->assertStringContainsString('<fault>', $response);
	}

	public function testCallMethodUnknownMethodReturnsFaultResponse(): void
	{
		$response = $this->protocol->callMethod(self::buildRequest('noSuchMethod'));
		$this->assertStringContainsString('<fault>', $response);
		$decoded = TXmlRpcCodec::decodeResponse($response);
		$this->assertTrue(TXmlRpcCodec::isFault($decoded));
	}

	public function testCallMethodTRpcExceptionReturnsFaultResponse(): void
	{
		$this->protocol->addMethod('boom', ['method' => function () {
			throw new TRpcException('Custom error', -99);
		}]);
		$response = $this->protocol->callMethod(self::buildRequest('boom'));
		$this->assertStringContainsString('<fault>', $response);
		$decoded = TXmlRpcCodec::decodeResponse($response);
		$this->assertTrue(TXmlRpcCodec::isFault($decoded));
		$this->assertSame('Custom error', $decoded['faultString']);
	}

	public function testCallMethodTHttpExceptionIsRethrown(): void
	{
		$this->protocol->addMethod('secure', ['method' => function () {
			throw new THttpException(403, 'Forbidden');
		}]);
		$this->expectException(THttpException::class);
		$this->protocol->callMethod(self::buildRequest('secure'));
	}

	public function testCallMethodGenericExceptionReturnsInternalError(): void
	{
		$this->protocol->addMethod('crash', ['method' => function () {
			throw new \RuntimeException('Unexpected failure');
		}]);
		$response = $this->protocol->callMethod(self::buildRequest('crash'));
		$this->assertStringContainsString('<fault>', $response);
		$decoded = TXmlRpcCodec::decodeResponse($response);
		$this->assertTrue(TXmlRpcCodec::isFault($decoded));
		$this->assertStringContainsString('internal error', $decoded['faultString']);
	}

	public function testCallMethodWithNoParamsMethod(): void
	{
		$this->protocol->addMethod('ping', ['method' => fn() => 'pong']);
		$response = $this->protocol->callMethod(self::buildRequest('ping'));
		$decoded = TXmlRpcCodec::decodeResponse($response);
		$this->assertSame('pong', $decoded);
	}

	// ── createErrorResponse ───────────────────────────────────────────────────

	public function testCreateErrorResponseProducesXmlRpcFault(): void
	{
		$e = new TRpcException('Test error', -5);
		$response = $this->protocol->createErrorResponse($e);
		$this->assertStringContainsString('<fault>', $response);
	}

	public function testCreateErrorResponseIncludesFaultCode(): void
	{
		$e = new TRpcException('Test error', -5);
		$response = $this->protocol->createErrorResponse($e);
		$this->assertStringContainsString('<int>-5</int>', $response);
	}

	public function testCreateErrorResponseIncludesFaultString(): void
	{
		$e = new TRpcException('Test error', -5);
		$response = $this->protocol->createErrorResponse($e);
		$this->assertStringContainsString('Test error', $response);
	}

	public function testCreateErrorResponseDefaultErrorCode(): void
	{
		$e = new TRpcException('Default code error');
		$response = $this->protocol->createErrorResponse($e);
		$decoded = TXmlRpcCodec::decodeResponse($response);
		$this->assertSame(-1, $decoded['faultCode']);
	}

	// ── decode / encode ───────────────────────────────────────────────────────

	public function testDecodeReturnsMethodAndParams(): void
	{
		$xml = self::buildRequest('doSomething', [42]);
		$result = $this->protocol->decode($xml);
		$this->assertSame('doSomething', $result['method']);
		$this->assertSame([42], $result['params']);
	}

	public function testDecodeReturnsFalseOnInvalidXml(): void
	{
		$this->assertFalse($this->protocol->decode('garbage'));
	}

	public function testEncodeReturnsMethodResponse(): void
	{
		$result = $this->protocol->encode('hello');
		$this->assertStringContainsString('<methodResponse>', $result);
		$this->assertStringContainsString('<string>hello</string>', $result);
	}

	// ── addMethod ─────────────────────────────────────────────────────────────

	public function testAddMethodRegistersMethod(): void
	{
		$this->protocol->addMethod('test', ['method' => fn() => 'ok']);
		$response = $this->protocol->callMethod(self::buildRequest('test'));
		$decoded = TXmlRpcCodec::decodeResponse($response);
		$this->assertSame('ok', $decoded);
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
		$this->assertSame('text/xml', $response->contentType);
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
