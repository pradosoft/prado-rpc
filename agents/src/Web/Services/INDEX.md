# Web/Services/INDEX.md

### Directories
[prado-rpc](../../../INDEX.md) / **`src/Web/Services`**

## Purpose

The server side of prado-rpc: a PRADO service that exposes an API over JSON-RPC and XML-RPC. Namespace `Prado\Web\Services`.

## Request flow

`TRpcService → TRpcServer → TRpcProtocol → TRpcApiProvider`

1. [TRpcService](./TRpcService.md) resolves the provider id, verifies POST, reads `Content-Type`.
2. The content type selects a protocol: `application/json` → [TJsonRpcProtocol](./TJsonRpcProtocol.md), `text/xml` → [TXmlRpcProtocol](./TXmlRpcProtocol.md). Unsupported → HTTP 415.
3. [TRpcApiProvider](./TRpcApiProvider.md) is instantiated with a [TRpcServer](./TRpcServer.md), registering every method from `registerMethods()`.
4. `TRpcServer::processRequest()` reads the body and hands it to the protocol.
5. The protocol decodes, dispatches to the handler, and encodes the result or fault.

## Classes

- **[TRpcService](./TRpcService.md)** — the `TService`. Parses `<rpcapi>` config (XML or PHP), selects the protocol by `Content-Type`, instantiates the provider. `$protocolHandlers` maps mime types to protocol FQNs.
- **[TRpcServer](./TRpcServer.md)** — `TModule` intermediary. Reads the raw request body; delegates to the protocol; catches a stray `TRpcException` as a safety net. Subclass for logging/auditing.
- **[TRpcProtocol](./TRpcProtocol.md)** — abstract wire format. Holds the method registry; `addMethod()`, `callApiMethod()`; abstracts `callMethod`/`encode`/`decode`/`createErrorResponse`/`createResponseHeaders`.
- **[TJsonRpcProtocol](./TJsonRpcProtocol.md)** — JSON-RPC 1.0 and 2.0. Per-request version detection from `"jsonrpc"`. Reserved error codes -32700/-32600/-32601/-32603.
- **[TXmlRpcProtocol](./TXmlRpcProtocol.md)** — XML-RPC via `TXmlRpcCodec`; faults as `<fault>`.
- **[TRpcApiProvider](./TRpcApiProvider.md)** — abstract `TModule`. Subclasses return a method registry from `registerMethods()`.
- **[TRpcException](./TRpcException.md)** — an RPC fault carrying a protocol fault code; extends `TException`.

## Gotchas

- `$protocolHandlers` holds `TJsonRpcProtocol::class` / `TXmlRpcProtocol::class` (FQN), so dispatch works under pure PSR-4 without the class map.
- `TRpcServer::getPayload()` reads `php://input`; tests that drive `processRequest()` set the input stream or override the method.
- JSON-RPC notifications (no `id` / `id: null`) return an empty string; XML-RPC has no notification concept on the server.
- Error codes live in `config/messages.txt` (`rpcservice_*`), not the framework `messages.txt`.

## See Also

- [SUMMARY.md](./SUMMARY.md) · client side: [src/IO/Rpc](../../IO/Rpc/INDEX.md)
