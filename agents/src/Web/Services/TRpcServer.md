# TRpcServer

### Directories
[prado-rpc](../../../INDEX.md) / [src/Web/Services](./INDEX.md) / **`TRpcServer`**

## Class Info
**Location:** `src/Web/Services/TRpcServer.php`
**Namespace:** `Prado\Web\Services`
**Extends:** `Prado\TModule`

## Overview
Intermediary between `TRpcService` and `TRpcApiProvider`. Reads the raw request body, delegates to the protocol handler, and returns the encoded response. The base class suits most needs; subclass it for logging, auditing, or request/response transformation.

## Core Properties

| Member | Description |
|---|---|
| `$handler` | `TRpcProtocol` protocol handler, set in the constructor. |

## Core Methods

| Method | Description |
|---|---|
| `__construct(TRpcProtocol $protocolHandler)` | Stores the protocol handler. |
| `addRpcMethod($name, $details)` | Registers a method with the protocol handler (`$handler->addMethod`). |
| `getPayload()` | Returns the raw request body from `php://input`. |
| `processRequest()` | Calls `$handler->callMethod(getPayload())`; converts a stray `TRpcException` to `createErrorResponse()`. Returns encoded response or empty string for notifications. |

## Subclassing

Reference a custom server with the `server` attribute on `<rpcapi>`:

```xml
<rpcapi id="customers" class="Application.Api.CustomersApi"
        server="Application.Api.AuditingRpcServer" />
```

```php
class AuditingRpcServer extends TRpcServer
{
    public function processRequest()
    {
        $r = parent::processRequest();
        // log, audit, transform …
        return $r;
    }
}
```

## Gotchas

- `getPayload()` reads `php://input`; override it (or feed the stream) when unit-testing `processRequest()`.
- `processRequest()` is the safety net for a `TRpcException` that escapes the protocol; protocols normally encode their own faults.

## See Also
- [`TRpcService`](./TRpcService.md) · [`TRpcProtocol`](./TRpcProtocol.md) · [`TRpcApiProvider`](./TRpcApiProvider.md)
