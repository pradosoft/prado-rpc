# Migration guide

The RPC classes shipped inside the PRADO framework (added 2010-03-27, first released in PRADO 3.2.0). PRADO 4.4 extracts them into this package. The class names and namespaces are unchanged, so most applications need only add the dependency.

## Install the package

```sh
composer require pradosoft/prado-rpc
```

PRADO reads the package's `extra.prado` section and registers its class map and error messages system-wide. No bootstrap module and no configuration change are required for existing service definitions to keep working.

## What is unchanged

- **Server classes** keep the namespace `Prado\Web\Services`: `TRpcService`, `TRpcServer`, `TRpcProtocol`, `TJsonRpcProtocol`, `TXmlRpcProtocol`, `TRpcApiProvider`, `TRpcException`.
- **Short names** still resolve. `config/classes.json` maps `TRpcService`, `TXmlRpcClient`, and the rest to their FQNs, so `<service class="TRpcService">` and `Prado::createComponent('TRpcClient')` work as before.
- **Service configuration** is unchanged: the same `<rpcapi>` elements and `apis` arrays apply.

## What changed

### Client classes moved namespace

The client classes moved from `Prado\Util` to `Prado\IO\Rpc`. Update `use` statements that referenced the old namespace.

```php
// before
use Prado\Util\TRpcClient;
use Prado\Util\TJsonRpcClient;
use Prado\Util\TXmlRpcClient;

// after
use Prado\IO\Rpc\TRpcClient;
use Prado\IO\Rpc\TJsonRpcClient;
use Prado\IO\Rpc\TXmlRpcClient;
```

Code that referenced the short class names through Prado's class map (for example `Prado::createComponent('TRpcClient', ...)`) needs no change.

### The transport is now the PRADO HTTP client

`TRpcClient` performs its HTTP POST through `Prado\IO\HttpClient\THttpClient` rather than cURL directly. Replace the transport with `setDownloader()` to set timeouts or enable caching. See the [client guide](client.md#transport).

### XML-RPC no longer needs the `xmlrpc` extension

XML-RPC encoding and decoding run through the pure-PHP `TXmlRpcCodec`. The PECL `xmlrpc` extension (removed from PHP core in 8.0) is no longer required. See the [codec guide](xml-rpc-codec.md).

### `TRpcClientTypesEnumerable` removed

The framework's `Prado\Util\TRpcClientTypesEnumerable` (the enumerable of client type keys) was not carried over. `TRpcClient::create()` validates the type string (`'json'`, `'xml'`) directly and throws a `TApplicationException` for any other value.

### Request and response exceptions

Transport failures throw `Prado\IO\Rpc\TRpcClientRequestException`; server faults and unparseable responses throw `Prado\IO\Rpc\TRpcClientResponseException`. Both live in the new client namespace.

## Verifying the move

After installing the package:

1. Confirm `class="TRpcService"` service definitions still resolve (the class map is registered automatically).
2. Update any `use Prado\Util\…RpcClient` imports to `Prado\IO\Rpc\…`.
3. Run the application's own test suite against the RPC endpoints.
