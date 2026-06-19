# PRADO RPC Extension

JSON-RPC and XML-RPC for the [PRADO PHP Framework](https://github.com/pradosoft/prado) (version 4.4+), as both a server-side web service and client classes.

The server side is `TRpcService`, a PRADO service that routes incoming HTTP POST requests to API providers over JSON-RPC 1.0/2.0 (`application/json`) or XML-RPC (`text/xml`). The client side is `TRpcClient` and its protocol subclasses, built on the PRADO HTTP client (`Prado\IO\HttpClient`), so the transport is pluggable and cacheable.

## Documentation

Full guides live in [`docs/`](docs/README.md):

| Guide | Contents |
|---|---|
| [Server](docs/server.md) | Exposing an API with `TRpcService` and `TRpcApiProvider`; configuration, routing, faults, custom servers |
| [Client](docs/client.md) | Calling a remote server with `TRpcClient`; factory, versions, notifications, transport, error handling |
| [XML-RPC codec](docs/xml-rpc-codec.md) | The pure-PHP `TXmlRpcCodec` that replaces PHP's removed `xmlrpc` extension; type mapping, base64, dates |
| [Migration](docs/migration.md) | Moving from the framework-bundled RPC classes to this package |

Contributor working-memory notes for each class are under [`agents/`](agents/INDEX.md).

## Requirements

| Requirement | Scope | Purpose |
|---|---|---|
| PHP 8.1 or higher | required | — |
| `ext-simplexml` | required | XML-RPC encoding/decoding (`TXmlRpcCodec`) |
| `ext-dom` | required | XML request parsing |
| PRADO Framework `^4.4` | dev | `TService`, `TModule`, `TComponent`, the IO/HttpClient layer |

## Installation

### Into a PRADO application

Add the package to an existing PRADO 4.4+ application:

```sh
composer require pradosoft/prado-rpc
```

PRADO reads the package's `extra.prado` section and registers its class map and error messages system-wide. No bootstrap module and no configuration change are required, and existing `class="TRpcService"` service definitions keep working. Declare a service to start serving (see the [server guide](docs/server.md)):

```xml
<service id="rpc" class="Prado\Web\Services\TRpcService">
	<rpcapi id="customers" class="Application.Api.CustomersApi" />
</service>
```

The application supplies the framework, so `pradosoft/prado` is not a runtime dependency of this package. The client classes use the framework's `Prado\IO\HttpClient` layer, which is part of PRADO 4.4; the application must run 4.4 or newer.

> **If PRADO 4.4 is not yet released** on Packagist, resolve the framework from source until it ships. In the application's `composer.json`, allow dev stability and require `pradosoft/prado` as `dev-master`:
>
> ```json
> "minimum-stability": "dev",
> "prefer-stable": true,
> "repositories": [
>     { "type": "vcs", "url": "https://github.com/pradosoft/prado" }
> ],
> "require": {
>     "pradosoft/prado": "dev-master",
>     "pradosoft/prado-rpc": "^1.0"
> }
> ```
>
> Once 4.4 is published, drop this block and the plain `composer require` above resolves it normally.

### Into a PRADO development repository

To develop the extension or run its test suite, clone the repository and install. No local framework checkout is required: `composer.json` declares a VCS repository for the framework, so `composer install` pulls `pradosoft/prado` (`dev-master`) directly from git along with the dev tools.

```sh
git clone https://github.com/pradosoft/prado-rpc.git
cd prado-rpc
composer install
```

The `asset-packagist.org` repository declared in `composer.json` supplies the framework's bower asset dependencies. Because PRADO 4.4 is not yet on Packagist, the framework is required as `dev-master` under `"minimum-stability": "dev"` (with `"prefer-stable": true`, so every other dependency still resolves to a stable release). Once 4.4 ships, change the constraint to `^4.4` and drop the VCS repository and the stability flags.

Verify the install with the full check chain:

```sh
composer unittest                                    # unit tests
vendor/bin/php-cs-fixer fix --dry-run src/           # code style
vendor/bin/phpstan analyse src/ --memory-limit=512M  # static analysis
```

## Server: exposing an API

Declare the service and its API providers, then write a provider that returns a method registry. See the [server guide](docs/server.md) for configuration forms, fault handling, and custom servers.

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

Request flow: `TRpcService → TRpcServer → TRpcProtocol → TRpcApiProvider`. The protocol is selected by the request `Content-Type`: `application/json` dispatches to `TJsonRpcProtocol` (JSON-RPC 1.0 and 2.0), `text/xml` to `TXmlRpcProtocol` (XML-RPC).

## Client: calling an RPC server

The factory selects the protocol; an unknown method call becomes an RPC request through `__call`. See the [client guide](docs/client.md) for versions, notifications, transport, and error handling.

```php
use Prado\IO\Rpc\TRpcClient;

$client = TRpcClient::create('json', 'https://host/rpc');

$customer = $client->getCustomer(42);          // request → decoded result
$orders   = $client->listOrders($customer['id']);
```

Transport failures throw `TRpcClientRequestException`; server faults and malformed responses throw `TRpcClientResponseException`. The HTTP transport is replaceable through `setDownloader()` with any `Prado\IO\HttpClient\THttpClient`, including the caching wrapper.

## XML-RPC on PHP 8

PHP's bundled `xmlrpc` extension was removed from the core in PHP 8.0 and is unavailable on most PHP 8 builds. This package ships `Prado\IO\Rpc\TXmlRpcCodec`, a pure-PHP encoder/decoder built on `ext-dom` and `ext-simplexml`, so XML-RPC works on stock PHP 8.1+ with no PECL extension. See the [codec guide](docs/xml-rpc-codec.md).

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

The package declares its integration points in `composer.json` under `extra.prado`, which PRADO's `TApplicationConfiguration` reads from every installed extension:

```json
"extra": {
	"prado": {
		"error-messages": "config/messages.txt",
		"class-map": ["config/classes.json"]
	}
}
```

- `config/classes.json` maps the short Prado class names (`TRpcService`, `TXmlRpcClient`, …) to their fully-qualified names, so application configurations may reference `class="TRpcService"` as they did when the classes shipped with the framework. Classes are also autoloadable directly by their PSR-4 FQN without the map.
- `config/messages.txt` holds the `rpc*` exception message codes, registered system-wide via `TException::addMessageFile()`.

Both load for any application that installs the package; no bootstrap module is required.

## History

These classes originated in the PRADO framework (`Prado\Web\Services` RPC service since PRADO 3.2) and were extracted into this extension for PRADO 4.4. The namespaces are unchanged, so existing service configurations and client code continue to work after adding the package. See the [migration guide](docs/migration.md).
