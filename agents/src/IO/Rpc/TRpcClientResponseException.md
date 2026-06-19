# TRpcClientResponseException

### Directories
[prado-rpc](../../../INDEX.md) / [src/IO/Rpc](./INDEX.md) / **`TRpcClientResponseException`**

## Class Info
**Location:** `src/IO/Rpc/TRpcClientResponseException.php`
**Namespace:** `Prado\IO\Rpc`
**Extends:** `Prado\Exceptions\TApplicationException`

## Overview
Thrown when the RPC server returns a fault or an unparseable response. The server was reached and replied; the reply is an error or could not be decoded. Raised by the client `__call` methods.

## Core Methods

| Method | Description |
|---|---|
| `__construct($errorMessage, $errorCode = null)` | Sets the fault message and an optional protocol fault code. |
| `getErrorCode()` | The server's fault code, when supplied. |

## When it is thrown

| Protocol | Condition |
|---|---|
| JSON-RPC | response carries an `error` (object → `message`/`code`, or a string) |
| JSON-RPC | empty, null, or non-array body ("Empty response received") |
| XML-RPC | `<fault>` response (`faultString` / `faultCode`) |
| XML-RPC | `false` decode (parse failure or empty body) |

## Gotchas

- Distinct from `TRpcClientRequestException`: the server replied, but the reply is a fault or malformed.
- For a server fault, `getMessage()` is the fault string and `getErrorCode()` the fault code.

## See Also
- [`TRpcClient`](./TRpcClient.md) · [`TRpcClientRequestException`](./TRpcClientRequestException.md)
