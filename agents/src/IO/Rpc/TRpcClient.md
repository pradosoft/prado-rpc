# TRpcClient

### Directories
[prado-rpc](../../../INDEX.md) / [src/IO/Rpc](./INDEX.md) / **`TRpcClient`**

## Class Info
**Location:** `src/IO/Rpc/TRpcClient.php`
**Namespace:** `Prado\IO\Rpc`
**Extends:** `Prado\TApplicationComponent`

## Overview
Base class for consuming a remote RPC server. Encodes a method call, sends it as an HTTP POST through the PRADO HTTP client, and decodes the response. Subclasses `TJsonRpcClient` and `TXmlRpcClient` implement protocol-specific encode/decode in `__call`.

## Core Properties

| Member | Description |
|---|---|
| `$_serverUrl` | RPC server URL. |
| `$_isNotification` | when true, calls ignore the response and return `true` (sticky until reset). |
| `$_downloader` | `THttpClient` transport, lazily created. |

## Core Methods

| Method | Description |
|---|---|
| `create($type, $url, $isNotification = false)` | static factory; `'json'` → `TJsonRpcClient`, `'xml'` → `TXmlRpcClient`; unknown type throws `TApplicationException`. Uses `Prado::createComponent`. |
| `performRequest($url, $payload, $mimeType)` | POSTs via `getDownloader()`; throws `TRpcClientRequestException` on transport failure or non-2xx. Returns the raw body. |
| `getIsNotification()` / `setIsNotification($b)` | notification mode. |
| `getServerUrl()` / `setServerUrl($v)` | server URL. |
| `getDownloader()` / `setDownloader($v)` | the `THttpClient` transport; lazily `THttpClient::create()` on first read. |

## Usage

```php
$client = TRpcClient::create('json', 'https://api.example.com/rpc');
$result = $client->add(2, 3);              // __call → request → decoded result
```

## Gotchas

- `__call` lives in the subclasses, not here; the base provides transport, factory, and accessors.
- Notification mode is sticky.
- Replace the transport with `setDownloader()` to set timeouts or wrap in `TCachedHttpClient`.
- `create()` is `: self` and resolves the handler from FQN class constants; subclasses override `create()` as `: static` via `Prado::createComponent(static::class, …)`.

## See Also
- [`TJsonRpcClient`](./TJsonRpcClient.md) · [`TXmlRpcClient`](./TXmlRpcClient.md)
- [`TRpcClientRequestException`](./TRpcClientRequestException.md) · [`TRpcClientResponseException`](./TRpcClientResponseException.md)
- [Client guide](../../../../docs/client.md)
