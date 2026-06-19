# TXmlRpcCodec

### Directories
[prado-rpc](../../../INDEX.md) / [src/IO/Rpc](./INDEX.md) / **`TXmlRpcCodec`**

## Class Info
**Location:** `src/IO/Rpc/TXmlRpcCodec.php`
**Namespace:** `Prado\IO\Rpc`
**Type:** `final` (static methods)
**Requires:** `ext-dom`, `ext-simplexml`

## Overview
Pure-PHP encoder and decoder for the XML-RPC wire format. PHP's `xmlrpc` extension was removed from the core in PHP 8.0 and moved to PECL (unmaintained, usually absent). This codec replaces `xmlrpc_encode_request()` / `xmlrpc_decode()` so XML-RPC runs on stock PHP 8.1+. Used by `TXmlRpcClient` and `TXmlRpcProtocol`.

## Core Methods

| Method | Description |
|---|---|
| `encodeRequest(string $method, array $params): string` | Build a `<methodCall>` document. |
| `decodeRequest(string $xml): array{method,params}\|false` | Parse a `<methodCall>`; `false` on parse failure. |
| `encodeResponse(mixed $value): string` | Build a `<methodResponse>` with a return value. |
| `encodeFaultResponse(int $code, string $message): string` | Build a `<fault>` response. |
| `decodeResponse(string $xml): mixed` | Parse a `<methodResponse>`; faults decode to a struct; `false` on parse failure. |
| `isFault(mixed $value): bool` | Test for a fault (`faultCode` + `faultString`). |

## Type mapping

**Encode (PHP → XML-RPC):** `null`→`<nil/>`, `bool`→`<boolean>`, `int`→`<int>`, `float`→`<double>`, `string`→`<string>`, `DateTimeInterface`→`<dateTime.iso8601>`, `TXmlRpcBase64`→`<base64>`, list→`<array>`, assoc/object→`<struct>`.

**Decode (XML-RPC → PHP):** `<string>`/bare→`string`, `<int>`/`<i4>`/`<i8>`→`int`, `<double>`→`float`, `<boolean>`→`bool`, `<nil/>`→`null`, `<dateTime.iso8601>`→`DateTimeImmutable`, `<base64>`→decoded `string`, `<array>`→indexed array, `<struct>`→assoc array.

## Gotchas

- Parse failures return `false`, not exceptions; `libxml` internal errors are suppressed during parse.
- A plain string encodes as `<string>`; wrap bytes in `TXmlRpcBase64` for `<base64>`.
- Dates encode as `Ymd\TH:i:s`; decode tries that format first, then the `DateTimeImmutable` constructor, then returns the raw string.
- Strings are escaped with `htmlspecialchars(ENT_XML1, 'UTF-8')`.

## See Also
- [`TXmlRpcClient`](./TXmlRpcClient.md) · [`TXmlRpcBase64`](./TXmlRpcBase64.md) · [`TXmlRpcProtocol`](../../Web/Services/TXmlRpcProtocol.md)
- [Codec guide](../../../../docs/xml-rpc-codec.md)
