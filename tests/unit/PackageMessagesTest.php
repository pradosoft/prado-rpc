<?php

use Prado\Exceptions\TConfigurationException;

/**
 * Tests for the package error messages declared in composer.json's extra.prado.error-messages
 * and stored in config/messages.txt.
 *
 * The source throws exceptions by message code. A code missing from messages.txt surfaces as the
 * bare code, so these tests scan src/ for every thrown rpc* code and check it against the file.
 */
class PackageMessagesTest extends PHPUnit\Framework\TestCase
{
	/**
	 * @return array<string,string> the code => message map from config/messages.txt.
	 */
	private function messages(): array
	{
		$messages = [];
		foreach (file(__DIR__ . '/../../config/messages.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			[$code, $message] = explode('=', $line, 2);
			$messages[trim($code)] = trim($message);
		}
		return $messages;
	}

	/**
	 * @return array<string,int> the code => argument count of every rpc* exception thrown in src/.
	 */
	private function thrownCodes(): array
	{
		$codes = [];
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../src'));
		foreach ($files as $file) {
			if ($file->getExtension() !== 'php') {
				continue;
			}
			preg_match_all("/throw new \\w+\\((?:\\d+,\\s*)?'(rpc[a-z_]+)'([^)]*)\\)/", file_get_contents($file->getPathname()), $matches, PREG_SET_ORDER);
			foreach ($matches as [, $code, $args]) {
				$codes[$code] = max($codes[$code] ?? 0, substr_count($args, ','));
			}
		}
		return $codes;
	}

	public function testSourceThrowsRpcCodes(): void
	{
		$this->assertArrayHasKey('rpcservice_apiproviderid_duplicated', $this->thrownCodes());
		$this->assertArrayHasKey('rpcclient_unsupported_handler', $this->thrownCodes());
	}

	public function testEveryThrownCodeHasAMessage(): void
	{
		$messages = $this->messages();
		foreach (array_keys($this->thrownCodes()) as $code) {
			$this->assertArrayHasKey($code, $messages, "No message for thrown code {$code}");
			$this->assertNotSame('', $messages[$code], "Empty message for code {$code}");
		}
	}

	public function testEveryMessageIsThrown(): void
	{
		$thrown = $this->thrownCodes();
		foreach (array_keys($this->messages()) as $code) {
			$this->assertArrayHasKey($code, $thrown, "Message code {$code} is not thrown in src/");
		}
	}

	public function testEveryPlaceholderHasAnArgument(): void
	{
		$messages = $this->messages();
		foreach ($this->thrownCodes() as $code => $argCount) {
			preg_match_all('/\{(\d+)\}/', $messages[$code] ?? '', $matches);
			foreach ($matches[1] as $index) {
				$this->assertLessThan($argCount, (int) $index, "Message {$code} uses {{$index}} but receives {$argCount} argument(s)");
			}
		}
	}

	public function testMessageResolvesWithArguments(): void
	{
		$e = new TConfigurationException('rpcservice_apiprovider_invalid', 'Foo', 'customers');
		$this->assertSame(
			"RPC API provider class 'Foo' of provider 'customers' must extend Prado\\Web\\Services\\TRpcApiProvider.",
			$e->getMessage()
		);
	}
}
