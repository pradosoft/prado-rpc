# TRpcService

### Directories
[prado-rpc](../../../INDEX.md) / [src/Web/Services](./INDEX.md) / **`TRpcService`**

## Class Info
**Location:** `src/Web/Services/TRpcService.php`
**Namespace:** `Prado\Web\Services`
**Extends:** `Prado\TService`

## Overview
The PRADO service that exposes an API over JSON-RPC and XML-RPC. On each request it resolves the API provider by id, verifies the method is POST, reads `Content-Type`, selects the protocol, instantiates the provider, and writes the encoded response.

## Core Properties

| Member | Description |
|---|---|
| `$protocolHandlers` | `array` mime → protocol class. Holds FQNs: `TMediaType::JSON => TJsonRpcProtocol::class`, `TMediaType::XML_TEXT => TXmlRpcProtocol::class`. |
| `$apiProviders` | `array` of configured provider descriptors, keyed by id. |
| `BASE_API_PROVIDER` | const; every provider must extend `TRpcApiProvider`. |
| `BASE_RPC_SERVER` | const; default server class `TRpcServer`. |

## Core Methods

| Method | Description |
|---|---|
| `init($config)` | Parses `<rpcapi>` / `apis` config via `loadConfig()`. |
| `loadConfig($config)` | Reads provider descriptors from XML element or PHP array. |
| `run()` | Resolves provider id, checks POST, reads `Content-Type`, selects protocol, dispatches, writes response. |
| `createApiProvider(TRpcProtocol $handler, $providerId)` | Resolves provider + server class via `Prado::usingClass()`, instantiates the provider with its server, applies extra properties. |

## Configuration

```xml
<service id="rpc" class="Prado\Web\Services\TRpcService">
    <rpcapi id="customers" class="Application.Api.CustomersApi" />
</service>
```

```php
'services' => ['rpc' => [
    'class' => 'Prado\Web\Services\TRpcService',
    'apis'  => ['customers' => ['class' => 'Application\Api\CustomersApi']],
]],
```

Per-provider `server="…"` overrides the default `TRpcServer`.

## Dispatch rules

- Non-POST request → `THttpException` 405.
- Missing/unknown provider id → 400 / 404.
- Missing `Content-Type` → 415; unsupported type → 415.
- `application/json` → `TJsonRpcProtocol`; `text/xml` → `TXmlRpcProtocol`.

## Gotchas

- `$protocolHandlers` is FQN-keyed, so protocol instantiation works under pure PSR-4; the class map is not required at runtime.
- Provider/server class names come from app config and resolve through `Prado::usingClass()` (dot, short, or FQN).
- Error codes are `rpcservice_*` in `config/messages.txt`.

## See Also
- [`TRpcServer`](./TRpcServer.md) · [`TRpcApiProvider`](./TRpcApiProvider.md) · [`TRpcProtocol`](./TRpcProtocol.md)
- [Server guide](../../../../docs/server.md)
