<?php

declare(strict_types=1);

namespace KaririCode\Console\Contract;

use KaririCode\Console\ValueObject\CommandResult;

/**
 * Cross-cutting behaviour applied to every command in the pipeline.
 *
 * Middleware wraps Application::run() dispatch. Each middleware receives
 * a CommandContext and a $next callable pointing to the inner middleware
 * or the final command execution. Returning early (without calling $next)
 * short-circuits the pipeline.
 *
 * Built-in implementations:
 *   TimingMiddleware    — injects durationMs into CommandResult
 *   LoggingMiddleware   — emits structured PSR-3 log records
 *   SignalMiddleware    — installs PCNTL handlers before execute()
 *
 * This is a first-class feature that Symfony Console and Laravel Artisan
 * do not offer: they expose global event subscribers instead, which
 * cannot short-circuit execution.
 *
 * @package KaririCode\Console\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
interface CommandMiddleware
{
    /**
     * @param callable(CommandContext): CommandResult $next
     */
    public function handle(CommandContext $context, callable $next): CommandResult;
}
