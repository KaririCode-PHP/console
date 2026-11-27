<?php

declare(strict_types=1);

namespace KaririCode\Console\Event;

use KaririCode\Console\ValueObject\TraceId;

/**
 * PSR-14 event: A command threw. The exception is re-thrown after this event and
 * Application converts it into a failed result.
 *
 * Dispatched by EventMiddleware; listeners correlate it with the run's log
 * records and span through `$traceId`.
 *
 *   $dispatcher->dispatch(new CommandFailed(commandName: 'deploy', traceId: TraceId::generate(), error: $exception));
 *
 * @package KaririCode\Console\Event
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.5.0
 */
final readonly class CommandFailed
{
    public function __construct(
        public string $commandName,
        public TraceId $traceId,
        public \Throwable $error,
    ) {}
}
