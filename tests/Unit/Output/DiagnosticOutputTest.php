<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Output;

use KaririCode\Console\Enum\DiagnosticFormat;
use KaririCode\Console\Enum\Severity;
use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Output\BufferedOutput;
use KaririCode\Console\Output\DiagnosticOutput;
use KaririCode\Console\ValueObject\SourceLocation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiagnosticOutput::class)]
#[CoversClass(DiagnosticFormat::class)]
#[CoversClass(SourceLocation::class)]
final class DiagnosticOutputTest extends TestCase
{
    public function testHumanFormatWritesLocationLabelAndMessage(): void
    {
        $inner = new BufferedOutput();

        (new DiagnosticOutput($inner))->diagnostic(Severity::Error, 'Missing strict_types', new SourceLocation('src/Foo.php', 3, 1));

        self::assertSame("src/Foo.php:3:1: ERROR Missing strict_types\n", $inner->buffer);
    }

    #[Group('security')]
    public function testHumanFormatNeutralisesMarkupAndEscapeSequences(): void
    {
        $inner = new BufferedOutput();

        (new DiagnosticOutput($inner))->diagnostic(Severity::Warning, "<e>fake</e> \e[2J");

        self::assertStringContainsString('<e>fake</e> ^[[2J', $inner->buffer);
        self::assertStringNotContainsString("\e", $inner->buffer);
    }

    public function testGithubFormatEmitsAnnotation(): void
    {
        $inner = new BufferedOutput();

        (new DiagnosticOutput($inner, DiagnosticFormat::GitHub))
            ->diagnostic(Severity::Warning, 'Deprecated call', new SourceLocation('src/Bar.php', 10, 5));

        self::assertSame("::warning file=src/Bar.php,line=10,col=5::Deprecated call\n", $inner->buffer);
    }

    /**
     * A newline in the payload would start a second, attacker-controlled
     * workflow command (the CVE-2020-15228 class of injection).
     */
    #[Group('security')]
    public function testGithubFormatCannotInjectWorkflowCommands(): void
    {
        $inner = new BufferedOutput();

        (new DiagnosticOutput($inner, DiagnosticFormat::GitHub))->diagnostic(
            Severity::Error,
            "boom\n::add-mask::secret\r100%",
            new SourceLocation('a,b:c.php', 1),
        );

        self::assertSame("::error file=a%2Cb%3Ac.php,line=1::boom%0A::add-mask::secret%0D100%25\n", $inner->buffer);
        self::assertSame(1, substr_count($inner->buffer, "\n"), 'exactly one workflow command line');
    }

    public function testJsonFormatBuffersUntilFlush(): void
    {
        $inner       = new BufferedOutput();
        $diagnostics = new DiagnosticOutput($inner, DiagnosticFormat::Json);

        $diagnostics->diagnostic(Severity::Error, 'E1', new SourceLocation('a.php', 1));
        $diagnostics->diagnostic(Severity::Hint, 'H1');

        self::assertSame('', $inner->buffer);
        self::assertSame(2, $diagnostics->countBuffered());

        $diagnostics->flushDiagnostics();

        self::assertSame(
            [['severity' => 'error', 'message' => 'E1', 'file' => 'a.php', 'line' => 1], ['severity' => 'hint', 'message' => 'H1']],
            json_decode($inner->buffer, true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertSame(0, $diagnostics->countBuffered());
    }

    public function testJsonFormatSurvivesInvalidUtf8AndMarkup(): void
    {
        $inner       = new BufferedOutput();
        $diagnostics = new DiagnosticOutput($inner, DiagnosticFormat::Json);

        $diagnostics->diagnostic(Severity::Error, "bad \xC3\x28 <info>x</info>");
        $diagnostics->flushDiagnostics();

        /** @var list<array{message: string}> $decoded */
        $decoded = json_decode($inner->buffer, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame("bad \u{FFFD}( <info>x</info>", $decoded[0]['message'] ?? null);
    }

    public function testFlushIsNoOpForNonJsonFormats(): void
    {
        $inner = new BufferedOutput();

        (new DiagnosticOutput($inner, DiagnosticFormat::Human))->flushDiagnostics();

        self::assertSame('', $inner->buffer);
    }

    public function testStandardWritesAndSettingsDelegateToInner(): void
    {
        $inner       = new BufferedOutput();
        $diagnostics = new DiagnosticOutput($inner);

        $diagnostics->writeln('plain');
        $diagnostics->verbosity = Verbosity::Quiet;

        self::assertSame("plain\n", $inner->buffer);
        self::assertSame(Verbosity::Quiet, $inner->verbosity);
        self::assertSame($inner, $diagnostics->errorOutput);
    }

    public function testFormatParsingFailsClosed(): void
    {
        self::assertSame(DiagnosticFormat::GitHub, DiagnosticFormat::parse(' GitHub '));
        self::assertNull(DiagnosticFormat::parse('sarif'));
    }

    public function testSourceLocationRejectsInvalidPositions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SourceLocation('a.php', null, 4);
    }
}
