<?php

declare(strict_types=1);

namespace KaririCode\Console\Contract;

/**
 * Renders console markup into the representation a sink expects.
 *
 * @package KaririCode\Console\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
interface OutputFormatter
{
    /** Render markup as ANSI sequences (true) or strip it to plain text (false). */
    public bool $decorated { get; set; }

    public function format(string $message): string;
}
