<?php

declare(strict_types=1);

namespace KaririCode\Console\Enum;

/**
 * Rendering format for commands that support multiple output modes.
 *
 * Pure enum (Spec v4.3.3 §10: pure by default; E-003 reserves backing for external contracts).
 *
 * @package KaririCode\Console\Enum
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
enum OutputFormat
{
    case Table;
    case Json;
    case Plain;

    // ── Parsing ──────────────────────────────────────────────────────────

    /**
     * Parses a user-supplied format name; null when unrecognised, so callers
     * reject typos (fail closed) instead of silently falling back to Table.
     */
    public static function parse(string $value): ?self
    {
        return match (strtolower(trim($value))) {
            'table' => self::Table,
            'json'  => self::Json,
            'plain' => self::Plain,
            default => null,
        };
    }
}
