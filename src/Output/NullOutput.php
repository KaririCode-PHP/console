<?php

declare(strict_types=1);

namespace KaririCode\Console\Output;

use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\Verbosity;

/**
 * Output that discards everything — for non-CLI dispatch and benchmarks.
 *
 * Settings are fixed: verbosity reads as Quiet and decoration as false;
 * assignments are accepted and ignored so callers need no special casing.
 *
 *   $application->run('cache:warm', new ArrayInput(), new NullOutput());
 *
 * @package KaririCode\Console\Output
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class NullOutput implements Output
{
    public Verbosity $verbosity {
        get => Verbosity::Quiet;
        set {
        }
    }

    public bool $decorated {
        get => false;
        set {
        }
    }

    public Output $errorOutput {
        get => $this;
    }

    #[\Override]
    public function write(string $message, Verbosity $verbosity = Verbosity::Normal): void {}

    #[\Override]
    public function writeln(string $message = '', Verbosity $verbosity = Verbosity::Normal): void {}

    #[\Override]
    public function newLine(int $count = 1): void {}
}
