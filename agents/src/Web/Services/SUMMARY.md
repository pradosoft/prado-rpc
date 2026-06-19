# Web/Services/SUMMARY.md

Server side of prado-rpc: a PRADO service exposing an API over JSON-RPC and XML-RPC. Namespace `Prado\Web\Services`. Flow: `TRpcService → TRpcServer → TRpcProtocol → TRpcApiProvider`.

## Classes

- **`TRpcService`** — `TService`; parses `<rpcapi>` config, selects protocol by `Content-Type`, instantiates the provider; `$protocolHandlers` maps mime → protocol FQN.
- **`TRpcServer`** — `TModule`; reads `php://input`, delegates to the protocol, converts a stray `TRpcException` to an error response; subclass via the `server` attribute.
- **`TRpcProtocol`** — abstract; method registry (`addMethod`/`callApiMethod`) plus abstract `callMethod`/`encode`/`decode`/`createErrorResponse`/`createResponseHeaders`.
- **`TJsonRpcProtocol`** — JSON-RPC 1.0/2.0; version from `"jsonrpc"`; codes -32700/-32600/-32601/-32603.
- **`TXmlRpcProtocol`** — XML-RPC via `TXmlRpcCodec`; faults as `<fault>`.
- **`TRpcApiProvider`** — abstract `TModule`; `registerMethods()` returns name → callable map.
- **`TRpcException`** — RPC fault with a protocol fault code; extends `TException`.
