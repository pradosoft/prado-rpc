# TXmlRpcProtocol

### Directories
[prado-rpc](../../../INDEX.md) / [src/Web/Services](./INDEX.md) / **`TXmlRpcProtocol`**

## Class Info
**Location:** `src/Web/Services/TXmlRpcProtocol.php`
**Namespace:** `Prado\Web\Services`
**Extends:** `TRpcProtocol`

## Overview
Implements the XML-RPC server protocol for `TRpcService`. Uses the pure-PHP `TXmlRpcCodec` in place of PHP's removed `xmlrpc` extension.

## Core Methods

| Method | Description |
|---|---|
| `callMethod($payload)` | Decodes a `<methodCall>`, dispatches to the handler, encodes a `<methodResponse>`; faults become `<fault>` responses. |
| `createErrorResponse(TRpcException $e)` | `TXmlRpcCodec::encodeFaultResponse(code, message)`. |
| `encode($data)` / `decode($data)` | `TXmlRpcCodec::encodeResponse` / `decodeRequest`. |
| `createResponseHeaders($response)` | `text/xml`, UTF-8. |

## Protocol details

- A request that fails to parse → `TRpcException('Invalid XML-RPC request')` → `<fault>`.
- `THttpException` from a handler is re-thrown; any other exception becomes an internal-error fault.
- No notification concept: every request produces a `<methodResponse>` or `<fault>`.

## Gotchas

- All encoding and decoding go through `TXmlRpcCodec`; there is no dependency on the PECL `xmlrpc` extension.
- Fault codes come from `TRpcException::getErrorCode()`.

## See Also
- [`TRpcProtocol`](./TRpcProtocol.md) · [`TXmlRpcCodec`](../../IO/Rpc/TXmlRpcCodec.md) · client: [`TXmlRpcClient`](../../IO/Rpc/TXmlRpcClient.md)
