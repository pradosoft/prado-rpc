# IO/Rpc/SUMMARY.md

Client side of prado-rpc plus the shared XML-RPC codec. Namespace `Prado\IO\Rpc`. Flow: `create('json'|'xml', $url)` → `__call` encodes → POST via `THttpClient` → decode or throw.

## Classes

- **`TRpcClient`** — base client; `create()` factory, `performRequest()` POST via `THttpClient`, notification mode, `setDownloader()` transport.
- **`TJsonRpcClient`** — JSON-RPC 2.0 default; `setVersion('1.0')`; `__call` decodes `result`/`error`.
- **`TXmlRpcClient`** — XML-RPC via `TXmlRpcCodec`; `__call` decodes response or fault.
- **`TXmlRpcCodec`** — `final` static pure-PHP XML-RPC encoder/decoder; replaces PHP's removed `xmlrpc` extension; parse failure → `false`.
- **`TXmlRpcBase64`** — `final` value object wrapping binary for a `<base64>` element.
- **`TRpcClientRequestException`** — transport failure (connect error, non-2xx).
- **`TRpcClientResponseException`** — server fault or empty/unparseable response; carries a fault code.
