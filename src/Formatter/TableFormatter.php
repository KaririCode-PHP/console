<?php

declare(strict_types=1);

namespace KaririCode\Console\Formatter;

use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\Verbosity;

/**
 * Renders a two-dimensional dataset as an aligned ASCII table.
 *
 * Column widths are calculated from the widest cell in each column.
 * Headers and data rows use the same width computation so the table
 * is always consistent regardless of row count (ARFA 1.3 P1 —
 * immutable state: widths are computed once before rendering begins).
 *
 *   (new TableFormatter($output))
 *       ->setHeaders(['Command', 'Description'])
 *       ->addRow(['deploy', 'Deploys the application'])
 *       ->render();
 *
 * Side effects: render() writes the table to the output.
 *
 * @package KaririCode\Console\Formatter
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class TableFormatter
{
    /** @var list<string> */
    private array $headers = [];

    /** @var list<list<string|int|float|null>> Cells are stringified when rendered. */
    private array $rows = [];

    /** Renders cells without decoration, to measure what the user will see. */
    private readonly AnsiFormatter $plain;

    public function __construct(private readonly Output $output)
    {
        $this->plain = new AnsiFormatter(decorated: false);
    }

    // ── Builder ──────────────────────────────────────────────────────────

    /** @param list<string> $headers */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    /** @param list<string|int|float|null> $row */
    public function addRow(array $row): self
    {
        $this->rows[] = $row;
        return $this;
    }

    // ── Render ───────────────────────────────────────────────────────────

    public function render(): void
    {
        if (empty($this->headers) && empty($this->rows)) {
            return;
        }

        $widths = $this->computeWidths();
        $separator = $this->buildSeparator($widths);

        $this->output->writeln($separator);
        $this->output->writeln($this->buildRow($this->headers, $widths));
        $this->output->writeln($separator);

        foreach ($this->rows as $row) {
            $this->output->writeln($this->buildRow($row, $widths));
        }

        $this->output->writeln($separator);
    }

    // ── Private helpers ──────────────────────────────────────────────────

    /**
     * Columns a cell occupies once printed: markup is measured as rendered
     * (tags removed, `\<` as `<`), raw ANSI SGR sequences count as zero,
     * and wide glyphs (CJK) count as two (mb_strwidth).
     */
    private function displayWidth(string $cell): int
    {
        $visible = (string) preg_replace('/\e\[[0-9;]*m/', replacement: '', subject: $this->plain->format($cell));

        return mb_strwidth($visible);
    }

    /** @return list<int> Display width per column. */
    private function computeWidths(): array
    {
        return array_map(
            fn(int $col): int => max([
                $this->displayWidth($this->headers[$col]),
                ...array_map(fn(array $row): int => $this->displayWidth((string) ($row[$col] ?? '')), $this->rows),
            ]),
            array_keys($this->headers),
        );
    }

    /** @param list<int> $widths */
    private function buildSeparator(array $widths): string
    {
        $parts = array_map(fn(int $w) => str_repeat('-', $w + 2), $widths);
        return '+' . implode('+', $parts) . '+';
    }

    /**
     * @param list<string|int|float|null> $row
     * @param list<int>    $widths
     */
    private function buildRow(array $row, array $widths): string
    {
        $cells = [];

        foreach ($widths as $col => $width) {
            $cell = (string) ($row[$col] ?? '');
            // Pad by display columns, not characters: mb_str_pad() counts
            // code points, so a CJK cell (2 columns per glyph) would overflow.
            $cells[] = ' ' . $cell . str_repeat(' ', max(0, $width - $this->displayWidth($cell))) . ' ';
        }

        return '|' . implode('|', $cells) . '|';
    }
}
