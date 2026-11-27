<?php

declare(strict_types=1);

namespace KaririCode\Console\ValueObject;

/**
 * Correlates everything one command run emits — log records, PSR-14
 * events and the span — as Spec v4.3.3 E-021 requires of P5 telemetry.
 *
 * The format is the W3C Trace Context trace-id: 16 random bytes as 32
 * lowercase hex characters, never all zeros, so an exporter can use it as
 * the OpenTelemetry trace id unchanged.
 *
 *   $traceId = TraceId::generate();
 *   $logger->info('console.command.start', ['trace_id' => $traceId->value]);
 *
 * @package KaririCode\Console\ValueObject
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.5.0
 */
final readonly class TraceId
{
    private const string PATTERN = '/^(?!0{32})[0-9a-f]{32}$/';

    /** @throws \InvalidArgumentException when $value is not a valid W3C trace-id */
    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new \InvalidArgumentException('A trace id is 32 lowercase hex characters and not all zeros.');
        }
    }

    /** Uses random_bytes(), the PHP-core CSPRNG (Spec v4.3.3 E-019). */
    public static function generate(): self
    {
        do {
            $hex = bin2hex(random_bytes(16));
        } while ($hex === str_repeat('0', 32));

        return new self($hex);
    }
}
