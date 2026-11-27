<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Formatter;

use KaririCode\Console\Formatter\AnsiFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnsiFormatter::class)]
final class AnsiFormatterTest extends TestCase
{
    public function testAppliesKnownTagsWhenDecorated(): void
    {
        self::assertSame("\e[32mok\e[0m", (new AnsiFormatter(decorated: true))->format('<info>ok</info>'));
    }

    public function testStripsKnownTagsWhenNotDecorated(): void
    {
        self::assertSame('ok and error', (new AnsiFormatter(decorated: false))->format('<info>ok</info> and <e>error</e>'));
    }

    /** @return iterable<string, array{string}> */
    public static function textThatIsNotMarkup(): iterable
    {
        yield 'usage synopsis' => ['usage: greet <name> [<greeting>]'];
        yield 'comparison'     => ['a < b > c'];
        yield 'generic type'   => ['expects list<string>'];
        yield 'email'          => ['Walmir <walmir@example.org>'];
        yield 'unknown tag'    => ['<unknown>plain</unknown>'];
    }

    /** Regression: the 4.2 formatter deleted every <…> it did not know. */
    #[DataProvider('textThatIsNotMarkup')]
    public function testLeavesNonMarkupUntouchedInBothModes(string $text): void
    {
        self::assertSame($text, (new AnsiFormatter(decorated: true))->format($text));
        self::assertSame($text, (new AnsiFormatter(decorated: false))->format($text));
    }

    public function testClosingAnInnerStyleRestoresTheOuterOne(): void
    {
        self::assertSame(
            "\e[1mbold \e[32mgreen\e[0m\e[1m still bold\e[0m",
            (new AnsiFormatter(decorated: true))->format('<b>bold <info>green</info> still bold</b>'),
        );
    }

    public function testUnclosedStyleIsResetAtEndOfMessage(): void
    {
        self::assertSame("\e[33mno close\e[0m", (new AnsiFormatter(decorated: true))->format('<comment>no close'));
    }

    public function testUnbalancedClosingTagStaysVisible(): void
    {
        self::assertSame('oops</info>', (new AnsiFormatter(decorated: true))->format('oops</info>'));
    }

    #[Group('security')]
    public function testEscapedTextRendersLiterallyInsideMarkup(): void
    {
        $formatter = new AnsiFormatter(decorated: false);
        $hostile   = 'name</e><info>forged';

        self::assertSame('Error: name</e><info>forged', $formatter->format('<e>Error: ' . AnsiFormatter::escape($hostile) . '</e>'));
    }

    #[Group('security')]
    public function testEscapeNeutralisesTerminalControlSequences(): void
    {
        $escaped = AnsiFormatter::escape("clear\e[2J\rspoof\x07bell\u{009B}csi\ttab\nline");

        self::assertSame("clear^[[2J^Mspoof^Gbell\u{FFFD}csi\ttab\nline", $escaped);
        self::assertDoesNotMatchRegularExpression('/[\x00-\x08\x0B-\x1F\x7F]/', $escaped);
    }

    #[Group('security')]
    public function testEscapeScrubsInvalidUtf8(): void
    {
        self::assertTrue(mb_check_encoding(AnsiFormatter::escape("bad \xC3\x28 byte"), 'UTF-8'));
    }

    public function testEscapePreservesAnExistingBackslashBeforeLessThan(): void
    {
        self::assertSame('C:\\<dir>', (new AnsiFormatter(decorated: false))->format(AnsiFormatter::escape('C:\\<dir>')));
    }

    public function testDecoratedIsAPublicMutableSetting(): void
    {
        $formatter = new AnsiFormatter(decorated: true);
        $formatter->decorated = false;

        self::assertSame('x', $formatter->format('<info>x</info>'));
    }
}
