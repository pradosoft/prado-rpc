# TJsonRpcProtocol

### Directories
[prado-rpc](../../../INDEX.md) / [src/Web/Services](./INDEX.md) / **`TJsonRpcProtocol`**

## Class Info
**Location:** `src/Web/Services/TJsonRpcProtocol.php`
**Namespace:** `Prado\Web\Services`
**Extends:** `TRpcProtocol`

## Overview
Implements JSON-RPC 1.0 and 2.0 for `TRpcService`. The version is detected per request: a `"jsonrpc"` field means 2.0; its absence means 1.0. The response format matches the detected version.

## Core Properties

| Member | Description |
|---|---|
| `$_id` | request id of the current call; `null` for a notification. |
| `$_requestVersion` | `'1.0'` or `'2.0'`, detected per request (default `'2.0'`). |

## Core Methods

| Method | Description |
|---|---|
| `callMethod($payload)` | Decodes, detects version, resolves id, dispatches, encodes the result; notifications return an empty string. |
| `createErrorResponse(TRpcException $e)` | Version-appropriate error envelope. |
| `callApiMethod($name, $params)` | Override adding error code -32601 for an unknown method. |
| `encode($data)` / `decode($data)` | `TJavaScript::jsonEncode` / `jsonDecode`. |
| `createResponseHeaders($response)` | `application/json`, UTF-8. |

## Protocol details

- **Version detection:** `"jsonrpc"` present → 2.0; absent → 1.0; a version > 2.0 → fault -32600.
- **Notifications:** no `id` (2.0) or `"id": null` (1.0) → empty-string response.
- **Reserved error codes:** parse error -32700, invalid request / missing method -32600, method not found -32601, internal error -32603.
- **Success envelopes:**
  - 1.0 → `{"result": …, "error": null, "id": …}`
  - 2.0 → `{"jsonrpc": "2.0", "id": …, "result": …}`
- **Error envelopes:**
  - 1.0 → `{"result": null, "error": "message", "id": …}`
  - 2.0 → `{"jsonrpc": "2.0", "id": …, "error": {"code": …, "message": …, "data": null}}`

## Gotchas

- `THttpException` thrown by a handler is re-thrown (not converted to a fault) so the framework's HTTP handling applies.
- A fault raised before the id is resolved is sent as an error; once the call is known to be a notification, faults yield an empty string.

## See Also
- [`TRpcProtocol`](./TRpcProtocol.md) · client: [`TJsonRpcClient`](../../IO/Rpc/TJsonRpcClient.md)
