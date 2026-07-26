# prado-rpc — Minimum Viable Context

Composer extension `pradosoft/prado-rpc` · version `4.4.0` (tracks the PRADO `main` dev branch version) · PHP 8.1+ · PSR-4 `Prado\` → `src`. Adds JSON-RPC (1.0/2.0) and XML-RPC to PRADO 4.4 as a server service and a client library. Extracted from the framework; namespaces unchanged.

**Integration** is declarative via `composer.json` `extra.prado`: `class-map` → `config/classes.json` (short name → FQN) and `error-messages` → `config/messages.txt` (`rpc*` codes). No bootstrap module.

## Server — `Prado\Web\Services` (`src/Web/Services/`)

- **`TRpcService`** — the PRADO `TService`. Parses `<rpcapi>` config, selects protocol by `Content-Type`, instantiates the provider, writes the response. Protocol map holds FQNs.
- **`TRpcServer`** — `TModule` between service and provider; reads the request body, delegates to the protocol. Subclass for logging/auditing (`server` attribute).
- **`TRpcProtocol`** — abstract wire format: `addMethod()`, `callApiMethod()`, and abstract `callMethod`/`encode`/`decode`/`createErrorResponse`/`createResponseHeaders`.
- **`TJsonRpcProtocol`** — JSON-RPC 1.0 and 2.0; version auto-detected from the `"jsonrpc"` field per request.
- **`TXmlRpcProtocol`** — XML-RPC via `TXmlRpcCodec`.
- **`TRpcApiProvider`** — abstract `TModule`; subclasses return a method registry from `registerMethods()`.
- **`TRpcException`** — an RPC fault carrying a protocol fault code; extends `TException`.

## Client — `Prado\IO\Rpc` (`src/IO/Rpc/`)

- **`TRpcClient`** — base client. `create('json'|'xml', $url)` factory; `__call` in subclasses turns method calls into requests; HTTP POST via `THttpClient`; notification mode.
- **`TJsonRpcClient`** — JSON-RPC 2.0 default, `setVersion('1.0')` for 1.0.
- **`TXmlRpcClient`** — XML-RPC via `TXmlRpcCodec`.
- **`TXmlRpcCodec`** — `final` pure-PHP XML-RPC encoder/decoder; replaces PHP's removed `xmlrpc` extension.
- **`TXmlRpcBase64`** — `final` value object wrapping binary for a `<base64>` element.
- **`TRpcClientRequestException`** — transport failure (connect error, non-2xx).
- **`TRpcClientResponseException`** — server fault or empty/unparseable response.
