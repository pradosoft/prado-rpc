# prado-rpc Documentation

JSON-RPC and XML-RPC for the [PRADO PHP Framework](https://github.com/pradosoft/prado) (4.4+), as a server-side web service and a client library.

## Guides

| Guide | Contents |
|---|---|
| [Server](server.md) | Exposing an API with `TRpcService` and `TRpcApiProvider`; configuration, routing, custom servers |
| [Client](client.md) | Calling a remote RPC server with `TRpcClient`; factory, notifications, transport, error handling |
| [XML-RPC codec](xml-rpc-codec.md) | The pure-PHP `TXmlRpcCodec` that replaces PHP's removed `xmlrpc` extension; type mapping, base64, dates |
| [Migration](migration.md) | Moving from the framework-bundled RPC classes to this package |

## At a glance

- **Server:** a `TRpcService` reads the request `Content-Type`, selects JSON-RPC or XML-RPC, and dispatches to a registered `TRpcApiProvider`.
- **Client:** `TRpcClient::create('json'|'xml', $url)` returns a client whose unknown method calls become RPC requests.
- **Codec:** `TXmlRpcCodec` encodes and decodes XML-RPC in pure PHP, so XML-RPC runs on stock PHP 8.1+ with no PECL extension.

## Namespaces

| Namespace | Location | Side |
|---|---|---|
| `Prado\Web\Services` | `src/Web/Services/` | Server |
| `Prado\IO\Rpc` | `src/IO/Rpc/` | Client and codec |

The class working-memory notes for contributors live under [`agents/`](../agents/INDEX.md).
