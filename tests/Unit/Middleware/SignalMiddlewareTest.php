<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Middleware;

use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Contract\Signalable;
use KaririCode\Console\Core\ConcreteCommandContext;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Enum\Signal;
use KaririCode\Console\Input\ArrayInput;
use KaririCode\Console\Middleware\SignalMiddleware;
use KaririCode\Console\Output\NullOutput;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\CommandResult;
use KaririCode\Console\ValueObject\TraceId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Uses real POSIX signals sent to this process with posix_kill().
 */
#[CoversClass(SignalMiddleware::class)]
#[CoversClass(Signal::class)]
#[RequiresPhpExtension('pcntl')]
#[RequiresFunction('posix_kill')]
final class SignalMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGHUP, SIG_DFL);
        pcntl_async_signals(false);
    }

    /** Regression: without async dispatch the handler never ran and SIGTERM was swallowed. */
    public function testDeliversSignalToCommandWhileItRuns(): void
    {
        $command = self::worker(accept: true, signo: SIGTERM);

        $result = self::dispatchThrough($command);

        self::assertTrue($result->isSuccess());
        self::assertSame(Signal::Terminate, $command->received);
    }

    public function testRestoresPreviousHandlersAndAsyncSetting(): void
    {
        $previous = static function (): void {};
        pcntl_signal(SIGHUP, $previous);
        pcntl_async_signals(false);

        self::dispatchThrough(self::worker(accept: true, signo: SIGTERM));

        self::assertSame($previous, pcntl_signal_get_handler(SIGHUP));
        self::assertSame(SIG_DFL, pcntl_signal_get_handler(SIGTERM));
        self::assertFalse(pcntl_async_signals());
    }

    public function testDeclinedSignalIsPassedToThePreviousPhpHandler(): void
    {
        $delivered = null;
        pcntl_signal(SIGHUP, static function (int $signo) use (&$delivered): void {
            $delivered = $signo;
        });

        $command = self::worker(accept: false, signo: SIGHUP);
        self::dispatchThrough($command);

        self::assertSame(Signal::Hangup, $command->received);
        self::assertSame(SIGHUP, $delivered);
    }

    public function testDeclinedSignalIsDroppedWhenPreviouslyIgnored(): void
    {
        pcntl_signal(SIGHUP, SIG_IGN);

        $result = self::dispatchThrough(self::worker(accept: false, signo: SIGHUP));

        self::assertTrue($result->isSuccess(), 'process survived an ignored signal');
        self::assertSame(SIG_IGN, pcntl_signal_get_handler(SIGHUP));
    }

    /**
     * With SIG_DFL in force the declined signal must terminate the process
     * by that signal (status 128 + 15), exactly as if nobody subscribed.
     */
    public function testDeclinedSignalTerminatesProcessWhenDispositionWasDefault(): void
    {
        $autoload = var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true);
        $script   = <<<PHP
            <?php
            require {$autoload};
            use KaririCode\\Console\\Tests\\Unit\\Middleware\\SignalMiddlewareTest as T;
            T::dispatchThrough(T::worker(accept: false, signo: SIGTERM));
            echo 'survived';
            PHP;

        $file = tempnam(sys_get_temp_dir(), 'sig');
        file_put_contents($file, $script);

        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>/dev/null', $lines, $status);
        unlink($file);

        self::assertSame(128 + SIGTERM, $status);
        self::assertNotContains('survived', $lines);
    }

    public function testPassesThroughCommandsThatAreNotSignalable(): void
    {
        $command = new StubCommand();

        self::assertTrue(self::dispatchThrough($command)->isSuccess());
        self::assertSame(1, $command->executions);
        self::assertSame(SIG_DFL, pcntl_signal_get_handler(SIGTERM));
    }

    // ── Helpers (public static: reused by the child-process script) ──────

    public static function dispatchThrough(Command $command): CommandResult
    {
        return (new SignalMiddleware())->handle(
            new ConcreteCommandContext(commandName: $command->name, input: new ArrayInput(), output: new NullOutput(), command: $command, traceId: TraceId::generate()),
            static fn(CommandContext $c): CommandResult => new CommandResult($c->command->execute($c->input, $c->output), $c->commandName),
        );
    }

    /** @return Command&Signalable&object{received: ?Signal} */
    public static function worker(bool $accept, int $signo): Command&Signalable
    {
        return new class ($accept, $signo) implements Command, Signalable {
            public string $name = 'worker';

            public string $description = 'Sends itself a signal mid-run';

            /** @var list<Signal> */
            public array $subscribedSignals = [Signal::Terminate, Signal::Hangup];

            public ?Signal $received = null;

            public function __construct(private readonly bool $accept, private readonly int $signo) {}

            public function configure(InputDefinition $definition): void {}

            public function handleSignal(Signal $signal): bool
            {
                $this->received = $signal;

                return $this->accept;
            }

            public function execute(Input $input, Output $output): ExitCode
            {
                posix_kill((int) getmypid(), $this->signo);

                for ($i = 0; $i < 2_000 && $this->received === null; $i++) {
                    usleep(100); // async delivery happens at the next VM tick
                }

                return ExitCode::Success;
            }
        };
    }
}
