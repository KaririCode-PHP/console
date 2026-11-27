<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Middleware;

use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Core\ConcreteCommandContext;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Event\CommandFailed;
use KaririCode\Console\Event\CommandFinished;
use KaririCode\Console\Event\CommandStarted;
use KaririCode\Console\Input\ArrayInput;
use KaririCode\Console\Integration\ObservabilityBridge;
use KaririCode\Console\Middleware\EventMiddleware;
use KaririCode\Console\Middleware\ObservabilityMiddleware;
use KaririCode\Console\Output\NullOutput;
use KaririCode\Console\Tests\Fixture\RecordingDispatcher;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\CommandResult;
use KaririCode\Console\ValueObject\CommandSpan;
use KaririCode\Console\ValueObject\TraceId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventMiddleware::class)]
#[CoversClass(CommandStarted::class)]
#[CoversClass(CommandFinished::class)]
#[CoversClass(CommandFailed::class)]
#[CoversClass(ObservabilityMiddleware::class)]
#[CoversClass(ObservabilityBridge::class)]
#[CoversClass(CommandSpan::class)]
final class TelemetryMiddlewareTest extends TestCase
{
    // ── PSR-14 events ────────────────────────────────────────────────────

    public function testPublishesStartedThenFinishedWithTheRunsTraceId(): void
    {
        $events  = new RecordingDispatcher();
        $context = self::context();

        (new EventMiddleware($events))->handle($context, static fn(): CommandResult => CommandResult::failure('demo', 'nope'));

        [$started, $finished] = $events->events;
        self::assertInstanceOf(CommandStarted::class, $started);
        self::assertInstanceOf(CommandFinished::class, $finished);
        self::assertSame('demo', $started->commandName);
        self::assertFalse($finished->result->isSuccess());
        self::assertSame($context->traceId, $started->traceId);
        self::assertSame($context->traceId, $finished->traceId);
    }

    public function testPublishesFailedAndRethrowsOnThrowable(): void
    {
        $events = new RecordingDispatcher();
        $error  = new \RuntimeException('boom');

        try {
            (new EventMiddleware($events))->handle(self::context(), static fn(): CommandResult => throw $error);
            self::fail('the throwable must be re-thrown');
        } catch (\RuntimeException $caught) {
            self::assertSame($error, $caught);
        }

        self::assertCount(2, $events->events);
        $failed = $events->events[1];
        self::assertInstanceOf(CommandFailed::class, $failed);
        self::assertSame($error, $failed->error);
    }

    // ── Metrics and spans (OTel CLI semantic conventions) ────────────────

    public function testRecordsDurationInSecondsAndASpanCorrelatedByTraceId(): void
    {
        [$middleware, $sink] = self::observabilityMiddleware();
        $context             = self::context();

        $middleware->handle($context, static fn(): CommandResult => CommandResult::success('demo'));

        [$metric, $seconds, $attributes] = $sink->samples[0];
        $span                            = $sink->spans[0];

        self::assertSame('console.command.duration', $metric);
        self::assertGreaterThanOrEqual(0.0, $seconds);
        self::assertLessThan(5.0, $seconds, 'seconds, not nanoseconds');
        self::assertSame(['console.command.name' => 'demo', 'process.exit.code' => 0], $attributes);

        self::assertSame('demo', $span->name);
        self::assertSame($context->traceId, $span->traceId);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $span->spanId);
        self::assertFalse($span->isError());
        self::assertSame(getmypid(), $span->attributes['process.pid'] ?? null);
        self::assertArrayHasKey('process.executable.name', $span->attributes);
        self::assertArrayNotHasKey('process.command_args', $span->attributes, 'PII: not collected by default');
        self::assertGreaterThanOrEqual($span->startUnixNano, $span->endUnixNano);
    }

    public function testFailedResultAndThrowableCarryErrorType(): void
    {
        [$middleware, $sink] = self::observabilityMiddleware();
        $middleware->handle(self::context(), static fn(): CommandResult => CommandResult::invalid('demo', 'bad'));

        self::assertSame('invalid', $sink->spans[0]->attributes['error.type'] ?? null);
        self::assertSame(ExitCode::Invalid->exitCode(), $sink->spans[0]->attributes['process.exit.code'] ?? null);
        self::assertTrue($sink->spans[0]->isError());

        try {
            $middleware->handle(self::context(), static fn(): CommandResult => throw new \DomainException('x'));
        } catch (\DomainException) {
        }

        self::assertSame(\DomainException::class, $sink->spans[1]->attributes['error.type'] ?? null);
    }

    public function testSpanRejectsMalformedIdsAndNegativeDuration(): void
    {
        $valid = ['name' => 'x', 'traceId' => TraceId::generate(), 'spanId' => '0123456789abcdef', 'startUnixNano' => 10, 'endUnixNano' => 11, 'attributes' => []];

        foreach ([['spanId' => str_repeat('0', 16)], ['spanId' => 'XYZ'], ['endUnixNano' => 9]] as $override) {
            try {
                new CommandSpan(...array_replace($valid, $override));
                self::fail('accepted ' . json_encode($override));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
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

    /** @return array{ObservabilityMiddleware, object{samples: list<array{string, float, array<string, int|string>}>, spans: list<CommandSpan>}} */
    private static function observabilityMiddleware(): array
    {
        $sink = new class {
            /** @var list<array{string, float, array<string, int|string>}> */
            public array $samples = [];

            /** @var list<CommandSpan> */
            public array $spans = [];
        };

        $bridge = new ObservabilityBridge(
            histogram: static function (string $name, float $value, array $attributes) use ($sink): void {
                $sink->samples[] = [$name, $value, $attributes];
            },
            spanExporter: static function (CommandSpan $span) use ($sink): void {
                $sink->spans[] = $span;
            },
        );

        return [new ObservabilityMiddleware($bridge), $sink];
    }
}
