<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Fixture;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 logger that keeps every record, optionally appending "log:<message>"
 * to a shared signal list so tests can assert ordering across sinks.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{string, string, array<array-key, mixed>}> */
    public array $records = [];

    /** @param \ArrayObject<int, string>|null $signals */
    public function __construct(private readonly ?\ArrayObject $signals = null) {}

    /** @param array<array-key, mixed> $context */
    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [is_string($level) ? $level : '?', (string) $message, $context];
        $this->signals?->append("log:{$message}");
    }
}
