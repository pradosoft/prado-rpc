# Client guide

`Prado\IO\Rpc\TRpcClient` consumes a remote RPC server. It encodes a method call, sends it as an HTTP POST through the PRADO HTTP client, and decodes the response. Two protocols are available: `TJsonRpcClient` (JSON-RPC 1.0 and 2.0) and `TXmlRpcClient` (XML-RPC).

## Creating a client

The factory selects the protocol from a type key:

```php
use Prado\IO\Rpc\TRpcClient;

$json = TRpcClient::create('json', 'https://api.example.com/rpc');   // TJsonRpcClient
$xml  = TRpcClient::create('xml',  'https://api.example.com/rpc');   // TXmlRpcClient
```

The type key is case-insensitive. An unknown key throws `Prado\Exceptions\TApplicationException`.

A concrete subclass may also be constructed directly:

```php
use Prado\IO\Rpc\TJsonRpcClient;

$client = new TJsonRpcClient('https://api.example.com/rpc');
```

## Calling remote methods

Any method that the client class does not define is treated as a remote call through `__call`. The method name becomes the RPC method, the arguments become the positional parameters, and the return value is the decoded result.

```php
$sum    = $client->add(2, 3);            // params [2, 3]
$user   = $client->getUser(42);          // result decoded to a PHP value
$items  = $client->listItems('open');    // arrays decode to PHP arrays
```

## JSON-RPC version

`TJsonRpcClient` sends JSON-RPC 2.0 by default. Switch to 1.0 with `setVersion()`:

```php
$client = new TJsonRpcClient('https://api.example.com/rpc');
$client->setVersion('1.0');     // '1.0' or '2.0'; any other value throws
```

The version controls the request envelope: 2.0 includes the `"jsonrpc"` field; 1.0 omits it. Notifications differ between the versions (see below).

## Notifications

A notification is a call whose response is ignored. Enable it with `setIsNotification(true)`; the next and every following call returns `true` without decoding a response, until the flag is set back to `false`.

```php
$client->setIsNotification(true);
$client->logEvent('user.login', $userId);   // returns true, response discarded
$client->logEvent('user.view', $pageId);     // still a notification
$client->setIsNotification(false);           // back to normal calls
```

- JSON-RPC 2.0: notifications omit the `id` key entirely.
- JSON-RPC 1.0: notifications send `"id": null`.
- XML-RPC: the request is sent and the response is not decoded.

## Transport

The transport is a `Prado\IO\HttpClient\THttpClient`, created on first use. Replace it to set timeouts, add headers, or enable caching.

```php
use Prado\IO\HttpClient\THttpClient;

$transport = THttpClient::create();
$transport->setTimeout(5);
$client->setDownloader($transport);
```

Response caching wraps the transport in `TCachedHttpClient`:

```php
use Prado\IO\HttpClient\TCachedHttpClient;

$client->setDownloader(new TCachedHttpClient(THttpClient::create(), $cache, 300));
```

Note that RPC calls are HTTP POST requests; a caching transport caches per the rules of its implementation, so apply it only to idempotent calls.

## Error handling

| Condition | Exception |
|---|---|
| Connection failure, timeout, or non-2xx HTTP status | `TRpcClientRequestException` |
| Server fault, or an empty or unparseable response | `TRpcClientResponseException` |

```php
use Prado\IO\Rpc\TRpcClientRequestException;
use Prado\IO\Rpc\TRpcClientResponseException;

try {
    $user = $client->getUser(42);
} catch (TRpcClientResponseException $e) {
    // Server-side fault. getMessage() is the fault string; getErrorCode() the fault code.
    log_warning("RPC fault {$e->getErrorCode()}: {$e->getMessage()}");
} catch (TRpcClientRequestException $e) {
    // Transport failure. The server was unreachable or returned a non-2xx status.
    log_error("RPC transport: {$e->getMessage()}");
}
```

For JSON-RPC, a response with an `error` object raises `TRpcClientResponseException` carrying the error `message` and `code`. For XML-RPC, a `<fault>` response raises the same exception carrying `faultString` and `faultCode`.

## Sending binary data over XML-RPC

Wrap a binary string in `TXmlRpcBase64` so the codec emits a `<base64>` element instead of a `<string>`:

```php
use Prado\IO\Rpc\TXmlRpcBase64;

$xml->storeFile('report.pdf', new TXmlRpcBase64(file_get_contents('/path/report.pdf')));
```

## Full example

```php
use Prado\IO\HttpClient\THttpClient;
use Prado\IO\Rpc\TRpcClient;
use Prado\IO\Rpc\TRpcClientRequestException;
use Prado\IO\Rpc\TRpcClientResponseException;

$client = TRpcClient::create('json', 'https://api.example.com/rpc');

$transport = THttpClient::create();
$transport->setTimeout(10);
$client->setDownloader($transport);

try {
    $customer = $client->getCustomer(42);
    $orders   = $client->listOrders($customer['id'], 'open');

    $client->setIsNotification(true);
    $client->recordAccess($customer['id']);   // fire-and-forget
    $client->setIsNotification(false);
} catch (TRpcClientResponseException $e) {
    // application-level fault returned by the server
} catch (TRpcClientRequestException $e) {
    // network/transport failure
}
```
