# TRpcException

### Directories
[prado-rpc](../../../INDEX.md) / [src/Web/Services](./INDEX.md) / **`TRpcException`**

## Class Info
**Location:** `src/Web/Services/TRpcException.php`
**Namespace:** `Prado\Web\Services`
**Extends:** `Prado\Exceptions\TException`

## Overview
Represents an RPC fault — an error caused by the client's request, such as an unknown method, invalid parameters, or a protocol violation. Carries a protocol-level fault code.

## Core Methods

| Method | Description |
|---|---|
| `__construct($message, $errorCode = -1)` | Sets the human-readable message and the protocol fault code. |
| `getErrorCode()` / `setErrorCode()` | The protocol fault code (from `TException`). |

## Fault codes

JSON-RPC 2.0 reserves -32768 to -32000 for predefined errors:

| Code | Meaning |
|---|---|
| -32700 | Parse error |
| -32600 | Invalid request |
| -32601 | Method not found |
| -32603 | Internal error |

Application faults should use codes outside the reserved range. XML-RPC uses the code as the `faultCode`.

## Gotchas

- Thrown from a provider handler, it is caught by the protocol and serialized as a fault (JSON `error` object / XML `<fault>`).
- The default code is -1, an application-defined fault.

## See Also
- [`TRpcProtocol`](./TRpcProtocol.md) · [`TRpcApiProvider`](./TRpcApiProvider.md)
