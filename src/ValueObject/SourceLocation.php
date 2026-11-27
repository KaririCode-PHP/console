<?php

declare(strict_types=1);

namespace KaririCode\Console\ValueObject;

/**
 * Position of a diagnostic in a source file (1-based line and column).
 *
 *   $diagnostics->diagnostic(Severity::Error, 'Missing strict_types', new SourceLocation('src/Foo.php', line: 3));
 *
 * @package KaririCode\Console\ValueObject
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.3.0
 */
final readonly class SourceLocation
{
    /**
     * @throws \InvalidArgumentException when line/column are not 1-based, or a
     *         column is given without a line
     */
    public function __construct(
        public string $file,
        public ?int $line = null,
        public ?int $column = null,
    ) {
        if ($line !== null && $line < 1) {
            throw new \InvalidArgumentException("Line must be >= 1, got {$line}.");
        }

        if ($column !== null && ($line === null || $column < 1)) {
            throw new \InvalidArgumentException('Column must be >= 1 and requires a line.');
        }
    }
}
