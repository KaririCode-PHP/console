<?php

declare(strict_types=1);

namespace KaririCode\Console\Contract;

use KaririCode\Console\Enum\ExitCode;

/**
 * A terminal command: identity, input schema, and executable behaviour.
 *
 * Commands are plain objects — no base class. Identity is exposed as
 * interface properties (PHP 8.4) rather than getName()/getDescription()
 * accessors (Spec v4.3.3 §18: no getters without hooks). Implementations
 * typically satisfy them with virtual get hooks:
 *
 *   public string $name { get => 'greet'; }
 *
 * @package KaririCode\Console\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
interface Command
{
    // ── Identity ─────────────────────────────────────────────────────────

    /** Invocation name used on the command line (e.g. "cache:clear"). */
    public string $name { get; }

    /** One-line summary shown by `list` and `help`. */
    public string $description { get; }

    // ── Lifecycle ────────────────────────────────────────────────────────

    /** Declares accepted arguments and options on the (mutable) definition. */
    public function configure(InputDefinition $definition): void;

    /** Performs the command's work against already-parsed input. */
    public function execute(Input $input, Output $output): ExitCode;
}
