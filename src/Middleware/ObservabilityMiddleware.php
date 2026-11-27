<?php

declare(strict_types=1);

namespace KaririCode\Console\Middleware;

use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Contract\CommandMiddleware;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Integration\ObservabilityBridge;
use KaririCode\Console\ValueObject\CommandResult;
use KaririCode\Console\ValueObject\CommandSpan;

/**
 * Records one duration histogram sample and one span per command (P5).
 *
 * Names follow the OpenTelemetry semantic conventions (status:
 * Development): durations in seconds; span named after the canonical
 * command (a documented low-cardinality alternative to the executable
 * name); process.exit.code, process.pid, process.executable.name, and
 * error.type when the exit code is not 0. process.command_args is never
 * collected — the conventions advise against it by default (PII).
 *
 * Register it outermost so the span covers every other middleware. The
 * span carries the run's TraceId, so log records and events correlate.
 *
 *   $application->addMiddleware(new ObservabilityMiddleware($bridge));
 *
 * Side effects: calls the bridge's histogram and span closures.
 *
 * @package KaririCode\Console\Middleware
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.3.0
 */
final readonly class ObservabilityMiddleware implements CommandMiddleware
{
    public const string DURATION_METRIC = 'console.command.duration';

    public function __construct(private ObservabilityBridge $observability) {}

    #[\Override]
    public function handle(CommandContext $context, callable $next): CommandResult
    {
        $commandName   = $context->command->name;
        $startUnixNano = (int) round(microtime(as_float: true) * 1_000_000.0) * 1_000;
        $startNs       = hrtime(as_number: true);

        try {
            $result = $next($context);
        } catch (\Throwable $e) {
            $this->record(context: $context, startUnixNano: $startUnixNano, elapsedNs: hrtime(as_number: true) - $startNs, attributes: self::attributes(commandName: $commandName, exitCode: ExitCode::Failure->exitCode(), errorType: $e::class));

            throw $e;
        }

        $errorType = $result->isSuccess() ? null : strtolower($result->exitCode->name);
        $this->record(context: $context, startUnixNano: $startUnixNano, elapsedNs: hrtime(as_number: true) - $startNs, attributes: self::attributes(commandName: $commandName, exitCode: $result->toExitInt(), errorType: $errorType));

        return $result;
    }

    /** @param array<string, int|string> $attributes */
    private function record(CommandContext $context, int $startUnixNano, int $elapsedNs, array $attributes): void
    {
        $this->observability->recordHistogram(name: self::DURATION_METRIC, value: $elapsedNs / 1_000_000_000, attributes: $attributes);

        $this->observability->exportSpan(new CommandSpan(
            name: $context->command->name,
            traceId: $context->traceId,
            spanId: self::newSpanId(),
            startUnixNano: $startUnixNano,
            endUnixNano: $startUnixNano + $elapsedNs,
            attributes: $attributes + [
                'process.executable.name' => self::executableName(),
                'process.pid' => (int) getmypid(),
            ],
        ));
    }

    /** W3C span-id: 8 random bytes, never all zeros. */
    private static function newSpanId(): string
    {
        do {
            $hex = bin2hex(random_bytes(8));
        } while ($hex === str_repeat('0', 16));

        return $hex;
    }

    /**
     * Low-cardinality attributes shared by the metric and the span.
     *
     * @return array<string, int|string>
     */
    private static function attributes(string $commandName, int $exitCode, ?string $errorType): array
    {
        $attributes = ['console.command.name' => $commandName, 'process.exit.code' => $exitCode];

        if ($errorType !== null) {
            $attributes['error.type'] = $errorType;
        }

        return $attributes;
    }

    /**
     * @psalm-suppress RedundantCondition Psalm evaluates PHP_BINARY for its own
     *                 process; at runtime it is '' on some SAPIs.
     */
    private static function executableName(): string
    {
        return basename(PHP_BINARY) ?: 'php';
    }
}
