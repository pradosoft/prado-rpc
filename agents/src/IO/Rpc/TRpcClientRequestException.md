# TRpcClientRequestException

### Directories
[prado-rpc](../../../INDEX.md) / [src/IO/Rpc](./INDEX.md) / **`TRpcClientRequestException`**

## Class Info
**Location:** `src/IO/Rpc/TRpcClientRequestException.php`
**Namespace:** `Prado\IO\Rpc`
**Extends:** `Prado\Exceptions\TApplicationException`

## Overview
Thrown when an RPC request fails at the transport level: no server responds at the URL, the connection times out, or the server returns a non-2xx HTTP status. Raised by `TRpcClient::performRequest()`.

## When it is thrown

| Condition | Message form |
|---|---|
| `THttpClientException` from the transport | `Request failed ("<message>")` |
| Non-2xx HTTP status | `Request failed ("HTTP <code>")` |

## Gotchas

- Signals a network/transport problem, distinct from a server-side fault (`TRpcClientResponseException`).
- Catch it to retry, fail over, or report connectivity errors.

## See Also
- [`TRpcClient`](./TRpcClient.md) · [`TRpcClientResponseException`](./TRpcClientResponseException.md)
