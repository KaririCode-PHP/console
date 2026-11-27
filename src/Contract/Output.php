<?php

declare(strict_types=1);

namespace KaririCode\Console\Contract;

use KaririCode\Console\Enum\Verbosity;

/**
 * Write-only sink for command output, with verbosity gating and markup.
 *
 * Messages may contain formatter markup (<info>, <e>, …). Untrusted text
 * must be passed through AnsiFormatter::escape() before being embedded in
 * markup (Spec v4.3.3 §20 — output is encoded for its sink).
 *
 * @package KaririCode\Console\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
interface Output
{
    /** Messages whose verbosity exceeds this level are discarded. */
    public Verbosity $verbosity { get; set; }

    /** Whether markup is rendered as ANSI sequences (true) or stripped (false). */
    public bool $decorated { get; set; }

    /**
     * Sink for diagnostics. ConsoleOutput routes it to STDERR so errors never
     * pollute piped stdout; single-stream outputs return themselves.
     */
    public Output $errorOutput { get; }

    public function write(string $message, Verbosity $verbosity = Verbosity::Normal): void;

    public function writeln(string $message = '', Verbosity $verbosity = Verbosity::Normal): void;

    /** Writes $count line breaks (none when $count <= 0), gated like write(). */
    public function newLine(int $count = 1): void;
}
