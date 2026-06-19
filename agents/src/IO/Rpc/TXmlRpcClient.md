# TXmlRpcClient

### Directories
[prado-rpc](../../../INDEX.md) / [src/IO/Rpc](./INDEX.md) / **`TXmlRpcClient`**

## Class Info
**Location:** `src/IO/Rpc/TXmlRpcClient.php`
**Namespace:** `Prado\IO\Rpc`
**Extends:** `TRpcClient`

## Overview
RPC client speaking XML-RPC. An unknown method call is encoded with `TXmlRpcCodec`, sent as `text/xml`, and the decoded `<methodResponse>` value is returned.

## Core Methods

| Method | Description |
|---|---|
| `__call($method, $params)` | Encodes and sends the request; decodes the response; raises `TRpcClientResponseException` on a `<fault>` or empty/unparseable body. |
| `encodeRequest($method, $params)` | `TXmlRpcCodec::encodeRequest($method, $params)`. |
| `create($type, $url, $isNotification = false)` | `Prado::createComponent(static::class, …)`. |

## Gotchas

- Fault detection uses `TXmlRpcCodec::isFault()`; a fault raises `TRpcClientResponseException($faultString, $faultCode)`.
- A `false` decode (parse failure or empty body) is reported as "Empty response received".
- Notification calls return `true` without decoding.
- Wrap binary parameters in `TXmlRpcBase64` for a `<base64>` element.

## See Also
- [`TRpcClient`](./TRpcClient.md) · [`TXmlRpcCodec`](./TXmlRpcCodec.md) · [`TXmlRpcBase64`](./TXmlRpcBase64.md) · server: [`TXmlRpcProtocol`](../../Web/Services/TXmlRpcProtocol.md)
