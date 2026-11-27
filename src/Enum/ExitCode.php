<?php

declare(strict_types=1);

namespace KaririCode\Console\Enum;

/**
 * POSIX process exit codes for command results.
 *
 * Pure enum (Spec v4.3.3 §10: pure by default; E-003 reserves backing for external contracts).
 * Integer values are resolved via exitCode() for use in posix_exit().
 *
 * POSIX refs: exit(3), sysexits.h; ARFA 1.3 P1.
 *
 * @package KaririCode\Console\Enum
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
enum ExitCode
{
    /** Command completed without errors. */
    case Success;

    /** Command failed due to a general error. */
    case Failure;

    /**
     * Input was syntactically or semantically invalid.
     * Maps to EX_USAGE (64) in sysexits.h conventions; simplified to 2
     * for shell-script compatibility.
     */
    case Invalid;

    // ── Value accessor ───────────────────────────────────────────────────

    /**
     * Returns the integer code passed to exit() or returned from main().
     */
    public function exitCode(): int
    {
        return match ($this) {
            self::Success => 0,
            self::Failure => 1,
            self::Invalid => 2,
        };
    }

    // ── Factory ──────────────────────────────────────────────────────────

    public static function fromInt(int $code): self
    {
        return match ($code) {
            0       => self::Success,
            2       => self::Invalid,
            default => self::Failure, // any other non-zero status is a failure (POSIX)
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::Success;
    }
}
