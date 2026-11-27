<?php

declare(strict_types=1);

namespace KaririCode\Console\Core;

use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Contract\CommandMiddleware;
use KaririCode\Console\ValueObject\CommandResult;

/**
 * Composes a list of middleware layers around a terminal executor.
 *
 * Middleware is applied in registration order (first registered = outermost).
 * The terminal callable produces a CommandResult from the context directly,
 * without going through another middleware.
 *
 * Composition uses array_reduce to build a single callable; no recursion,
 * no stack overflow risk regardless of middleware count.
 *
 *   $pipeline = (new MiddlewarePipeline())->pipe(new TimingMiddleware());
 *   $result   = $pipeline->run($context, $terminal);
 *
 * @package KaririCode\Console\Core
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
final class MiddlewarePipeline
{
    /** @var list<CommandMiddleware> */
    private array $layers = [];

    // ── Builder ──────────────────────────────────────────────────────────

    public function pipe(CommandMiddleware $middleware): self
    {
        $clone = clone $this;
        $clone->layers[] = $middleware;
        return $clone;
    }

    // ── Execution ────────────────────────────────────────────────────────

    /**
     * Runs the context through all middleware layers, then calls $terminal.
     *
     * @param callable(CommandContext): CommandResult $terminal
     */
    public function run(CommandContext $context, callable $terminal): CommandResult
    {
        /** @var \Closure(CommandContext): CommandResult $pipeline */
        $pipeline = array_reduce(
            array_reverse($this->layers),
            /** @param \Closure(CommandContext): CommandResult $carry */
            callback: static fn(\Closure $carry, CommandMiddleware $layer): \Closure
                => static fn(CommandContext $ctx): CommandResult => $layer->handle($ctx, $carry),
            initial: $terminal(...),
        );

        return $pipeline($context);
    }

    public function isEmpty(): bool
    {
        return empty($this->layers);
    }
}
