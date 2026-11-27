<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\ValueObject;

use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Core\ConcreteCommandContext;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Input\ArrayInput;
use KaririCode\Console\Middleware\TimingMiddleware;
use KaririCode\Console\Output\NullOutput;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\CommandResult;
use KaririCode\Console\ValueObject\TraceId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Verbosity::class)]
#[CoversClass(CommandResult::class)]
#[CoversClass(TimingMiddleware::class)]
#[CoversClass(ConcreteCommandContext::class)]
#[CoversClass(TraceId::class)]
final class CoreTypesTest extends TestCase
{
    /** @return iterable<string, array{string, ?Verbosity}> */
    public static function flags(): iterable
    {
        yield '-q'        => ['-q', Verbosity::Quiet];
        yield '--quiet'   => ['--quiet', Verbosity::Quiet];
        yield '-v'        => ['-v', Verbosity::Verbose];
        yield '--verbose' => ['--verbose', Verbosity::Verbose];
        yield '-vv'       => ['-vv', Verbosity::VeryVerbose];
        yield '-vvv'      => ['-vvv', Verbosity::Debug];
        yield '--debug'   => ['--debug', null];   // regression: not a global flag since 4.3.0
        yield 'other'     => ['--version', null];
    }

    #[DataProvider('flags')]
    public function testVerbosityFlags(string $flag, ?Verbosity $expected): void
    {
        self::assertSame($expected, Verbosity::fromFlag($flag));
    }

    public function testVerbosityOrdering(): void
    {
        self::assertTrue(Verbosity::Debug->isAtLeast(Verbosity::Verbose));
        self::assertFalse(Verbosity::Quiet->isAtLeast(Verbosity::Normal));
        self::assertTrue(Verbosity::Quiet->isAtLeast(Verbosity::Quiet), 'quiet-level messages still print when quiet');
    }

    public function testCommandResultFactoriesAndExitStatus(): void
    {
        $success = CommandResult::success('a', 1.5);
        $failure = CommandResult::failure('a', 'boom');
        $invalid = CommandResult::invalid('a', 'bad', 2.0);

        self::assertTrue($success->isSuccess());
        self::assertSame([0, 1, 2], [$success->toExitInt(), $failure->toExitInt(), $invalid->toExitInt()]);
        self::assertSame(['boom', 2.0], [$failure->errorMessage, $invalid->durationMs]);
    }

    public function testTimingMiddlewareReplacesDurationAndKeepsTheOutcome(): void
    {
        $context = new ConcreteCommandContext(commandName: 't', input: new ArrayInput(), output: new NullOutput(), command: new StubCommand('t'), traceId: TraceId::generate());

        $result = (new TimingMiddleware())->handle(
            $context,
            static function (CommandContext $c): CommandResult {
                usleep(2_000);

                return CommandResult::invalid($c->commandName, 'bad', 999.0);
            },
        );

        self::assertSame(ExitCode::Invalid, $result->exitCode);
        self::assertSame('bad', $result->errorMessage);
        self::assertGreaterThanOrEqual(1.5, $result->durationMs);
        self::assertLessThan(999.0, $result->durationMs);
    }

    public function testTraceIdFollowsW3cTraceContext(): void
    {
        $first  = TraceId::generate();
        $second = TraceId::generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first->value);
        self::assertNotSame($first->value, $second->value);

        foreach (['', str_repeat('0', 32), str_repeat('A', 32), str_repeat('a', 31)] as $invalid) {
            try {
                new TraceId($invalid);
                self::fail("accepted '{$invalid}'");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
