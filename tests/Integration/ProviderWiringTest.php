<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Integration;

use KaririCode\Console\Event\CommandStarted;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Integration\ConfiguratorBridge;
use KaririCode\Console\Integration\Instrumentation;
use KaririCode\Console\Integration\ObservabilityBridge;
use KaririCode\Console\Output\BufferedOutput;
use KaririCode\Console\Provider\ConsoleServiceProvider;
use KaririCode\Console\Tests\Fixture\RecordingDispatcher;
use KaririCode\Console\Tests\Fixture\RecordingLogger;
use KaririCode\Console\ValueObject\CommandSpan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsoleServiceProvider::class)]
#[CoversClass(ConfiguratorBridge::class)]
#[CoversClass(Instrumentation::class)]
final class ProviderWiringTest extends TestCase
{
    /** Regression: scan_paths was read by nothing. */
    public function testConfiguredScanPathsAreDiscovered(): void
    {
        $reads        = 0;
        $configurator = new ConfiguratorBridge(static function () use (&$reads): array {
            $reads++;

            return ['name' => 'cfg-app', 'version' => '9.9', 'scan_paths' => [__DIR__ . '/../Fixture/Loader']];
        });

        $app = (new ConsoleServiceProvider(configurator: $configurator))->build();

        self::assertSame(['cfg-app', '9.9'], [$app->name, $app->version]);
        self::assertTrue($app->registry->has('report:build'));
        self::assertSame(1, $reads, 'configuration is read once, lazily');
    }

    public function testEveryTelemetrySignalIsEmittedForOneCommandAndShareOneTraceId(): void
    {
        $signals = new \ArrayObject();
        $logger  = new RecordingLogger($signals);
        $events  = new RecordingDispatcher($signals);
        $spans   = [];

        $provider = new ConsoleServiceProvider(instrumentation: new Instrumentation(
            logger: $logger,
            events: $events,
            observability: new ObservabilityBridge(
                histogram: static fn(string $metric) => $signals->append("metric:{$metric}"),
                spanExporter: static function (CommandSpan $span) use ($signals, &$spans): void {
                    $signals->append("span:{$span->name}");
                    $spans[] = $span;
                },
            ),
        ));

        self::assertSame(0, $provider->build()->dispatch(new ArgvInput(['list']), new BufferedOutput()));
        self::assertSame([
            'log:console.command.start',
            'event:CommandStarted',
            'event:CommandFinished',
            'log:console.command.finish',
            'metric:console.command.duration',
            'span:list',
        ], $signals->getArrayCopy());

        $traceId = $spans[0]->traceId->value;
        $started = $events->events[0];
        self::assertInstanceOf(CommandStarted::class, $started);
        self::assertSame($traceId, $started->traceId->value);
        self::assertSame($traceId, $logger->records[0][2]['trace_id'] ?? null);
        self::assertSame($traceId, $logger->records[1][2]['trace_id'] ?? null);
    }
}
