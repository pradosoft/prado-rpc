<?php
/**
 * Common settings for all unit tests of the PRADO RPC extension.
 *
 * Autoloads the framework and the extension via Composer's PSR-4 map, then registers the
 * extension's class map and error message file. In a running application
 * TApplicationConfiguration does this from composer.json's extra.prado section; standalone tests
 * have no TApplication, so this bootstrap stands in. Both files are the same ones composer.json
 * references, keeping a single source of truth.
 */

require_once(__DIR__ . '/../../vendor/autoload.php');

$config = __DIR__ . '/../../config';

// Register the short Prado class names (e.g. TJsonRpcProtocol) so name-based resolution — such as
// TRpcService instantiating a protocol handler by short name — works as it does under an application.
// Referencing Prado\Prado also activates the framework's class-map autoloader.
\Prado\Prado::registerClassMap(
	(array) json_decode((string) file_get_contents($config . '/classes.json'), true)
);

// Register the extension's exception message codes (rpc*) so they resolve to readable text.
\Prado\Exceptions\TException::addMessageFile(realpath($config . '/messages.txt'));
