<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Integration;

use KaririCode\Console\Prompt\Prompter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;

/**
 * Drives Prompter::password() through a real pseudo-terminal: the terminal
 * must not echo the secret, and its settings must be restored afterwards.
 */
#[CoversClass(Prompter::class)]
#[RequiresOperatingSystemFamily('Linux')]
#[Group('security')]
final class PasswordPromptTest extends TestCase
{
    public function testSecretIsNotEchoedAndTerminalIsRestored(): void
    {
        $process = @proc_open(
            [PHP_BINARY, __DIR__ . '/bin/password'],
            [0 => ['pty'], 1 => ['pty'], 2 => ['pty']],
            $pipes,
            null,
            ['PATH' => (string) getenv('PATH'), 'TERM' => 'xterm'],
        );

        if (!is_resource($process)) {
            self::markTestSkipped('This PHP build cannot allocate a pseudo-terminal.');
        }

        stream_set_blocking($pipes[1], false);
        $seen = self::readUntil($pipes[1], '/> /');            // prompt shown: echo is now off
        fwrite($pipes[0], "s3cret-value\n");
        $seen = self::readUntil($pipes[1], '/restored=(yes|no)/', $seen);
        proc_close($process);

        self::assertStringContainsString('length=12', $seen, 'the child read the whole secret');
        self::assertStringNotContainsString('s3cret-value', $seen, 'the terminal echoed the secret');
        self::assertStringContainsString('restored=yes', $seen);
    }

    /** @param resource $stream */
    private static function readUntil($stream, string $pattern, string $buffer = '', float $timeout = 5.0): string
    {
        $deadline = microtime(true) + $timeout;

        while (preg_match($pattern, $buffer) !== 1 && microtime(true) < $deadline) {
            $chunk   = @fread($stream, 8192);
            $buffer .= is_string($chunk) ? $chunk : '';
            usleep(10_000);
        }

        return $buffer;
    }
}
