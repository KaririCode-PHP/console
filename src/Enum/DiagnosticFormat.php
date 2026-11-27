<?php

declare(strict_types=1);

namespace KaririCode\Console\Enum;

/**
 * Wire format DiagnosticOutput emits findings in (ADR-006).
 *
 * @package KaririCode\Console\Enum
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.3.0
 */
enum DiagnosticFormat
{
    /** Compiler-style `file:line: SEVERITY message`, coloured when decorated. */
    case Human;

    /** GitHub Actions workflow commands (`::error file=…::message`). */
    case GitHub;

    /** One JSON array, emitted by flushDiagnostics(). */
    case Json;

    /** Parses a user-supplied format name; null when unrecognised (fail closed). */
    public static function parse(string $value): ?self
    {
        return match (strtolower(trim($value))) {
            'human'  => self::Human,
            'github' => self::GitHub,
            'json'   => self::Json,
            default  => null,
        };
    }
}
