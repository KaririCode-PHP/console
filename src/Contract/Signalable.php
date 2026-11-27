<?php

declare(strict_types=1);

namespace KaririCode\Console\Contract;

use KaririCode\Console\Enum\Signal;

/**
 * Opt-in contract for commands that react to POSIX signals.
 *
 * SignalMiddleware installs asynchronous PCNTL handlers for the subscribed
 * signals while execute() runs and restores the previous dispositions
 * afterwards (ADR-008).
 *
 * handleSignal() semantics:
 *   true  — handled; the command will wind down on its own (typically by
 *           setting a flag its work loop checks) and execution continues.
 *   false — not handled; the signal gets the treatment it would have had
 *           without the command: a previous PHP handler is called, SIG_IGN
 *           ignores it, and SIG_DFL terminates the process by that signal
 *           (GNU libc manual §24.4.2, "Handlers That Terminate the Process").
 *
 * @package KaririCode\Console\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
interface Signalable
{
    /** @var list<Signal> Signals this command wants delivered to handleSignal(). */
    public array $subscribedSignals { get; }

    public function handleSignal(Signal $signal): bool;
}
