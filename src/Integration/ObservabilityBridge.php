<?php

declare(strict_types=1);

namespace KaririCode\Console\Integration;

use KaririCode\Console\ValueObject\CommandSpan;

/**
 * Hands Console's metrics and spans to the host's telemetry stack.
 *
 * Both sinks are optional closures, so Console carries no OpenTelemetry
 * dependency; a missing sink simply drops that signal (ADR-010).
 *
 *   new ObservabilityBridge(histogram: $meter->record(...), spanExporter: $tracer->export(...));
 *
 * Side effects: invokes the host's histogram and span closures.
 *
 * @package KaririCode\Console\Integration
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.3.0
 */
final readonly class ObservabilityBridge
{
    /**
     * @param \Closure(string, float, array<string, int|string>): void|null $histogram    ($name, $value, $attributes)
     * @param \Closure(CommandSpan): void|null                              $spanExporter
     */
    public function __construct(
        private ?\Closure $histogram = null,
        private ?\Closure $spanExporter = null,
    ) {}

    /** @param array<string, int|string> $attributes */
    public function recordHistogram(string $name, float $value, array $attributes): void
    {
        if ($this->histogram !== null) {
            ($this->histogram)($name, $value, $attributes);
        }
    }

    public function exportSpan(CommandSpan $span): void
    {
        if ($this->spanExporter !== null) {
            ($this->spanExporter)($span);
        }
    }
}
