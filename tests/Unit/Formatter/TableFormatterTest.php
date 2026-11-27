<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Formatter;

use KaririCode\Console\Formatter\TableFormatter;
use KaririCode\Console\Output\BufferedOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableFormatter::class)]
final class TableFormatterTest extends TestCase
{
    /** @return list<string> non-empty rendered lines */
    private function renderLines(TableFormatter $table, BufferedOutput $output): array
    {
        $table->render();

        return array_values(array_filter(
            explode("\n", $output->buffer),
            static fn(string $line): bool => $line !== '',
        ));
    }

    public function testRendersHeadersAndRows(): void
    {
        $output = new BufferedOutput();
        $table  = (new TableFormatter($output))
            ->setHeaders(['Command', 'Description'])
            ->addRow(['list', 'List commands']);

        $table->render();
        $buffer = $output->buffer;

        self::assertStringContainsString('Command', $buffer);
        self::assertStringContainsString('list', $buffer);
        self::assertStringContainsString('List commands', $buffer);
    }

    public function testEmptyTableRendersNothing(): void
    {
        $output = new BufferedOutput();
        (new TableFormatter($output))->render();

        self::assertSame('', $output->buffer);
    }

    public function testColumnWidthMatchesWidestCell(): void
    {
        $output = new BufferedOutput();
        $table  = (new TableFormatter($output))
            ->setHeaders(['X'])
            ->addRow(['short'])
            ->addRow(['a-much-longer-value']);

        $lines  = $this->renderLines($table, $output);
        $widths = array_map('strlen', $lines);

        // Every border/row line is the same byte width for pure-ASCII content.
        self::assertCount(1, array_unique($widths));
    }

    public function testMultiByteColumnsStayAligned(): void
    {
        // Regression guard for mb_strwidth/mb_str_pad: CJK glyphs occupy 2
        // display columns each. With strlen()/str_pad() the border and the
        // content row would diverge in display width.
        $output = new BufferedOutput();
        $table  = (new TableFormatter($output))
            ->setHeaders(['City'])
            ->addRow(['東京'])
            ->addRow(['SP']);

        $lines  = $this->renderLines($table, $output);
        $widths = array_map(mb_strwidth(...), $lines);

        self::assertCount(
            1,
            array_unique($widths),
            'all table lines must share one display width regardless of multi-byte content',
        );
    }

    /**
     * Regression: widths were measured on the raw markup, so a cell like
     * "Uses <b>markup</b>" rendered 7 columns short of its border.
     */
    public function testCellsWithMarkupOrAnsiStayAligned(): void
    {
        foreach ([false, true] as $decorated) {
            $output = new BufferedOutput(decorated: $decorated);

            (new TableFormatter($output))
                ->setHeaders(['Command', 'Description'])
                ->addRow(['tagged', 'Uses <b>markup</b> & <file>'])
                ->addRow(['東京', "\e[31mraw\e[0m ansi"])
                ->render();

            $widths = array_map(
                static fn(string $line): int => mb_strwidth((string) preg_replace('/\e\[[0-9;]*m/', '', $line)),
                array_values(array_filter(explode("\n", $output->buffer))),
            );

            self::assertCount(1, array_unique($widths), 'every line shares one visible width');
        }
    }
}
