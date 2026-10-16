# CLAUDE.md

This file provides guidance to Agents when working with code in this repository.

## What This Is

**prado-rpc** is a PRADO 4 extension that adds **JSON-RPC** (1.0/2.0) and **XML-RPC** to the [PRADO framework](https://github.com/pradosoft/prado), as both a server-side web service and client classes. All source lives under `src/` (PSR-4 namespace `Prado\`, split between `Prado\Web\Services` for the server side and `Prado\IO\Rpc` for the client side). Tests mirror that under `tests/unit/`. Current version: **1.0.1**. The package has its own `1.0.x` release line, tagged without a `v` prefix (`1.0`, `1.0.1`); patch releases carry backward-compatible fixes. The package targets PRADO 4.4.

The classes originated in the PRADO framework (since PRADO 3.2) and were extracted for PRADO 4.4. The namespaces are unchanged so existing applications keep working when the package is installed.

## Commands

```bash
# Code style (tabs, PSR-12) — check, then fix
vendor/bin/php-cs-fixer fix --dry-run src/
vendor/bin/php-cs-fixer fix src/

# Static analysis
vendor/bin/phpstan analyse src/ --memory-limit=512M

# Unit tests
composer unittest        # vendor/bin/phpunit --testsuite unit
```

### Full Check (required before git commit)

Run these in order — all must pass:

1. `php -l` on changed files
2. `vendor/bin/php-cs-fixer fix --dry-run src/`
3. `vendor/bin/phpstan analyse src/ --memory-limit=512M`
4. `composer unittest`

> **Never add or change phpunit command options** — run only the `unittest` script. When testing one class/cluster, use `--filter <name>`.

## Architecture

### Server side — `Prado\Web\Services` (`src/Web/Services/`)

Request flow: `TRpcService → TRpcServer → TRpcProtocol → TRpcApiProvider`

| Class | Role |
|---|---|
| `TRpcService` | The PRADO service: parses `<rpcapi>` config (XML or PHP array), selects the protocol by request `Content-Type`, instantiates the provider |
| `TRpcServer` | Moves data between the service and the provider; subclass for logging, filtering, request/response transformation |
| `TRpcProtocol` | Abstract wire format: method registry (`addMethod`) and dispatch (`callMethod`) |
| `TJsonRpcProtocol` | JSON-RPC 1.0 and 2.0 (`application/json`) |
| `TXmlRpcProtocol` | XML-RPC (`text/xml`), using `TXmlRpcCodec` |
| `TRpcApiProvider` | Abstract API provider; subclasses declare methods via `registerMethods()` |
| `TRpcException` | An RPC fault carrying a protocol-level fault code (JSON-RPC reserves -32768 to -32000) |

### Client side — `Prado\IO\Rpc` (`src/IO/Rpc/`)

| Class | Role |
|---|---|
| `TRpcClient` | Base client: `create()` factory (`'json'`/`'xml'`), HTTP POST via `Prado\IO\HttpClient\THttpClient`, notification mode |
| `TJsonRpcClient` | JSON-RPC 2.0 by default; `setVersion('1.0')` for JSON-RPC 1.0 |
| `TXmlRpcClient` | XML-RPC |
| `TXmlRpcCodec` | `final` XML-RPC value encoder/decoder (shared by client and server protocol) |
| `TXmlRpcBase64` | Wrapper marking a string for `<base64>` encoding |
| `TRpcClientRequestException` | Transport-level failure (connect error, non-2xx status) |
| `TRpcClientResponseException` | Server fault or unparseable response |

Remote methods are invoked through `__call`, so a client call reads like a native method call. The HTTP transport is pluggable via `setDownloader()` (any `THttpClient`, including `TCachedHttpClient`).

## Naming Conventions

| Thing | Convention | Example |
|---|---|---|
| Classes | `TPascalCase` | `TRpcService` |
| Methods | `camelCase` | `registerMethods` |
| Variables | `camelCase` | `$serverUrl` |
| Class Constants | `SCREAMING_SNAKE_CASE` | `BASE_API_PROVIDER` |
| Class properties | `_camelCase` | `$_serverUrl` |
| Namespaces | `Prado\{Module}` | `Prado\IO\Rpc` |

## Important Rules

- **Namespaces** `Prado\Web\Services` and `Prado\IO\Rpc`, PSR-4 → `src/`. Composer PSR-4 autoloading resolves classes by their fully-qualified name.
- **`extra.prado` in `composer.json`** declares the two integration points that PRADO's `TApplicationConfiguration` reads from every installed extension:
  - `class-map` → `config/classes.json`: maps short Prado names (`TRpcService`, `TXmlRpcClient`, …) to FQNs so app configs may reference `class="TRpcService"`. Add an entry here whenever a new public class is added.
  - `error-messages` → `config/messages.txt`: the `rpc*` exception message codes. There is **no** bootstrap module; both files load system-wide for any application that installs the package.
- **Error codes** are `rpc*` keys in `config/messages.txt`. The framework `messages.txt` is not used. New codes describe the failure; `{0}`, `{1}` are positional parameters.
- **cs-fixer = tabs** (`@PSR12` + `setIndent("\t")`). If cs-fixer suddenly wants to convert tabs → spaces across *every* file, `.php-cs-fixer.dist.php` has been clobbered by a php-cs-fixer scaffold (the `@auto` default); restore the tab-based config (it matches the sibling extensions).
- **`if` statements** always use a block (`{}`), never a single-line body.
- **`@since`** records the version that introduced the class or method: the twelve classes extracted from the framework carry `@since 3.2.0` (verified against the framework git history — added 2010-03-27 in commit `25da68e7`, first released in PRADO 3.2.0, and tagged `@since 3.2` in the framework docblocks); `TXmlRpcCodec` and `TXmlRpcBase64` are new in this package and carry `@since 4.4.0`. New classes/methods use `@since 4.4.0`, the PRADO framework version the package targets, independent of the package release number (see "What This Is"); omit the method tag when it matches the class tag.
- **Backward compatibility** — all changes must be backward compatible within a point release. The public API (class names, namespaces, method signatures) matches what shipped in the PRADO framework; applications migrating to the package must not need code changes.
- Method docblocks are **tight** and carry at least one descriptive sentence (not only `@param`/`@return`).

## Testing

- **Unit** (`tests/unit`, `composer unittest`) — self-contained PHPUnit tests; no `TApplication` is created. Client tests inject a mock `THttpClient`; protocol tests drive encode/decode round-trips; service tests exercise configuration parsing.
- The bootstrap (`tests/test_tools/phpunit_bootstrap.php`) loads composer's autoloader and registers `config/messages.txt` directly. In a running application `TApplicationConfiguration` registers it from `extra.prado.error-messages` instead; standalone tests have no `TApplication`, so the bootstrap stands in.
- New code includes tests for typical **and** edge cases, and for error/exception paths. Tests are isolated (no shared state).

## Code Style

- Indentation: **tabs** (not spaces). Line endings: Unix (`\n`). PHP minimum: 8.1 (CI tests 8.1, 8.2, 8.3). PSR-12 enforced via php-cs-fixer. Use `?` for single nullable types and in doc blocks.

### Documentation Style (enforced)

Docblocks are technical documentation: direct statements, present tense, American English, clear and brief.

_Banned constructions_:
- **Antithesis** ("not just X, it Ys", "is not a Y, it's a Z", "rather than X, it Ys"). State what it does, once.
- **Em-dash dramatic asides** for emphasis. Use a period or a plain clause.
- **Editorializing / filler.**
- **Rule-of-three rhetorical lists** and build-up sentences. One fact per sentence.

Prefer subject-verb-object declaratives and `condition → result` lists.

## Development Environment

- PHP 8.1+ with `ext-simplexml` and `ext-dom`.
- `pradosoft/prado` is a dev dependency, providing `TService`/`TModule`/`TComponent` and the `IO/HttpClient` layer. PRADO 4.4 is unreleased, so `composer.json` pulls the framework from its git repository: a `vcs` repository for `https://github.com/pradosoft/prado` plus `"pradosoft/prado": "dev-master"` under `"minimum-stability": "dev"` / `"prefer-stable": true`. `composer install` clones the framework from git for everyone, with no local path checkout. When 4.4 ships, switch the constraint to `^4.4` and drop the VCS repository and stability flags.
- Presume project dependencies are installed.

## Anti-Patterns (Required Safeguards)

- **Never** run `git clone/checkout/mv/restore/rm/branch/add/commit/merge/rebase/reset/pull/push/fetch` without developer approval first.
- **Never** run `rm` on any path without developer approval first.
- **Never** remove `composer --dev` dependencies.
- **Never** erase or overwrite files during unit testing — the file changes being tested must be preserved.
