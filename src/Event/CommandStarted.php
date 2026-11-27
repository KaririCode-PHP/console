<?php

declare(strict_types=1);

namespace KaririCode\Console\Event;

use KaririCode\Console\ValueObject\TraceId;

/**
 * PSR-14 event: A command is about to execute.
 *
 * Dispatched by EventMiddleware; listeners correlate it with the run's log
 * records and span through `$traceId`.
 *
 *   $dispatcher->dispatch(new CommandStarted(commandName: 'deploy', traceId: TraceId::generate()));
 *
 * @package KaririCode\Console\Event
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.5.0
 */
final readonly class CommandStarted
{
    public function __construct(
        public string $commandName,
        public TraceId $traceId,
    ) {}
}
