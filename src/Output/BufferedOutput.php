<?php

declare(strict_types=1);

namespace KaririCode\Console\Output;

use KaririCode\Console\Contract\Output;
use KaririCode\Console\Contract\OutputFormatter;
use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Formatter\AnsiFormatter;

/**
 * In-memory output for tests and for capturing command output.
 *
 * Applies the same formatter and verbosity rules as StreamOutput, so the
 * buffer holds exactly what a user would see: markup stripped when not
 * decorated, ANSI sequences when decorated.
 *
 *   $output = new BufferedOutput();
 *   $application->run('greet', new ArrayInput(['name' => 'Ana']), $output);
 *   // $output->buffer === "Hello, Ana!\n"
 *
 * @package KaririCode\Console\Output
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class BufferedOutput implements Output
{
    private readonly OutputFormatter $formatter;

    /** Everything written since construction or the last fetch()/clear(). */
    public private(set) string $buffer = '';

    public bool $decorated {
        get => $this->formatter->decorated;
        set {
            $this->formatter->decorated = $value;
        }
    }

    /** Diagnostics are captured in the same buffer. */
    public Output $errorOutput {
        get => $this;
    }

    public function __construct(
        public Verbosity $verbosity = Verbosity::Normal,
        bool $decorated = false,
        ?OutputFormatter $formatter = null,
    ) {
        $this->formatter            = $formatter ?? new AnsiFormatter();
        $this->formatter->decorated = $decorated;
    }

    #[\Override]
    public function write(string $message, Verbosity $verbosity = Verbosity::Normal): void
    {
        if ($this->verbosity->isAtLeast($verbosity)) {
            $this->buffer .= $this->formatter->format($message);
        }
    }

    #[\Override]
    public function writeln(string $message = '', Verbosity $verbosity = Verbosity::Normal): void
    {
        $this->write($message . "\n", $verbosity);
    }

    #[\Override]
    public function newLine(int $count = 1): void
    {
        if ($count > 0) {
            $this->write(str_repeat("\n", $count));
        }
    }

    /** Returns the buffered text and empties the buffer. */
    public function fetch(): string
    {
        $content      = $this->buffer;
        $this->buffer = '';

        return $content;
    }

    public function clear(): void
    {
        $this->buffer = '';
    }
}
