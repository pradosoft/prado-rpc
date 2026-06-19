# TRpcProtocol

### Directories
[prado-rpc](../../../INDEX.md) / [src/Web/Services](./INDEX.md) / **`TRpcProtocol`**

## Class Info
**Location:** `src/Web/Services/TRpcProtocol.php`
**Namespace:** `Prado\Web\Services`
**Type:** `abstract`

## Overview
Base class for RPC protocol implementations. Holds the method registry and the dispatch logic shared by `TJsonRpcProtocol` (JSON-RPC) and `TXmlRpcProtocol` (XML-RPC).

## Core Properties

| Member | Description |
|---|---|
| `$rpcMethods` | `array<string, array>` method name → handler descriptor (`['method' => callable]`). |

## Core Methods

| Method | Kind | Description |
|---|---|---|
| `addMethod($name, $details)` | concrete | Registers a method name and its handler descriptor. |
| `callApiMethod($name, $params)` | concrete | Dispatches to the registered handler; throws `TRpcException` "Method not found" when unknown; normalizes params to an array. |
| `callMethod($payload)` | abstract | Processes the raw request body, returns the encoded response. |
| `createErrorResponse(TRpcException $e)` | abstract | Encodes a fault as a protocol response. |
| `createResponseHeaders($response)` | abstract | Sets content-type and charset on the `THttpResponse`. |
| `encode($data)` | abstract | Encodes a PHP value as a response body. |
| `decode($data)` | abstract | Decodes a raw request body. |

## Gotchas

- `callApiMethod()` is overridden in `TJsonRpcProtocol` to attach JSON-RPC error code -32601 to the "not found" fault.
- Handler descriptors must carry a `method` key holding a PHP callable (`[$this, 'm']`, `['Class', 'm']`, or a closure).

## See Also
- [`TJsonRpcProtocol`](./TJsonRpcProtocol.md) · [`TXmlRpcProtocol`](./TXmlRpcProtocol.md) · [`TRpcException`](./TRpcException.md)
