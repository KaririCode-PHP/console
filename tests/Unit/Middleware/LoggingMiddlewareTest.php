<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Middleware;

use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Core\ConcreteCommandContext;
use KaririCode\Console\Input\ArrayInput;
use KaririCode\Console\Middleware\LoggingMiddleware;
use KaririCode\Console\Output\NullOutput;
use KaririCode\Console\Tests\Fixture\RecordingLogger;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\CommandResult;
use KaririCode\Console\ValueObject\TraceId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoggingMiddleware::class)]
final class LoggingMiddlewareTest extends TestCase
{
    public function testLogsStartAndFinishWithTheRunsTraceId(): void
    {
        $logger  = new RecordingLogger();
        $context = self::context();

        (new LoggingMiddleware($logger))->handle($context, static fn(): CommandResult => CommandResult::success('demo', 2.5));

        $correlation = ['command' => 'demo', 'trace_id' => $context->traceId->value];
        self::assertSame(['info', 'console.command.start', $correlation], $logger->records[0]);
        self::assertSame(['info', 'console.command.finish', $correlation + ['exit_code' => 0, 'duration_ms' => 2.5]], $logger->records[1]);
    }

    public function testAFailedResultIsLoggedAsAnErrorWithItsMessage(): void
    {
        $logger = new RecordingLogger();

        (new LoggingMiddleware($logger))->handle(self::context(), static fn(): CommandResult => CommandResult::failure('demo', 'boom'));

        self::assertSame('error', $logger->records[1][0]);
        self::assertSame('boom', $logger->records[1][2]['error'] ?? null);
        self::assertSame(1, $logger->records[1][2]['exit_code'] ?? null);
    }

    /** PSR-3 §1.3: a throwable travels in the context under the "exception" key. */
    public function testAThrowableIsLoggedUnderTheExceptionKeyAndRethrown(): void
    {
        $logger = new RecordingLogger();
        $error  = new \RuntimeException('kaput');

        try {
            (new LoggingMiddleware($logger))->handle(self::context(), static fn(): CommandResult => throw $error);
            self::fail('the throwable must be re-thrown');
        } catch (\RuntimeException $caught) {
            self::assertSame($error, $caught);
        }

        self::assertSame(['error', 'console.command.error'], [$logger->records[1][0], $logger->records[1][1]]);
        self::assertSame($error, $logger->records[1][2]['exception'] ?? null);
    }

    private static function context(): CommandContext
    {
        return new ConcreteCommandContext(
            commandName: 'demo',
            input: new ArrayInput(),
            output: new NullOutput(),
            command: new StubCommand('demo'),
            traceId: TraceId::generate(),
        );
    }
}
