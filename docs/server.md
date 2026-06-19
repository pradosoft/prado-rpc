# Server guide

`Prado\Web\Services\TRpcService` exposes an API over JSON-RPC and XML-RPC. On each request it reads the `Content-Type`, selects the matching protocol, and dispatches the call to a registered API provider.

Request flow: `TRpcService → TRpcServer → TRpcProtocol → TRpcApiProvider`.

| Content-Type | Protocol |
|---|---|
| `application/json` | `TJsonRpcProtocol` (JSON-RPC 1.0 and 2.0) |
| `text/xml` | `TXmlRpcProtocol` (XML-RPC) |

## Registering the service

Declare the service and one or more `<rpcapi>` providers. Each provider needs an `id` and a `class` (dot-notation or namespace form).

```xml
<service id="rpc" class="Prado\Web\Services\TRpcService">
    <rpcapi id="customers" class="Application.Api.CustomersApi" />
    <rpcapi id="billing"   class="Application.Api.BillingApi" />
</service>
```

PHP configuration form:

```php
'services' => [
    'rpc' => [
        'class' => 'Prado\Web\Services\TRpcService',
        'apis' => [
            'customers' => ['class' => 'Application\Api\CustomersApi'],
            'billing'   => ['class' => 'Application\Api\BillingApi'],
        ],
    ],
],
```

The provider is selected per request by its `id` (the service parameter), so a single service can host several providers.

## Writing an API provider

Subclass `TRpcApiProvider` and return a method registry from `registerMethods()`. Each entry maps a public RPC method name to a PHP callable.

```php
namespace Application\Api;

use Prado\Web\Services\TRpcApiProvider;

class CustomersApi extends TRpcApiProvider
{
    public function registerMethods(): array
    {
        return [
            'getCustomer'   => ['method' => [$this, 'getCustomer']],
            'listCustomers' => ['method' => [$this, 'listCustomers']],
            'ping'          => ['method' => ['Application\Api\Util', 'ping']], // static callable
        ];
    }

    public function getCustomer(int $id): array
    {
        return CustomerDao::find($id) ?? throw new \Prado\Web\Services\TRpcException('Not found', 404);
    }

    public function listCustomers(string $status = 'active'): array
    {
        return CustomerDao::findByStatus($status);
    }
}
```

Handlers receive the client's positional parameters as arguments. Use parameter defaults or a variadic signature (`...$args`) to handle missing or variable arguments safely.

## Reporting faults

Throw `Prado\Web\Services\TRpcException` from a handler to return a protocol fault. The constructor takes a message and an optional fault code.

```php
throw new \Prado\Web\Services\TRpcException('Customer not found', 404);
```

- JSON-RPC 2.0 serializes it as `{"error": {"code": ..., "message": ...}}`.
- JSON-RPC 1.0 serializes the message into the `error` field.
- XML-RPC serializes it as a `<fault>` with `faultCode` and `faultString`.

JSON-RPC reserves the codes -32768 to -32000 for protocol errors (parse error -32700, invalid request -32600, method not found -32601, internal error -32603). Application faults should use codes outside that range.

## Custom server

`TRpcServer` moves data between the service and the provider. Subclass it for logging, auditing, or request/response transformation, then name it on the provider with the `server` attribute.

```php
namespace Application\Api;

use Prado\Web\Services\TRpcServer;

class AuditingRpcServer extends TRpcServer
{
    public function processRequest()
    {
        $start = microtime(true);
        $response = parent::processRequest();
        Prado::log('RPC handled in ' . (microtime(true) - $start) . 's', TLogger::INFO, 'rpc');
        return $response;
    }
}
```

```xml
<rpcapi id="customers" class="Application.Api.CustomersApi"
        server="Application.Api.AuditingRpcServer" />
```

## How a request is handled

1. `TRpcService::run()` resolves the provider id, checks the method is POST, and reads the `Content-Type`.
2. The `Content-Type` selects `TJsonRpcProtocol` or `TXmlRpcProtocol`; an unsupported type returns HTTP 415.
3. The provider is instantiated with a `TRpcServer`, which registers every method from `registerMethods()`.
4. `TRpcServer::processRequest()` reads the request body and hands it to the protocol.
5. The protocol decodes the call, dispatches to the handler, and encodes the result or fault.
6. The service writes the encoded response with the protocol's content type.

For JSON-RPC, the protocol version is detected per request: a `"jsonrpc"` field means 2.0, its absence means 1.0, and the response matches.
