<?php

declare(strict_types=1);

namespace KaririCode\Console\Enum;

/**
 * Diagnostic severity level.
 *
 * Maps to LSP DiagnosticSeverity (RFC 7234) and GitHub Actions
 * annotation levels so DiagnosticOutput can emit in any format
 * without branching at the call site.
 *
 * @package KaririCode\Console\Enum
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.2.0
 */
enum Severity
{
    case Error;
    case Warning;
    case Info;
    case Hint;

    // ── Labels ───────────────────────────────────────────────────────────

    public function label(): string
    {
        return match ($this) {
            self::Error   => 'ERROR',
            self::Warning => 'WARN ',
            self::Info    => 'INFO ',
            self::Hint    => 'HINT ',
        };
    }

    /** ANSI colour prefix for human output (reset appended by caller). */
    public function ansiColor(): string
    {
        return match ($this) {
            self::Error   => "\033[1;31m",
            self::Warning => "\033[1;33m",
            self::Info    => "\033[1;36m",
            self::Hint    => "\033[0;37m",
        };
    }

    /** GitHub Actions annotation command string. */
    public function githubAnnotation(): string
    {
        return match ($this) {
            self::Error   => 'error',
            self::Warning => 'warning',
            self::Info    => 'notice',
            self::Hint    => 'notice',
        };
    }

    public function isAtLeast(self $minimum): bool
    {
        return $this->ordinal() <= $minimum->ordinal();
    }

    private function ordinal(): int
    {
        return match ($this) {
            self::Error   => 0,
            self::Warning => 1,
            self::Info    => 2,
            self::Hint    => 3,
        };
    }
}
