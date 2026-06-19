# IO/Rpc/INDEX.md

### Directories
[prado-rpc](../../../INDEX.md) / **`src/IO/Rpc`**

## Purpose

The client side of prado-rpc and the shared XML-RPC codec. Namespace `Prado\IO\Rpc`.

## Call flow

`TRpcClient::create('json'|'xml', $url)` → `__call` encodes the method and arguments → HTTP POST via `THttpClient` → decode the result, or throw.

## Classes

- **[TRpcClient](./TRpcClient.md)** — base client. `create()` factory (`'json'`/`'xml'`); `performRequest()` POSTs via `THttpClient`; notification mode; pluggable transport (`setDownloader()`).
- **[TJsonRpcClient](./TJsonRpcClient.md)** — JSON-RPC 2.0 by default; `setVersion('1.0')` for 1.0. `__call` encodes the request and decodes `result`/`error`.
- **[TXmlRpcClient](./TXmlRpcClient.md)** — XML-RPC. `__call` encodes via `TXmlRpcCodec` and decodes the response or fault.
- **[TXmlRpcCodec](./TXmlRpcCodec.md)** — `final` pure-PHP XML-RPC encoder/decoder. Replaces PHP's `xmlrpc` extension (removed from core in 8.0). Static API.
- **[TXmlRpcBase64](./TXmlRpcBase64.md)** — `final` value object wrapping a binary string for a `<base64>` element.
- **[TRpcClientRequestException](./TRpcClientRequestException.md)** — transport failure (connect error, non-2xx HTTP status).
- **[TRpcClientResponseException](./TRpcClientResponseException.md)** — server fault, or an empty/unparseable response; carries a fault code.

## Gotchas

- `getDownloader()` lazily creates a `THttpClient`; inject a mock or a configured transport with `setDownloader()`.
- Notification mode is sticky: once `setIsNotification(true)`, every call returns `true` until set back to `false`.
- `TXmlRpcCodec` reports XML parse failures as a `false` return, not an exception; `libxml` errors are suppressed during parse.
- A plain string encodes as `<string>`; wrap bytes in `TXmlRpcBase64` for `<base64>`.
- The subclass `create()` overrides use `Prado::createComponent(static::class, …)`, not `new`.

## See Also

- [SUMMARY.md](./SUMMARY.md) · server side: [src/Web/Services](../../Web/Services/INDEX.md) · [codec guide](../../../../docs/xml-rpc-codec.md)
