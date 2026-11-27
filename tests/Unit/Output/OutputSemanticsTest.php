<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Output;

use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Formatter\ProgressBar;
use KaririCode\Console\Output\BufferedOutput;
use KaririCode\Console\Output\ConsoleOutput;
use KaririCode\Console\Output\NullOutput;
use KaririCode\Console\Output\StreamOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;

#[CoversClass(BufferedOutput::class)]
#[CoversClass(StreamOutput::class)]
#[CoversClass(ConsoleOutput::class)]
#[CoversClass(NullOutput::class)]
#[CoversClass(ProgressBar::class)]
final class OutputSemanticsTest extends TestCase
{
    /** Regression: BufferedOutput stored raw markup that users never see. */
    public function testBufferedOutputHoldsWhatTheUserWouldSee(): void
    {
        $plain = new BufferedOutput();
        $plain->write('<info>ok</info>');

        $color = new BufferedOutput(decorated: true);
        $color->write('<info>ok</info>');

        self::assertSame('ok', $plain->buffer);
        self::assertSame("\e[32mok\e[0m", $color->buffer);
    }

    /** Regression: BufferedOutput::newLine() printed even in --quiet mode. */
    public function testNewLineHonoursVerbosityAndZeroCount(): void
    {
        $quiet = new BufferedOutput(Verbosity::Quiet);
        $quiet->newLine(3);

        $normal = new BufferedOutput();
        $normal->newLine(0);
        $normal->newLine(-2);

        self::assertSame('', $quiet->buffer);
        self::assertSame('', $normal->buffer);
    }

    public function testStreamOutputWritesFormattedTextToItsStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        $output = new StreamOutput($stream);
        $output->writeln('<comment>hi</comment>');
        $output->newLine(0);
        rewind($stream);

        self::assertFalse($output->decorated, 'a memory stream is not a TTY');
        self::assertSame("hi\n", stream_get_contents($stream));
        self::assertSame($output, $output->errorOutput);
    }

    public function testStreamOutputRejectsNonStreams(): void
    {
        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9007);

        new StreamOutput('php://stdout');
    }

    public function testConsoleOutputKeepsStderrSeparateAndInSync(): void
    {
        $output = new ConsoleOutput(decorated: false);
        $output->verbosity = Verbosity::Debug;

        self::assertNotSame($output, $output->errorOutput);
        self::assertSame(Verbosity::Debug, $output->errorOutput->verbosity);
        self::assertFalse($output->errorOutput->decorated);
    }

    public function testNullOutputIgnoresSettings(): void
    {
        $output = new NullOutput();
        $output->verbosity = Verbosity::Debug;
        $output->decorated = true;

        self::assertSame(Verbosity::Quiet, $output->verbosity);
        self::assertFalse($output->decorated);
        self::assertSame($output, $output->errorOutput);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function terminalEnvironments(): iterable
    {
        yield 'plain terminal'      => [['TERM' => 'xterm'], 'color'];
        yield 'NO_COLOR set'        => [['TERM' => 'xterm', 'NO_COLOR' => '1'], 'plain'];
        yield 'NO_COLOR empty'      => [['TERM' => 'xterm', 'NO_COLOR' => ''], 'color'];
        yield 'dumb terminal'       => [['TERM' => 'dumb'], 'plain'];
    }

    /**
     * Runs a child PHP whose stdout is a real pseudo-terminal, since colour
     * detection only matters on a TTY (https://no-color.org).
     *
     * @param array<string, string> $env
     */
    #[DataProvider('terminalEnvironments')]
    #[RequiresOperatingSystemFamily('Linux')]
    public function testColourDetectionOnARealTerminal(array $env, string $expected): void
    {
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $code     = 'require ' . var_export($autoload, true) . ';'
            . 'echo (new KaririCode\Console\Output\ConsoleOutput())->decorated ? "color" : "plain";';

        $process = @proc_open([PHP_BINARY, '-r', $code], [1 => ['pty'], 2 => ['pty']], $pipes, null, $env);

        if (!is_resource($process)) {
            self::markTestSkipped('This PHP build cannot allocate a pseudo-terminal.');
        }

        $seen = (string) @stream_get_contents($pipes[1]);
        proc_close($process);

        self::assertStringContainsString($expected, $seen);
    }

    // ── ProgressBar ──────────────────────────────────────────────────────

    /** Regression: a negative step made str_repeat() throw ValueError. */
    public function testProgressBarClampsNegativeStepsAndPadsPercentOnTheLeft(): void
    {
        $output = new BufferedOutput(decorated: true);
        $bar    = new ProgressBar($output, total: 20, barWidth: 4);

        $bar->advance(-5);
        $bar->advance();

        self::assertSame(1, $bar->current);
        self::assertStringEndsWith('[░░░░]   5% 1/20', $output->buffer);
    }

    /** Regression: on a log every advance() packed a `\r` redraw into one line. */
    public function testProgressBarWritesOneLinePerTenPercentOnUndecoratedOutput(): void
    {
        $log = new BufferedOutput();
        $bar = new ProgressBar($log, total: 50, barWidth: 10);

        $bar->start();
        for ($i = 0; $i < 50; $i++) {
            $bar->advance();
        }
        $bar->finish();

        self::assertStringNotContainsString("\r", $log->buffer);
        self::assertSame(11, substr_count($log->buffer, "\n"), '0%, 10%, …, 100%');
        self::assertStringEndsWith("[██████████] 100% 50/50\n", $log->buffer);
    }

    public function testProgressBarFinishIsIdempotentAndSpinnerReportsOnce(): void
    {
        $tty = new BufferedOutput(decorated: true);
        $bar = new ProgressBar($tty, total: 2, barWidth: 2);
        $bar->finish();
        $bar->finish();
        $bar->advance();

        $log     = new BufferedOutput();
        $spinner = new ProgressBar($log);
        $spinner->advance(7);
        $spinner->finish();

        self::assertSame(1, substr_count($tty->buffer, "\n"));
        self::assertSame("✓ 7 items processed\n", $log->buffer);
    }

    public function testProgressBarRejectsImpossibleDimensions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProgressBar(new BufferedOutput(), total: 10, barWidth: 0);
    }

    /** Regression: fwrite() failures were not surfaced consistently. */
    public function testStreamOutputReportsFailedWrites(): void
    {
        $readOnly = fopen('php://memory', 'r');
        self::assertIsResource($readOnly);

        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9007);

        (new StreamOutput($readOnly))->write('x');
    }
}
