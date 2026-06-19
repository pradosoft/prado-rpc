# TJsonRpcClient

### Directories
[prado-rpc](../../../INDEX.md) / [src/IO/Rpc](./INDEX.md) / **`TJsonRpcClient`**

## Class Info
**Location:** `src/IO/Rpc/TJsonRpcClient.php`
**Namespace:** `Prado\IO\Rpc`
**Extends:** `TRpcClient`

## Overview
RPC client speaking JSON-RPC 2.0 by default; `setVersion('1.0')` switches to JSON-RPC 1.0. An unknown method call is encoded, sent, and the decoded `result` is returned.

## Core Properties

| Member | Description |
|---|---|
| `$_version` | outgoing protocol version, `'2.0'` (default) or `'1.0'`. |

## Core Methods

| Method | Description |
|---|---|
| `__call($method, $params)` | Encodes and sends the request; returns `result`; raises `TRpcClientResponseException` on an `error` object or empty/unparseable body. |
| `encodeRequest($method, $params)` | Builds the JSON-RPC envelope; 2.0 adds `"jsonrpc"`; notifications omit `id` (2.0) or send `id: null` (1.0). |
| `create($type, $url, $isNotification = false)` | `Prado::createComponent(static::class, …)`. |
| `getVersion()` / `setVersion($v)` | `'1.0'` or `'2.0'`; any other value throws `TInvalidDataValueException`. |

## Request envelope

- 2.0: `{"jsonrpc": "2.0", "method": …, "params": […], "id": N}`
- 1.0: `{"method": …, "params": […], "id": N}`
- Notification (2.0): no `id` key. Notification (1.0): `"id": null`.

The request id is a process-incrementing counter.

## Gotchas

- An `error` object in the response raises `TRpcClientResponseException` with its `message` and `code`.
- A JSON decode failure or a non-array body is reported as "Empty response received".
- Notification calls return `true` without decoding.

## See Also
- [`TRpcClient`](./TRpcClient.md) · server: [`TJsonRpcProtocol`](../../Web/Services/TJsonRpcProtocol.md)
