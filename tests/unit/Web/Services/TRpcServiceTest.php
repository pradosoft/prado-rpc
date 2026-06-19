<?php

use Prado\Exceptions\TConfigurationException;
use Prado\Web\Services\TRpcService;
use Prado\Xml\TXmlDocument;

/**
 * Tests for TRpcService — configuration parsing.
 */
class TRpcServiceTest extends PHPUnit\Framework\TestCase
{
	// ── loadConfig — null / non-XML ───────────────────────────────────────────

	public function testLoadConfigHandlesNull(): void
	{
		$service = new TRpcService();
		$service->loadConfig(null);
		// No exception — null config is a no-op.
		$this->assertTrue(true);
	}

	public function testLoadConfigHandlesArray(): void
	{
		$service = new TRpcService();
		$service->loadConfig(['api' => 'someValue']);
		$this->assertTrue(true);
	}

	public function testLoadConfigHandlesEmptyXml(): void
	{
		$service = new TRpcService();
		$doc = new TXmlDocument();
		$doc->loadFromString('<service/>');
		$service->loadConfig($doc);
		$this->assertTrue(true);
	}

	// ── loadConfig — valid rpcapi entries ─────────────────────────────────────

	public function testLoadConfigRegistersApiId(): void
	{
		$service = new TRpcService();
		$doc = new TXmlDocument();
		$doc->loadFromString('<service><rpcapi id="customers" class="SomeProvider"/></service>');
		$service->loadConfig($doc);

		$providers = \Closure::bind(fn() => $this->apiProviders, $service, TRpcService::class)();
		$this->assertArrayHasKey('customers', $providers);
	}

	public function testLoadConfigRegistersMultipleApis(): void
	{
		$service = new TRpcService();
		$doc = new TXmlDocument();
		$doc->loadFromString(
			'<service>'
			. '<rpcapi id="customers" class="A"/>'
			. '<rpcapi id="orders" class="B"/>'
			. '</service>'
		);
		$service->loadConfig($doc);

		$providers = \Closure::bind(fn() => $this->apiProviders, $service, TRpcService::class)();
		$this->assertArrayHasKey('customers', $providers);
		$this->assertArrayHasKey('orders', $providers);
	}

	// ── loadConfig — validation errors ───────────────────────────────────────

	public function testLoadConfigThrowsOnMissingId(): void
	{
		$service = new TRpcService();
		$doc = new TXmlDocument();
		$doc->loadFromString('<service><rpcapi class="SomeProvider"/></service>');
		$this->expectException(TConfigurationException::class);
		$service->loadConfig($doc);
	}

	public function testLoadConfigThrowsOnEmptyId(): void
	{
		$service = new TRpcService();
		$doc = new TXmlDocument();
		$doc->loadFromString('<service><rpcapi id="" class="SomeProvider"/></service>');
		$this->expectException(TConfigurationException::class);
		$service->loadConfig($doc);
	}

	public function testLoadConfigThrowsOnDuplicateId(): void
	{
		$service = new TRpcService();
		$doc = new TXmlDocument();
		$doc->loadFromString(
			'<service>'
			. '<rpcapi id="dup" class="A"/>'
			. '<rpcapi id="dup" class="B"/>'
			. '</service>'
		);
		$this->expectException(TConfigurationException::class);
		$service->loadConfig($doc);
	}

	public function testLoadConfigThrowsOnDuplicateIdAcrossTwoCalls(): void
	{
		$service = new TRpcService();
		$doc1 = new TXmlDocument();
		$doc1->loadFromString('<service><rpcapi id="shared" class="A"/></service>');
		$service->loadConfig($doc1);

		$doc2 = new TXmlDocument();
		$doc2->loadFromString('<service><rpcapi id="shared" class="B"/></service>');
		$this->expectException(TConfigurationException::class);
		$service->loadConfig($doc2);
	}
}
