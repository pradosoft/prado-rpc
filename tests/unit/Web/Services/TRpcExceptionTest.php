<?php

use Prado\Web\Services\TRpcException;

/**
 * Tests for TRpcException — constructor argument ordering and error code preservation.
 */
class TRpcExceptionTest extends PHPUnit\Framework\TestCase
{
	public function testConstructorStoresMessage(): void
	{
		$e = new TRpcException('some error');
		$this->assertSame('some error', $e->getMessage());
	}

	public function testConstructorDefaultErrorCode(): void
	{
		$e = new TRpcException('some error');
		$this->assertSame(-1, $e->getErrorCode());
	}

	public function testConstructorStoresIntegerErrorCode(): void
	{
		$e = new TRpcException('some error', 42);
		$this->assertSame(42, $e->getErrorCode());
	}

	public function testConstructorStoresStringErrorCode(): void
	{
		$e = new TRpcException('some error', '-32601');
		$this->assertSame('-32601', $e->getErrorCode());
	}

	public function testConstructorStoresNegativeErrorCode(): void
	{
		$e = new TRpcException('some error', -99);
		$this->assertSame(-99, $e->getErrorCode());
	}

	public function testErrorCodeIsNotOverwrittenByMessage(): void
	{
		// Verifies parent::__construct() runs before setErrorCode() so the error
		// code is not overwritten by TException's old-style message processing.
		$e = new TRpcException('Method not found', -32601);
		$this->assertSame(-32601, $e->getErrorCode());
		$this->assertNotSame('Method not found', $e->getErrorCode());
	}

	public function testPhpNativeGetCodeReturnsZero(): void
	{
		// getCode() is the PHP-native Exception code (always 0 in Prado exceptions);
		// getErrorCode() is the RPC protocol code — the two must not be confused.
		$e = new TRpcException('err', -32601);
		$this->assertSame(0, $e->getCode());
		$this->assertSame(-32601, $e->getErrorCode());
	}
}
