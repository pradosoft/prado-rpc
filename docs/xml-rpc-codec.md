# XML-RPC codec

`Prado\IO\Rpc\TXmlRpcCodec` is a pure-PHP encoder and decoder for the XML-RPC wire format, built on `ext-dom` and `ext-simplexml`.

## Why a custom codec

PHP shipped an `xmlrpc` extension for years, exposing `xmlrpc_encode_request()` and `xmlrpc_decode()`. That extension was **removed from the PHP core in PHP 8.0** and moved to PECL, where it is unmaintained and unavailable on most PHP 8 builds. On a stock PHP 8.1+ installation those functions do not exist.

`TXmlRpcCodec` replaces them with a dependency-free implementation. XML-RPC therefore works on stock PHP 8.1+ with no PECL extension. Both the client (`TXmlRpcClient`) and the server protocol (`TXmlRpcProtocol`) use the codec for all XML-RPC encoding and decoding.

## Type mapping

### PHP → XML-RPC (encoding)

| PHP type | XML-RPC element |
|---|---|
| `null` | `<nil/>` |
| `bool` | `<boolean>` |
| `int` | `<int>` |
| `float` | `<double>` |
| `string` | `<string>` |
| `DateTimeInterface` | `<dateTime.iso8601>` |
| `TXmlRpcBase64` | `<base64>` |
| sequential `array` (list) | `<array>` |
| associative `array` or `object` | `<struct>` |

### XML-RPC → PHP (decoding)

| XML-RPC element | PHP type |
|---|---|
| `<string>` or a bare value | `string` |
| `<int>` / `<i4>` / `<i8>` | `int` |
| `<double>` | `float` |
| `<boolean>` | `bool` |
| `<nil/>` | `null` |
| `<dateTime.iso8601>` | `DateTimeImmutable` |
| `<base64>` | `string` (decoded binary) |
| `<array>` | indexed `array` |
| `<struct>` | associative `array` |

## Binary data

A plain PHP string encodes as `<string>`. To send raw bytes as `<base64>`, wrap them in `TXmlRpcBase64`:

```php
use Prado\IO\Rpc\TXmlRpcBase64;

$client->upload(new TXmlRpcBase64(file_get_contents('/path/file.bin')));
```

On decode, a `<base64>` element returns the decoded binary string.

## Dates

A `DateTimeInterface` encodes to `<dateTime.iso8601>` in `Ymd\TH:i:s` form. On decode, the canonical format is parsed first; extended ISO 8601 strings fall back to the `DateTimeImmutable` constructor, and an unparseable value is returned as the original string.

## API

The codec is a `final` class of static methods.

| Method | Purpose |
|---|---|
| `encodeRequest(string $method, array $params): string` | Build a `<methodCall>` document |
| `decodeRequest(string $xml): array{method,params}\|false` | Parse a `<methodCall>`; `false` on parse failure |
| `encodeResponse(mixed $value): string` | Build a `<methodResponse>` with a return value |
| `encodeFaultResponse(int $code, string $message): string` | Build a `<fault>` response |
| `decodeResponse(string $xml): mixed` | Parse a `<methodResponse>`; faults decode to a struct; `false` on parse failure |
| `isFault(mixed $value): bool` | Test whether a decoded value is a fault (`faultCode` + `faultString`) |

## Direct use

```php
use Prado\IO\Rpc\TXmlRpcCodec;

$request = TXmlRpcCodec::encodeRequest('add', [2, 3]);
// <?xml ...><methodCall><methodName>add</methodName>...</methodCall>

$decoded = TXmlRpcCodec::decodeResponse($responseXml);
if (TXmlRpcCodec::isFault($decoded)) {
    // $decoded['faultCode'], $decoded['faultString']
} else {
    // $decoded is the return value
}
```

XML parse failures are reported through the return value (`false`), not exceptions; `libxml` internal errors are suppressed during parsing so a malformed document does not emit warnings.
