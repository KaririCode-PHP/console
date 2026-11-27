<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Fixture;

use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Output\BufferedOutput;

/**
 * Output with separate stdout/stderr buffers, like ConsoleOutput.
 */
final class SplitOutput implements Output
{
    public readonly BufferedOutput $stdout;

    public readonly BufferedOutput $stderr;

    public Output $errorOutput {
        get => $this->stderr;
    }

    public Verbosity $verbosity {
        get => $this->stdout->verbosity;
        set {
            $this->stdout->verbosity = $value;
            $this->stderr->verbosity = $value;
        }
    }

    public bool $decorated {
        get => false;
        set {
        }
    }

    public function __construct(Verbosity $verbosity = Verbosity::Normal)
    {
        $this->stdout = new BufferedOutput($verbosity);
        $this->stderr = new BufferedOutput($verbosity);
    }

    public function write(string $message, Verbosity $verbosity = Verbosity::Normal): void
    {
        $this->stdout->write($message, $verbosity);
    }

    public function writeln(string $message = '', Verbosity $verbosity = Verbosity::Normal): void
    {
        $this->stdout->writeln($message, $verbosity);
    }

    public function newLine(int $count = 1): void
    {
        $this->stdout->newLine($count);
    }
}
