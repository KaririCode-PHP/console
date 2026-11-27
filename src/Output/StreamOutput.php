<?php

declare(strict_types=1);

namespace KaririCode\Console\Output;

use KaririCode\Console\Contract\Output;
use KaririCode\Console\Contract\OutputFormatter;
use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Formatter\AnsiFormatter;

/**
 * Output that writes formatted text to a single PHP stream.
 *
 * Colour is enabled automatically only when the stream is an interactive
 * terminal and the user has not opted out (see supportsColor()).
 *
 *   $output = new StreamOutput(fopen('php://stderr', 'w'), Verbosity::Verbose);
 *
 * Side effects: writes to its stream (fwrite).
 *
 * @package KaririCode\Console\Output
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class StreamOutput implements Output
{
    /** @var resource */
    private readonly mixed $stream;

    private readonly OutputFormatter $formatter;

    public bool $decorated {
        get => $this->formatter->decorated;
        set {
            $this->formatter->decorated = $value;
        }
    }

    /** A single-stream output reports diagnostics on itself. */
    public Output $errorOutput {
        get => $this;
    }

    /**
     * @param mixed $stream An open, writable stream resource (resources cannot be typed).
     *
     * @throws ConsoleException 9007 when $stream is not an open stream resource
     */
    public function __construct(
        mixed $stream,
        public Verbosity $verbosity = Verbosity::Normal,
        ?OutputFormatter $formatter = null,
    ) {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw ConsoleException::outputWriteFailure('stream argument is not an open stream resource');
        }

        $this->stream    = $stream;
        $this->formatter = $formatter ?? new AnsiFormatter(self::supportsColor($stream));
    }

    // ── Output contract ──────────────────────────────────────────────────

    #[\Override]
    public function write(string $message, Verbosity $verbosity = Verbosity::Normal): void
    {
        if (!$this->verbosity->isAtLeast($verbosity)) {
            return;
        }

        if (@fwrite($this->stream, $this->formatter->format($message)) === false) {
            throw ConsoleException::outputWriteFailure('fwrite returned false');
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

    // ── Capability detection ─────────────────────────────────────────────

    /**
     * Decides whether ANSI colour should be emitted on $stream.
     *
     * Order of precedence:
     *   1. NO_COLOR set and non-empty → never (https://no-color.org).
     *   2. TERM=dumb → never (terminals without escape support, e.g. Emacs).
     *   3. Windows → only when the console has VT100 processing enabled.
     *   4. Otherwise → only when the stream is a TTY (not a pipe or file).
     *
     * @param resource $stream
     */
    private static function supportsColor(mixed $stream): bool
    {
        $noColor = getenv('NO_COLOR');

        if (($noColor !== false && $noColor !== '') || getenv('TERM') === 'dumb') {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return \function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support($stream);
        }

        return @stream_isatty($stream);
    }
}
