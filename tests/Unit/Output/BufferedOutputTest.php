<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Output;

use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Output\BufferedOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BufferedOutput::class)]
final class BufferedOutputTest extends TestCase
{
    public function testWritelnAppendsNewline(): void
    {
        $output = new BufferedOutput();
        $output->writeln('hello');

        self::assertSame("hello\n", $output->buffer);
    }

    public function testFetchClearsBuffer(): void
    {
        $output = new BufferedOutput();
        $output->writeln('data');

        $content = $output->fetch();

        self::assertSame("data\n", $content);
        self::assertSame('', $output->buffer);
    }

    public function testVerbosityGateSuppressesLowerPriorityMessages(): void
    {
        $output = new BufferedOutput(Verbosity::Normal);
        $output->writeln('verbose detail', Verbosity::Verbose);

        self::assertSame('', $output->buffer);
    }

    public function testVerbosityGatePassesEqualLevel(): void
    {
        $output = new BufferedOutput(Verbosity::Verbose);
        $output->writeln('verbose detail', Verbosity::Verbose);

        self::assertStringContainsString('verbose detail', $output->buffer);
    }

    public function testNewLineAddsCorrectNumberOfLineBreaks(): void
    {
        $output = new BufferedOutput();
        $output->newLine(3);

        self::assertSame("\n\n\n", $output->buffer);
    }

    public function testClearEmptiesBuffer(): void
    {
        $output = new BufferedOutput();
        $output->write('something');
        $output->clear();

        self::assertSame('', $output->buffer);
    }
}
