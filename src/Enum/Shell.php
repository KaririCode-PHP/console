<?php

declare(strict_types=1);

namespace KaririCode\Console\Enum;

/**
 * Shells CompletionCommand can generate scripts for.
 *
 * @package KaririCode\Console\Enum
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.3.0
 */
enum Shell
{
    case Bash;
    case Zsh;
    case Fish;

    /** Parses a user-supplied shell name; null when unsupported (fail closed). */
    public static function parse(string $value): ?self
    {
        return match (strtolower(trim($value))) {
            'bash'  => self::Bash,
            'zsh'   => self::Zsh,
            'fish'  => self::Fish,
            default => null,
        };
    }
}
