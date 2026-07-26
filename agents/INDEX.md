# prado-rpc — Working Knowledge

### Directories
**`prado-rpc`** / [src/Web/Services](./src/Web/Services/INDEX.md) · [src/IO/Rpc](./src/IO/Rpc/INDEX.md)

## Purpose

JSON-RPC and XML-RPC for the PRADO framework (4.4+), as a server-side web service and a client library. Extracted from the framework (`Prado\Web\Services` RPC, since PRADO 3.2) into a standalone composer extension for PRADO 4.4. Version **4.4.0** (tracks the PRADO `main` dev branch version).

## Layout

| Path | Namespace | Side |
|---|---|---|
| [`src/Web/Services/`](./src/Web/Services/INDEX.md) | `Prado\Web\Services` | Server: service, server, protocols, provider, exception |
| [`src/IO/Rpc/`](./src/IO/Rpc/INDEX.md) | `Prado\IO\Rpc` | Client: clients, codec, base64 wrapper, client exceptions |

## How it integrates

- **PSR-4** autoloads every class by FQN (`Prado\` → `src`).
- **`composer.json` → `extra.prado`** declares two integration points PRADO's `TApplicationConfiguration` reads from every installed extension:
  - `class-map` → `config/classes.json` — short Prado name → FQN, so app configs may use `class="TRpcService"`.
  - `error-messages` → `config/messages.txt` — the `rpc*` exception codes.
- **No bootstrap module.** Both files load system-wide for any consuming application.

## Request flow (server)

`TRpcService → TRpcServer → TRpcProtocol → TRpcApiProvider`

The service reads `Content-Type`, picks `TJsonRpcProtocol` (`application/json`) or `TXmlRpcProtocol` (`text/xml`), instantiates the provider, and writes the encoded result.

## Call flow (client)

`TRpcClient::create('json'|'xml', $url)` → `__call` encodes the method → HTTP POST via `THttpClient` → decode result, or throw `TRpcClientRequestException` (transport) / `TRpcClientResponseException` (fault).

## Key facts

- XML-RPC uses the pure-PHP [`TXmlRpcCodec`](./src/IO/Rpc/TXmlRpcCodec.md); PHP's `xmlrpc` extension was removed from core in PHP 8.0.
- `TRpcService::$protocolHandlers` holds FQNs (`TJsonRpcProtocol::class`), so the service dispatches under pure PSR-4; the class map is for app-facing short names only.
- Internal component creation uses `Prado::createComponent()`, not `new` (exceptions excepted).

## See Also

- [Documentation guides](../docs/README.md)
- [SUMMARY.md](./SUMMARY.md) — terse one-line-per-class index
