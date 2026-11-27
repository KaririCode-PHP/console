<?php

declare(strict_types=1);

namespace KaririCode\Console\ValueObject;

/**
 * A finished command-execution span, shaped after the OpenTelemetry
 * semantic conventions for CLI programs (span kind INTERNAL).
 *
 * Exporters convert it into a real OTel span; Console itself stays free of
 * any telemetry dependency (ADR-010).
 *
 *   $bridge->exportSpan(new CommandSpan(
 *       name: 'deploy', traceId: $traceId, spanId: 'a1b2c3d4e5f60718',
 *       startUnixNano: $start, endUnixNano: $end, attributes: ['process.exit.code' => 0],
 *   ));
 *
 * @package KaririCode\Console\ValueObject
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.3.0
 */
final readonly class CommandSpan
{
    /**
     * @param string                    $name       Low-cardinality span name: the canonical command name.
     * @param TraceId                   $traceId    Shared with the run's log records and events (E-021).
     * @param string                    $spanId     W3C span-id: 16 lowercase hex characters, not all zeros.
     * @param array<string, int|string> $attributes process.exit.code, process.pid, process.executable.name,
     *                                              console.command.name, and error.type on failure.
     *
     * @throws \InvalidArgumentException when the span id is malformed or the span ends before it starts
     */
    public function __construct(
        public string $name,
        public TraceId $traceId,
        public string $spanId,
        public int $startUnixNano,
        public int $endUnixNano,
        public array $attributes,
    ) {
        if (preg_match('/^(?!0{16})[0-9a-f]{16}$/', $spanId) !== 1) {
            throw new \InvalidArgumentException('A span id is 16 lowercase hex characters and not all zeros.');
        }

        if ($endUnixNano < $startUnixNano) {
            throw new \InvalidArgumentException('A span cannot end before it starts.');
        }
    }

    /** OTel CLI conventions: the span is an error iff process.exit.code is not 0. */
    public function isError(): bool
    {
        return ($this->attributes['process.exit.code'] ?? 0) !== 0;
    }
}
