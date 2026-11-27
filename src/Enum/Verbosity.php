<?php

declare(strict_types=1);

namespace KaririCode\Console\Enum;

/**
 * Output verbosity levels, ordered from least to most verbose.
 *
 * Pure enum (Spec v4.3.3 §10: pure by default; E-003 reserves backing for external contracts).
 * Integer levels are resolved via level() for threshold comparisons.
 *
 * @package KaririCode\Console\Enum
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
enum Verbosity
{
    case Quiet;
    case Normal;
    case Verbose;
    case VeryVerbose;
    case Debug;

    // ── Level accessor ───────────────────────────────────────────────────

    public function level(): int
    {
        return match ($this) {
            self::Quiet       => 0,
            self::Normal      => 1,
            self::Verbose     => 2,
            self::VeryVerbose => 3,
            self::Debug       => 4,
        };
    }

    public function isAtLeast(self $minimum): bool
    {
        return $this->level() >= $minimum->level();
    }

    // ── CLI flag parsing ─────────────────────────────────────────────────

    /**
     * Resolves a verbosity flag to its level.
     *
     * Returns null when the token is not a recognised verbosity flag, so
     * callers can tell "not a verbosity flag" apart from an explicit Normal.
     */
    public static function fromFlag(string $flag): ?self
    {
        return match ($flag) {
            '-q', '--quiet'        => self::Quiet,
            '-v', '--verbose'      => self::Verbose,
            '-vv'                  => self::VeryVerbose,
            '-vvv'                 => self::Debug,
            default                => null,
        };
    }
}
