<?php

declare(strict_types=1);

namespace KaririCode\Console\Middleware;

use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Contract\CommandMiddleware;
use KaririCode\Console\Contract\Signalable;
use KaririCode\Console\Enum\Signal;
use KaririCode\Console\ValueObject\CommandResult;

/**
 * Delivers POSIX signals to Signalable commands while they execute.
 *
 * For each subscribed signal the middleware:
 *   1. enables asynchronous delivery (pcntl_async_signals) — without it PHP
 *      only queues signals, handlers never run, and SIGTERM/SIGINT are
 *      silently swallowed for the whole execution;
 *   2. saves the previous disposition (pcntl_signal_get_handler) and
 *      installs a handler that calls Signalable::handleSignal();
 *   3. restores every previous disposition and the async setting in a
 *      finally block, so handlers installed by the host application
 *      survive the command.
 *
 * A handler returning false means "not handled", and the signal gets the
 * treatment it would have had without the command:
 *   - previous handler is a PHP callable → it is invoked directly (PHP
 *     blocks all signals while dispatching, so a re-raise would be lost);
 *   - previous disposition is SIG_IGN    → the signal is ignored;
 *   - previous disposition is SIG_DFL    → SIG_DFL is restored and the
 *     signal re-raised; it is delivered once the dispatcher unblocks
 *     signals and terminates the process (GNU C Library manual §24.4.2).
 *
 * Mirrors Symfony Console's SignalRegistry (async + previous handler).
 * No-op when PCNTL is unavailable (Windows, disabled functions).
 *
 * Side effects: changes process-wide signal dispositions and the async-signals
 *               flag while execute() runs; a declined signal may terminate the process.
 *
 * @package KaririCode\Console\Middleware
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
final readonly class SignalMiddleware implements CommandMiddleware
{
    #[\Override]
    public function handle(CommandContext $context, callable $next): CommandResult
    {
        $command = $context->command;

        if (!$command instanceof Signalable || $command->subscribedSignals === [] || !Signal::isSupported()) {
            return $next($context);
        }

        $wasAsync = pcntl_async_signals(enable: true);

        /** @var array<int, (callable(int, mixed=): void)|int> $previous signo => disposition to restore */
        $previous = [];

        /** @var list<Signal> $signals Signalable::$subscribedSignals (interface property). */

        $signals = $command->subscribedSignals;


        foreach ($signals as $signal) {
            $signo            = $signal->pcntlConstant();
            /** @var (callable(int, mixed=): void)|int $handler Documented return type; stubs disagree across analysers. */
            $handler          = pcntl_signal_get_handler($signo); // @phpstan-ignore varTag.nativeType (stubs disagree; Psalm needs the handler signature pcntl_signal() declares)
            $previous[$signo] = $handler;

            pcntl_signal($signo, self::handlerFor(command: $command, signal: $signal, previous: $previous[$signo]));
        }

        try {
            return $next($context);
        } finally {
            foreach ($previous as $signo => $disposition) {
                pcntl_signal($signo, $disposition);
            }

            pcntl_async_signals($wasAsync);
        }
    }

    /**
     * @param (callable(int, mixed=): void)|int $previous Disposition in force before the command subscribed.
     *
     * @return \Closure(int, mixed=): void
     */
    private static function handlerFor(Signalable $command, Signal $signal, callable|int $previous): \Closure
    {
        return static function (int $signo, mixed $info = null) use ($command, $signal, $previous): void {
            if ($command->handleSignal($signal)) {
                return;
            }

            if (is_callable($previous)) {
                $previous($signo, $info);
                return;
            }

            if ($previous === SIG_IGN) {
                return;
            }

            pcntl_signal($signo, SIG_DFL);

            $pid = getmypid();

            // Never posix_kill(0, …): pid 0 signals the whole process group.
            if ($pid !== false && \function_exists('posix_kill')) {
                posix_kill($pid, $signo);
                return;
            }

            exit(128 + $signo); // shell convention for "terminated by signal N"
        };
    }
}
