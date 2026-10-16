# PRADO RPC Extension

[![Tests](https://github.com/pradosoft/prado-rpc/actions/workflows/prado-rpc.yml/badge.svg)](https://github.com/pradosoft/prado-rpc/actions/workflows/prado-rpc.yml)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE)

This package adds JSON-RPC and XML-RPC support to the [PRADO PHP Framework](https://github.com/pradosoft/prado) (4.4 and up), and it covers both sides of the conversation: a service you plug into your application to expose an API, and client classes for calling RPC servers from PHP.

On the server, `TRpcService` takes incoming HTTP POST requests and routes them to your API providers, speaking JSON-RPC 1.0/2.0 (`application/json`) or XML-RPC (`text/xml`) depending on the request's Content-Type. On the client, `TRpcClient` and its subclasses ride on the PRADO HTTP client (`Prado\IO\HttpClient`), so you can swap the transport or add caching without touching your calling code.

## Documentation

The full guides live in [`docs/`](docs/README.md):

- **[Server](docs/server.md)** — exposing an API with `TRpcService` and `TRpcApiProvider`: configuration, routing, faults, custom servers
- **[Client](docs/client.md)** — calling a remote server with `TRpcClient`: the factory, protocol versions, notifications, transport, error handling
- **[XML-RPC codec](docs/xml-rpc-codec.md)** — the pure-PHP `TXmlRpcCodec` that stands in for PHP's removed `xmlrpc` extension: type mapping, base64, dates
- **[Migration](docs/migration.md)** — moving from the framework-bundled RPC classes to this package

If you're contributing, the per-class working notes under [`agents/`](agents/INDEX.md) are a good map of the internals.

## Requirements

You'll need a PHP version actually supported by the [PRADO PHP Framework](https://github.com/pradosoft/prado) with the `simplexml` and `dom` extensions — both are part of stock PHP builds. The XML-RPC codec uses `simplexml` for decoding, and `dom` handles XML request parsing. The package runs inside a PRADO 4.4+ application, which supplies the framework itself; `pradosoft/prado` is only a dev dependency of this package.

## Installation

### Into a PRADO application

```sh
composer require pradosoft/prado-rpc
```

That's the whole install. PRADO reads the package's `extra.prado` section and registers its class map and error messages automatically — no bootstrap module, no configuration changes, and any existing `class="TRpcService"` service definitions keep working. To start serving, declare the service (details in the [server guide](docs/server.md)):

```xml
<service id="rpc" class="Prado\Web\Services\TRpcService">
	<rpcapi id="customers" class="Application.Api.CustomersApi" />
</service>
```

> **Until PRADO 4.4 reaches Packagist**, you'll need to pull the framework from source. In your application's `composer.json`, allow dev stability and require `pradosoft/prado` as `dev-master`:
>
> ```json
> "minimum-stability": "dev",
> "prefer-stable": true,
> "repositories": [
>     { "type": "vcs", "url": "https://github.com/pradosoft/prado" }
> ],
> "require": {
>     "pradosoft/prado": "dev-master",
>     "pradosoft/prado-rpc": "^4.4"
> }
> ```
>
> Once 4.4 is published, delete this block and the plain `composer require` above will do the right thing.

### For development

To hack on the extension itself or run its test suite:

```sh
git clone https://github.com/pradosoft/prado-rpc.git
cd prado-rpc
composer install
```

There's nothing else to set up. `composer.json` points at the framework's git repository, so `composer install` fetches `pradosoft/prado` (`dev-master`) straight from GitHub along with the dev tools — no local framework checkout needed. The `asset-packagist.org` entry covers the framework's bower assets, and `"prefer-stable": true` keeps every other dependency on stable releases despite the dev-stability floor. When 4.4 ships, the constraint becomes `^4.4` and the VCS repository and stability flags go away.

Then check that everything works:

```sh
composer unittest                                    # unit tests
vendor/bin/php-cs-fixer fix --dry-run src/           # code style
vendor/bin/phpstan analyse src/ --memory-limit=512M  # static analysis
```

CI ([`.github/workflows/prado-rpc.yml`](.github/workflows/prado-rpc.yml)) runs those same checks on PHP 8.1, 8.2, and 8.3 for every push and pull request. Code style is PSR-12 with tabs, enforced by `.php-cs-fixer.dist.php`.

## Serving an API

Two steps: declare the service with its API providers, then write a provider that says which methods it exposes.

```xml
<service id="rpc" class="Prado\Web\Services\TRpcService">
	<rpcapi id="customers" class="Application.Api.CustomersApi" />
</service>
```

```php
namespace Application\Api;

use Prado\Web\Services\TRpcApiProvider;

class CustomersApi extends TRpcApiProvider
{
	public function registerMethods(): array
	{
		return [
			'getCustomer'   => ['method' => [$this, 'getCustomer']],
			'listCustomers' => ['method' => [$this, 'listCustomers']],
		];
	}

	public function getCustomer(int $id): array { /* ... */ }
	public function listCustomers(string $status = 'active'): array { /* ... */ }
}
```

A request arrives at `TRpcService`, which hands it to a `TRpcServer`, which asks the protocol to decode it and dispatch to your provider. The protocol is chosen by the request's Content-Type: `application/json` gets `TJsonRpcProtocol` (JSON-RPC 1.0 and 2.0), `text/xml` gets `TXmlRpcProtocol` (XML-RPC). The [server guide](docs/server.md) covers the configuration variants, fault handling, and custom servers.

## Calling an RPC server

Create a client and call methods on it as if they were local — unknown method calls become RPC requests through `__call`:

```php
use Prado\IO\Rpc\TRpcClient;

$client = TRpcClient::create('json', 'https://host/rpc');

$customer = $client->getCustomer(42);
$orders   = $client->listOrders($customer['id']);
```

Pass `'json'` or `'xml'` to `create()` to pick the protocol; anything else throws a `TApplicationException`. The JSON client speaks JSON-RPC 2.0 out of the box, and `setVersion('1.0')` switches it to 1.0 if you need that.

```php
$client = TRpcClient::create('xml', 'https://host/rpc');        // XML-RPC
$notify = TRpcClient::create('json', 'https://host/rpc', true); // notification mode
```

A client in notification mode fires requests and ignores the responses. JSON-RPC 2.0 notifications omit the `id` member entirely; 1.0 sends `"id": null`.

When things go wrong you get one of two exceptions: `TRpcClientRequestException` for transport failures (connection errors, non-2xx responses) and `TRpcClientResponseException` for server faults or responses that won't parse. And if the default HTTP transport doesn't suit you, `setDownloader()` accepts any `Prado\IO\HttpClient\THttpClient`, including the caching wrapper. More in the [client guide](docs/client.md).

## XML-RPC on PHP 8

PHP dropped the bundled `xmlrpc` extension in 8.0, which left XML-RPC users hunting for a PECL build. This package sidesteps the problem: `TXmlRpcCodec` is a pure-PHP encoder/decoder built on `ext-dom` and `ext-simplexml`, so XML-RPC works on stock PHP 8.1+ with nothing extra to install. The [codec guide](docs/xml-rpc-codec.md) documents the type mapping, base64 handling, and dates.

## Classes

| Class | Role |
|---|---|
| `Prado\Web\Services\TRpcService` | The PRADO service: parses `<rpcapi>` config, selects protocol by Content-Type, dispatches |
| `Prado\Web\Services\TRpcServer` | Moves data between the service and the provider; subclass for logging/filtering |
| `Prado\Web\Services\TRpcProtocol` | Abstract wire format: method registry and dispatch |
| `Prado\Web\Services\TJsonRpcProtocol` | JSON-RPC 1.0 and 2.0 |
| `Prado\Web\Services\TXmlRpcProtocol` | XML-RPC |
| `Prado\Web\Services\TRpcApiProvider` | Abstract base for API providers (`registerMethods()`) |
| `Prado\Web\Services\TRpcException` | An RPC fault carrying a protocol-level fault code |
| `Prado\IO\Rpc\TRpcClient` | Client base: factory, HTTP POST transport, notification mode |
| `Prado\IO\Rpc\TJsonRpcClient` | JSON-RPC 2.0 client |
| `Prado\IO\Rpc\TXmlRpcClient` | XML-RPC client |
| `Prado\IO\Rpc\TXmlRpcCodec` | XML-RPC value encoding/decoding |
| `Prado\IO\Rpc\TXmlRpcBase64` | Wrapper marking a string for `<base64>` encoding |
| `Prado\IO\Rpc\TRpcClientRequestException` | Transport-level request failure |
| `Prado\IO\Rpc\TRpcClientResponseException` | Server fault or unparseable response |

## Class resolution and error messages

The package tells PRADO about itself through the `extra.prado` section of `composer.json`, which the framework's `TApplicationConfiguration` reads from every installed extension:

```json
"extra": {
	"prado": {
		"error-messages": "config/messages.txt",
		"class-map": ["config/classes.json"]
	}
}
```

`config/classes.json` maps the short class names (`TRpcService`, `TXmlRpcClient`, and so on) to their fully-qualified names, so application configurations can keep writing `class="TRpcService"` exactly as they did when the classes shipped with the framework. The fully-qualified names work too, through plain PSR-4 autoloading. `config/messages.txt` supplies the `rpc*` exception message texts, registered through `TException::addMessageFile()`. Both are picked up automatically by any application that installs the package.

## History

These classes have been part of PRADO for a long time. They landed in the framework on March 27, 2010 (commit `25da68e7`, "added TRpcClient and TRpcServer - fixing #180") as two files: `framework/Web/Services/TRpcService.php` carried the whole server side — `TRpcService`, `TRpcServer`, `TRpcException`, `TRpcApiProvider`, `TRpcProtocol`, `TJsonRpcProtocol`, and `TXmlRpcProtocol` — while `framework/Util/TRpcClient.php` held the clients: `TRpcClient`, `TJsonRpcClient`, `TXmlRpcClient`, and the two client exceptions. The first stable release to ship them was PRADO 3.2.0 in June 2012, which is why the extracted classes carry `@since 3.2.0`. The files were split one class per file in January 2015 (first released in 4.0.0), and in June 2026 the classes moved out of the framework into this repository for PRADO 4.4 (framework commit `54a8def7`).

Two classes are new here: `TXmlRpcCodec` and `TXmlRpcBase64` (`@since 4.4.0`) replace the PECL `xmlrpc` extension the framework versions depended on. Everything else keeps its original namespace, so existing service configurations and client code work unchanged — see the [migration guide](docs/migration.md).

## Versioning and compatibility

The package version follows the PRADO framework's `main` branch, currently **4.4.0**; there is no independent 1.x line. Within a point release, changes stay backward compatible: the public API matches what shipped in the framework, so migrating applications don't need code changes.

## Contributing

Issues and pull requests are welcome. Before submitting, please run the full check chain — syntax, code style, static analysis, and the unit tests — as described under [development setup](#for-development), and include tests for new code: the happy path, the edge cases, and the failure modes.

## License

BSD 3-Clause, the same license as the PRADO framework. See [LICENSE](LICENSE).
