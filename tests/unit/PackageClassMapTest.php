<?php

/**
 * Tests for the package class map declared in composer.json's extra.prado.class-map
 * and stored in config/classes.json.
 *
 * The map lets application configurations and internal short-name resolution (such as
 * TRpcService instantiating a protocol handler via `new 'TJsonRpcProtocol'()`) reach the
 * fully-qualified classes. The phpunit bootstrap registers it with Prado\Prado::registerClassMap()
 * the way TApplicationConfiguration registers it for an installed extension.
 *
 * Prado::registerClassMap() keeps the first mapping for a name, so while prado still ships the
 * legacy Prado\Util RPC clients their short names win over this package's. These tests assert the
 * package's own contract — its classes autoload and the map is usable — without depending on prado
 * having dropped its bundled RPC yet.
 */
class PackageClassMapTest extends PHPUnit\Framework\TestCase
{
	/**
	 * @return array<string,string> the short name => FQN map from config/classes.json.
	 */
	private function classMap(): array
	{
		$json = file_get_contents(__DIR__ . '/../../config/classes.json');
		return json_decode($json, true);
	}

	public function testClassesJsonIsValidNonEmptyObject(): void
	{
		$map = $this->classMap();
		$this->assertIsArray($map);
		$this->assertNotEmpty($map);
	}

	public function testEveryMappedFqnAutoloads(): void
	{
		foreach ($this->classMap() as $shortName => $fqn) {
			$this->assertTrue(class_exists($fqn), "Mapped FQN does not autoload: {$fqn}");
		}
	}

	public function testEveryShortNameResolvesToASameNamedClass(): void
	{
		foreach ($this->classMap() as $shortName => $fqn) {
			$this->assertTrue(class_exists($shortName), "Short name does not resolve: {$shortName}");
			$resolved = (new ReflectionClass($shortName))->getShortName();
			$this->assertSame(
				$shortName,
				$resolved,
				"Short name {$shortName} resolved to {$resolved}"
			);
		}
	}

	public function testServerSideShortNamesMapIntoThisPackage(): void
	{
		// The server-side classes are referenced by short name in application <service>/<rpcapi>
		// configuration and have no legacy Prado\Util counterpart, so they resolve into this
		// package's namespace regardless of prado's bundled RPC.
		$serverSide = [
			'TRpcService', 'TRpcServer', 'TRpcProtocol',
			'TJsonRpcProtocol', 'TXmlRpcProtocol', 'TRpcApiProvider', 'TRpcException',
		];
		foreach ($serverSide as $shortName) {
			$this->assertSame(
				'Prado\\Web\\Services\\' . $shortName,
				ltrim((new ReflectionClass($shortName))->getName(), '\\'),
				"{$shortName} did not resolve into Prado\\Web\\Services"
			);
		}
	}

	public function testRegisteredShortNameInstantiatesViaClassMap(): void
	{
		// The extension's own code resolves classes by FQN; the class map exists for application
		// configurations that reference the historical short names. Instantiating a global short
		// name proves the registered map keeps that backward-compatible path working.
		$handler = new TJsonRpcProtocol();
		$this->assertInstanceOf(\Prado\Web\Services\TJsonRpcProtocol::class, $handler);
	}
}
