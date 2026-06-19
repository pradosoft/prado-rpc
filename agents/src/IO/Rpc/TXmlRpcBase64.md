# TXmlRpcBase64

### Directories
[prado-rpc](../../../INDEX.md) / [src/IO/Rpc](./INDEX.md) / **`TXmlRpcBase64`**

## Class Info
**Location:** `src/IO/Rpc/TXmlRpcBase64.php`
**Namespace:** `Prado\IO\Rpc`
**Type:** `final`

## Overview
Value object wrapping a raw binary string for transmission as an XML-RPC `<base64>` element. Without it a string encodes as `<string>`; wrapping signals the codec to base64-encode the bytes.

## Members

| Member | Description |
|---|---|
| `__construct(string $data)` | constructor promotes `public readonly string $data`. |
| `$data` | the raw binary payload, base64-encoded on the wire. |

## Usage

```php
use Prado\IO\Rpc\TXmlRpcBase64;

$client->upload(new TXmlRpcBase64(file_get_contents('/path/file.bin')));
```

## Gotchas

- Immutable: `$data` is `readonly`.
- Only meaningful for XML-RPC; JSON-RPC has no base64 type and treats it as a struct/object.

## See Also
- [`TXmlRpcCodec`](./TXmlRpcCodec.md) · [`TXmlRpcClient`](./TXmlRpcClient.md)
