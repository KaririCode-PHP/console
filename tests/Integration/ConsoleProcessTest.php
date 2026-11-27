<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs tests/Integration/bin/demo as a real process: exit statuses and the
 * stdout/stderr split are observed exactly as a shell would see them.
 */
#[CoversNothing]
final class ConsoleProcessTest extends TestCase
{
    /** @return iterable<string, array{list<string>, int, string, string}> */
    public static function invocations(): iterable
    {
        yield 'command output on stdout' => [['greet', 'Walmir'], 0, "Hello, Walmir\n", ''];
        yield 'version flag'             => [['--version'], 0, "demo 1.0.0\n", ''];
        yield 'unknown command'          => [['nope'], 1, '', 'Command "nope" is not registered'];
        yield 'missing argument'         => [['greet'], 2, '', 'Required argument "name" was not provided'];
        yield 'unknown option'           => [['greet', 'x', '--loud'], 2, '', 'Option "--loud" is invalid'];
        yield 'operand after --'         => [['greet', '--', '-v'], 0, "Hello, -v\n", ''];
    }

    /** @param list<string> $arguments */
    #[DataProvider('invocations')]
    public function testExitStatusAndStreams(array $arguments, int $status, string $stdout, string $stderr): void
    {
        [$code, $out, $err] = self::runDemo($arguments);

        self::assertSame($status, $code);
        self::assertSame($stdout, $out);
        $stderr === '' ? self::assertSame('', $err) : self::assertStringContainsString($stderr, $err);
    }

    public function testMachineReadableOutputStaysCleanWhenAnotherCommandFails(): void
    {
        [$code, $out] = self::runDemo(['list', '--format=json']);

        self::assertSame(0, $code);
        self::assertIsArray(json_decode($out, true, flags: JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString("\e[", $out, 'pipes are not TTYs: no ANSI');
    }

    /**
     * @param list<string> $arguments
     * @return array{int, string, string}
     */
    private static function runDemo(array $arguments): array
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/bin/demo', ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['PATH' => (string) getenv('PATH')],
        );
        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
