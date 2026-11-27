<?php

declare(strict_types=1);

namespace KaririCode\Console\Middleware;

use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Contract\CommandMiddleware;
use KaririCode\Console\ValueObject\CommandResult;

/**
 * Wraps command execution in an hrtime() bracket and injects durationMs.
 *
 * It measures everything inside its position in the pipeline. Registered
 * innermost (as ConsoleServiceProvider does), that is Command::execute()
 * alone, so every outer layer — logs, events, metrics — sees the same
 * execute-only duration instead of Application's run()-wide estimate.
 *
 *   $application->addMiddleware(new TimingMiddleware()); // last added = innermost
 *
 * @package KaririCode\Console\Middleware
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
final readonly class TimingMiddleware implements CommandMiddleware
{
    #[\Override]
    public function handle(CommandContext $context, callable $next): CommandResult
    {
        $startNs = hrtime(as_number: true);

        $result = $next($context);

        $durationMs = (hrtime(as_number: true) - $startNs) / 1_000_000;

        return new CommandResult(
            exitCode: $result->exitCode,
            commandName: $result->commandName,
            errorMessage: $result->errorMessage,
            durationMs: $durationMs,
        );
    }
}
