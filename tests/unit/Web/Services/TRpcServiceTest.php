<?php

use Prado\Exceptions\TConfigurationException;
use Prado\Exceptions\THttpException;
use Prado\Web\Services\TJsonRpcProtocol;
use Prado\Web\Services\TRpcApiProvider;
use Prado\Web\Services\TRpcServer;
use Prado\Web\Services\TRpcService;
use Prado\Web\THttpRequest;
use Prado\Xml\TXmlDocument;

class ConfigTestApiProvider extends TRpcApiProvider
{
	private string $_label = '';

	public function registerMethods(): array
	{
		return [];
	}

	public function getLabel(): string
	{
		return $this->_label;
	}

	public function setLabel(string $value): void
	{
		$this->_label = $value;
	}
}

class ConfigTestRpcServer extends TRpcServer
{
}

class RequestTestRpcService extends TRpcService
{
	public ?THttpRequest $request = null;

	public function getRequest()
	{
		return $this->request;
	}
}

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

	public function testLoadConfigHandlesArrayWithoutApis(): void
	{
		$service = new TRpcService();
		$service->loadConfig(['class' => TRpcService::class]);
		$this->assertSame([], $this->getApiProviders($service));
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

		$providers = $this->getApiProviders($service);
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

		$providers = $this->getApiProviders($service);
		$this->assertArrayHasKey('customers', $providers);
		$this->assertArrayHasKey('orders', $providers);
	}

	// ── loadConfig — PHP configuration ───────────────────────────────────────

	public function testLoadConfigArrayRegistersApisById(): void
	{
		$service = new TRpcService();
		$service->loadConfig([
			'class' => TRpcService::class,
			'apis' => [
				'customers' => ['class' => 'A', 'server' => 'S'],
				'orders' => ['class' => 'B'],
			],
		]);

		$providers = $this->getApiProviders($service);
		$this->assertSame(['customers', 'orders'], array_keys($providers));
		$this->assertSame(['class' => 'A', 'server' => 'S'], $providers['customers']->toArray());
		$this->assertSame(['class' => 'B'], $providers['orders']->toArray());
	}

	public function testLoadConfigArrayDropsIdProperty(): void
	{
		$service = new TRpcService();
		$service->loadConfig(['apis' => ['customers' => ['id' => 'other', 'class' => 'A']]]);

		$providers = $this->getApiProviders($service);
		$this->assertSame(['customers'], array_keys($providers));
		$this->assertSame(['class' => 'A'], $providers['customers']->toArray());
	}

	public function testLoadConfigArrayNonArrayEntryHasNoProperties(): void
	{
		$service = new TRpcService();
		$service->loadConfig(['apis' => ['customers' => 'A']]);

		$providers = $this->getApiProviders($service);
		$this->assertSame([], $providers['customers']->toArray());
	}

	public function testLoadConfigArrayThrowsOnIntegerKey(): void
	{
		$service = new TRpcService();
		$this->expectException(TConfigurationException::class);
		$service->loadConfig(['apis' => [['class' => 'A']]]);
	}

	public function testLoadConfigArrayThrowsOnEmptyKey(): void
	{
		$service = new TRpcService();
		$this->expectException(TConfigurationException::class);
		$service->loadConfig(['apis' => ['' => ['class' => 'A']]]);
	}

	public function testLoadConfigArrayThrowsOnIdDuplicatedFromXml(): void
	{
		$service = new TRpcService();
		$doc = new TXmlDocument();
		$doc->loadFromString('<service><rpcapi id="shared" class="A"/></service>');
		$service->loadConfig($doc);

		$this->expectException(TConfigurationException::class);
		$service->loadConfig(['apis' => ['shared' => ['class' => 'B']]]);
	}

	public function testCreateApiProviderFromArrayConfig(): void
	{
		$service = new TRpcService();
		$service->loadConfig(['apis' => ['customers' => [
			'class' => ConfigTestApiProvider::class,
			'server' => ConfigTestRpcServer::class,
			'Label' => 'crm',
		]]]);

		$provider = $service->createApiProvider(new TJsonRpcProtocol(), 'customers');

		$this->assertInstanceOf(ConfigTestApiProvider::class, $provider);
		$this->assertSame('customers', $provider->getId());
		$this->assertSame('crm', $provider->getLabel());
		$this->assertInstanceOf(ConfigTestRpcServer::class, $provider->getRpcServer());
	}

	public function testCreateApiProviderFromArrayConfigThrowsWithoutClass(): void
	{
		$service = new TRpcService();
		$service->loadConfig(['apis' => ['customers' => []]]);

		$this->expectException(TConfigurationException::class);
		$this->expectExceptionMessage("RPC API provider 'customers' requires a 'class' property.");
		$service->createApiProvider(new TJsonRpcProtocol(), 'customers');
	}

	public function testCreateApiProviderThrowsOnInvalidProviderClass(): void
	{
		$service = new TRpcService();
		$service->loadConfig(['apis' => ['customers' => ['class' => ConfigTestRpcServer::class]]]);

		$this->expectException(TConfigurationException::class);
		$this->expectExceptionMessage("RPC API provider class '" . ConfigTestRpcServer::class . "' of provider 'customers' must extend");
		$service->createApiProvider(new TJsonRpcProtocol(), 'customers');
	}

	public function testCreateApiProviderThrowsOnInvalidServerClass(): void
	{
		$service = new TRpcService();
		$service->loadConfig(['apis' => ['customers' => [
			'class' => ConfigTestApiProvider::class,
			'server' => ConfigTestApiProvider::class,
		]]]);

		$this->expectException(TConfigurationException::class);
		$this->expectExceptionMessage("RPC server class '" . ConfigTestApiProvider::class . "' of provider 'customers' must be");
		$service->createApiProvider(new TJsonRpcProtocol(), 'customers');
	}

	// ── loadConfig — validation errors ───────────────────────────────────────

	public function testLoadConfigThrowsOnMissingId(): void
	{
		$service = new TRpcService();
		$doc = new TXmlDocument();
		$doc->loadFromString('<service><rpcapi class="SomeProvider"/></service>');
		$this->expectException(TConfigurationException::class);
		$this->expectExceptionMessage('Each RPC API provider requires a non-empty id.');
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
		$this->expectExceptionMessage("RPC API provider id 'dup' is already defined.");
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

	// ── run — CSRF validation ─────────────────────────────────────────────────

	public function testRunRefusesPostWithInvalidCsrfToken(): void
	{
		$service = $this->createRunService('POST', false);
		$service->setEnableCsrfValidation(true);
		$e = $this->runAndCatch($service);
		$this->assertSame(400, $e->getStatusCode());
		$this->assertSame('service_csrf_token_invalid', $e->getErrorCode());
	}

	public function testRunAcceptsPostWithValidCsrfToken(): void
	{
		$service = $this->createRunService('POST', true);
		$service->setEnableCsrfValidation(true);
		$this->assertSame('rpcservice_apiproviderid_missing', $this->runAndCatch($service)->getErrorCode());
	}

	public function testRunSkipsCsrfCheckForGet(): void
	{
		$service = $this->createRunService('GET', false);
		$service->setEnableCsrfValidation(true);
		$this->assertSame('rpcservice_apiproviderid_missing', $this->runAndCatch($service)->getErrorCode());
	}

	public function testRunSkipsCsrfCheckByDefault(): void
	{
		$service = $this->createRunService('POST', false);
		$this->assertFalse($service->getEnableCsrfValidation());
		$this->assertSame('rpcservice_apiproviderid_missing', $this->runAndCatch($service)->getErrorCode());
	}

	private function createRunService(string $requestType, bool $tokenValid): RequestTestRpcService
	{
		$request = $this->createMock(THttpRequest::class);
		$request->method('getRequestType')->willReturn($requestType);
		$request->method('validateCsrfToken')->willReturn($tokenValid);
		$request->method('getServiceParameter')->willReturn('');
		$service = new RequestTestRpcService();
		$service->request = $request;
		return $service;
	}

	private function runAndCatch(TRpcService $service): THttpException
	{
		try {
			$service->run();
		} catch (THttpException $e) {
			return $e;
		}
		$this->fail('run() did not throw THttpException.');
	}

	private function getApiProviders(TRpcService $service): array
	{
		return \Closure::bind(fn() => $this->apiProviders, $service, TRpcService::class)();
	}
}
