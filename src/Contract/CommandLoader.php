<?php

declare(strict_types=1);

namespace KaririCode\Console\Contract;

/**
 * Strategy for discovering and loading command classes.
 *
 * Multiple loaders can be chained in CommandRegistry to support
 * attribute scanning, explicit registration, and cached warm-up.
 *
 * @package KaririCode\Console\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
interface CommandLoader
{
    /**
     * Returns a map of command name → FQCN.
     *
     * @return array<string, class-string<Command>>
     */
    public function load(): array;

    /**
     * Whether the loader can produce commands without external I/O.
     * Used by Application to decide whether to warm up a cache.
     */
    public function isWarm(): bool;
}
