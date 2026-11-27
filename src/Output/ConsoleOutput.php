<?php

declare(strict_types=1);

namespace KaririCode\Console\Output;

use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Exception\ConsoleException;

/**
 * Default CLI output: regular text to STDOUT, diagnostics to STDERR.
 *
 * Composes two StreamOutputs instead of extending one (composition over
 * inheritance — Spec v4.3.3 §18). Keeping errors on STDERR means
 * `app list --format=json | jq` never receives an error message as JSON
 * input (POSIX: diagnostics belong on standard error).
 *
 *   $output = new ConsoleOutput();
 *   $output->writeln('<info>done</info>');
 *   $output->errorOutput->writeln('<e>failed</e>');
 *
 * Side effects: writes to STDOUT and STDERR.
 *
 * @package KaririCode\Console\Output
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class ConsoleOutput implements Output
{
    private readonly StreamOutput $stdout;

    /** STDERR sink; verbosity and decoration are kept in sync with stdout. */
    public readonly Output $errorOutput;

    public Verbosity $verbosity {
        get => $this->stdout->verbosity;
        set {
            $this->stdout->verbosity      = $value;
            $this->errorOutput->verbosity = $value;
        }
    }

    public bool $decorated {
        get => $this->stdout->decorated;
        set {
            $this->stdout->decorated      = $value;
            $this->errorOutput->decorated = $value;
        }
    }

    /**
     * @param bool|null $decorated Null auto-detects per stream (TTY, NO_COLOR).
     */
    public function __construct(Verbosity $verbosity = Verbosity::Normal, ?bool $decorated = null)
    {
        $this->stdout      = new StreamOutput(self::openStream('STDOUT', 'php://stdout'), $verbosity);
        $this->errorOutput = new StreamOutput(self::openStream('STDERR', 'php://stderr'), $verbosity);

        if ($decorated !== null) {
            $this->decorated = $decorated;
        }
    }

    /**
     * The CLI SAPI predefines STDOUT/STDERR; other SAPIs must open them.
     *
     * @return resource
     *
     * @throws ConsoleException 9007 when the stream cannot be opened
     */
    private static function openStream(string $constant, string $url): mixed
    {
        /** @var mixed $stream constant() is untyped; checked below. */
        $stream = \defined($constant) ? \constant($constant) : @fopen($url, 'w');

        return is_resource($stream) ? $stream : throw ConsoleException::outputWriteFailure("cannot open {$url}");
    }

    #[\Override]
    public function write(string $message, Verbosity $verbosity = Verbosity::Normal): void
    {
        $this->stdout->write($message, $verbosity);
    }

    #[\Override]
    public function writeln(string $message = '', Verbosity $verbosity = Verbosity::Normal): void
    {
        $this->stdout->writeln($message, $verbosity);
    }

    #[\Override]
    public function newLine(int $count = 1): void
    {
        $this->stdout->newLine($count);
    }
}
