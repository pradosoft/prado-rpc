# TRpcApiProvider

### Directories
[prado-rpc](../../../INDEX.md) / [src/Web/Services](./INDEX.md) / **`TRpcApiProvider`**

## Class Info
**Location:** `src/Web/Services/TRpcApiProvider.php`
**Namespace:** `Prado\Web\Services`
**Extends:** `Prado\TModule`
**Type:** `abstract`

## Overview
Abstract base for an RPC API. A subclass declares the callable methods it exposes through `registerMethods()`. On construction the provider is given a `TRpcServer` and registers every method with it.

## Core Properties

| Member | Description |
|---|---|
| `$rpcServer` | the `TRpcServer` handling this provider's requests. |

## Core Methods

| Method | Kind | Description |
|---|---|---|
| `registerMethods()` | abstract | Returns `array<string, array>` mapping RPC method names to handler descriptors. |
| `__construct(TRpcServer $rpcServer)` | concrete | Stores the server, then registers each method via `addRpcMethod()`. |
| `processRequest()` | concrete | Delegates to `$rpcServer->processRequest()`. |
| `getRpcServer()` | concrete | Returns the server instance. |

## Writing a provider

```php
class CustomersApi extends TRpcApiProvider
{
    public function registerMethods(): array
    {
        return [
            'getCustomer'   => ['method' => [$this, 'getCustomer']],
            'listCustomers' => ['method' => [$this, 'listCustomers']],
        ];
    }

    public function getCustomer(int $id): array { /* … */ }
    public function listCustomers(string $status = 'active'): array { /* … */ }
}
```

## Gotchas

- Handlers receive the client's positional parameters as arguments; use defaults or `...$args` for missing/variable arity.
- A handler throws `TRpcException` to return a protocol fault.
- The handler descriptor's `method` may be an instance callable, a static callable, or a closure.

## See Also
- [`TRpcService`](./TRpcService.md) · [`TRpcServer`](./TRpcServer.md) · [`TRpcException`](./TRpcException.md)
- [Server guide](../../../../docs/server.md)
