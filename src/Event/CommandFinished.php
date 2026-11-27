<?php

declare(strict_types=1);

namespace KaririCode\Console\Event;

use KaririCode\Console\ValueObject\CommandResult;
use KaririCode\Console\ValueObject\TraceId;

/**
 * PSR-14 event: A command returned a result, successful or not; `$result->isSuccess()`
 * tells which, and `$result->durationMs` how long `execute()` took.
 *
 * Dispatched by EventMiddleware; listeners correlate it with the run's log
 * records and span through `$traceId`.
 *
 *   $dispatcher->dispatch(new CommandFinished(result: CommandResult::success('deploy'), traceId: TraceId::generate()));
 *
 * @package KaririCode\Console\Event
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.5.0
 */
final readonly class CommandFinished
{
    public function __construct(
        public CommandResult $result,
        public TraceId $traceId,
    ) {}
}
