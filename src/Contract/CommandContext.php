<?php

declare(strict_types=1);

namespace KaririCode\Console\Contract;

use KaririCode\Console\ValueObject\TraceId;

/**
 * Immutable dispatch context handed to every middleware layer.
 *
 * Carries the resolved command and its already-parsed I/O so middleware can
 * observe or short-circuit execution without re-resolving anything.
 *
 * @package KaririCode\Console\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
interface CommandContext
{
    /** Name the command was invoked by (may be an alias). */
    public string $commandName { get; }

    public Input $input { get; }

    public Output $output { get; }

    public Command $command { get; }

    /** Correlates the run's log records, events and span (E-021). */
    public TraceId $traceId { get; }
}
