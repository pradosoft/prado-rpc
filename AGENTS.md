# PRADO RPC Extension Agent Guidelines

`prado-rpc` is a PRADO 4 extension that adds JSON-RPC (1.0/2.0) and XML-RPC to PRADO as a server-side web service (`Prado\Web\Services`) and client classes (`Prado\IO\Rpc`), under the PSR-4 root `Prado\` (`src/`). See `CLAUDE.md` for the architecture.

## Build, Lint, and Test Commands

- **Unit tests**: `composer unittest` (`vendor/bin/phpunit --testsuite unit`) — self-contained, no `TApplication`.
- **Test filter**: `vendor/bin/phpunit --testsuite unit --filter <function|class>`.
- **PHPStan**: `vendor/bin/phpstan analyse src/ --memory-limit=512M`.
- **PHP CS Fixer**: `vendor/bin/php-cs-fixer fix --dry-run src/` (check) / `vendor/bin/php-cs-fixer fix src/` (apply).
- **Install / update deps**: `composer install` / `composer update`.
- A **full check** is, in order: `php -l` compile → php-cs-fixer → phpstan → phpunit (`unittest`). All must pass before a commit.
- NEVER add or change phpunit command options; only run the `unittest` script. When testing one class or cluster, run only its tests via `--filter`.

## Code Style Guidelines

### PHP Coding Standards
- PHP 8.1 minimum (CI: 8.1, 8.2, 8.3). PSR-12 via php-cs-fixer.
- Indentation: **1 tab**, never spaces. Line endings: Unix (`\n`). All files begin with `<?php`.
- `if` always has a `{}` block (no single-line bodies).
- Use `?` for single nullable types and in doc blocks.
- All class properties declare visibility; properties are `private` or `protected` `_camelCase`.

### Naming Conventions
- Classes: `TPascalCase` (`TRpcService`, `TJsonRpcClient`); interfaces `IPascalCase`.
- Methods/variables: `camelCase` (`registerMethods`, `$serverUrl`).
- Class constants: `SCREAMING_SNAKE_CASE` (`BASE_API_PROVIDER`).
- Class properties: `_camelCase` (`$_serverUrl`).
- Namespaces: `Prado\Web\Services` (server side), `Prado\IO\Rpc` (client side).

### Documentation Standards
- Every public method has a PHPDoc block with `@param`/`@return`/`@throws` as applicable, plus at least one descriptive sentence.
- Classes have a clear top docblock (what/why/how, an example where useful) with `@author` and `@since`.
- `@since` uses the current version (`1.0.0`).

### Compatibility
- The public API (class names, namespaces, signatures) matches what shipped in the PRADO framework; applications migrating from framework-bundled RPC to this package must not need code changes.
- Integration is declared in `composer.json` under `extra.prado`: `class-map` → `config/classes.json` (short Prado name → FQN, keeping `class="TRpcService"` working) and `error-messages` → `config/messages.txt` (the `rpc*` codes). No bootstrap module; both load system-wide for any consuming application. Add a `config/classes.json` entry whenever a new public class is added.
