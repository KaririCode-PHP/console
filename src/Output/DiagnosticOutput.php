<?php

declare(strict_types=1);

namespace KaririCode\Console\Output;

use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\DiagnosticFormat;
use KaririCode\Console\Enum\Severity;
use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Formatter\AnsiFormatter;
use KaririCode\Console\ValueObject\SourceLocation;

/**
 * Decorator that adds structured diagnostics to any Output (ADR-006).
 *
 *   $diagnostics = new DiagnosticOutput($output, DiagnosticFormat::GitHub);
 *   $diagnostics->diagnostic(Severity::Error, 'Missing strict_types', new SourceLocation('src/Foo.php', 3));
 *
 * All diagnostic text is treated as untrusted: it is escaped for the
 * console formatter and, in GitHub mode, for the workflow-command grammar,
 * so a finding can never inject markup or CI commands.
 *
 * Side effects: writes to the decorated output.
 *
 * @package KaririCode\Console\Output
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.2.0
 */
final class DiagnosticOutput implements Output
{
    private const string RESET = "\e[0m";

    /** @var list<array<string, int|string>> Pending entries in Json mode. */
    private array $buffer = [];

    public Verbosity $verbosity {
        get => $this->inner->verbosity;
        set {
            $this->inner->verbosity = $value;
        }
    }

    public bool $decorated {
        get => $this->inner->decorated;
        set {
            $this->inner->decorated = $value;
        }
    }

    public Output $errorOutput {
        get => $this->inner->errorOutput;
    }

    public function __construct(
        private readonly Output $inner,
        private readonly DiagnosticFormat $format = DiagnosticFormat::Human,
    ) {}

    // ── Diagnostics ──────────────────────────────────────────────────────

    public function diagnostic(Severity $severity, string $message, ?SourceLocation $location = null): void
    {
        match ($this->format) {
            DiagnosticFormat::Human  => $this->emitHuman(severity: $severity, message: $message, location: $location),
            DiagnosticFormat::GitHub => $this->emitGitHub(severity: $severity, message: $message, location: $location),
            DiagnosticFormat::Json   => $this->bufferJson(severity: $severity, message: $message, location: $location),
        };
    }

    /** Writes buffered Json-mode diagnostics as one array; no-op otherwise. */
    public function flushDiagnostics(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $json = json_encode(
            $this->buffer,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );

        $this->inner->writeln(AnsiFormatter::escape($json));
        $this->buffer = [];
    }

    /** Number of diagnostics waiting for flushDiagnostics(). */
    public function countBuffered(): int
    {
        return count($this->buffer);
    }

    // ── Emission strategies ──────────────────────────────────────────────

    private function emitHuman(Severity $severity, string $message, ?SourceLocation $location): void
    {
        $prefix = $location === null ? '' : self::describeLocation($location) . ': ';
        $text   = AnsiFormatter::escape("{$prefix}{$severity->label()} {$message}");

        $this->inner->writeln($this->inner->decorated ? $severity->ansiColor() . $text . self::RESET : $text);
    }

    private function emitGitHub(Severity $severity, string $message, ?SourceLocation $location): void
    {
        $properties = [];

        if ($location !== null) {
            $properties[] = 'file=' . self::escapeWorkflowProperty($location->file);

            if ($location->line !== null) {
                $properties[] = "line={$location->line}";
            }

            if ($location->column !== null) {
                $properties[] = "col={$location->column}";
            }
        }

        $command = sprintf(
            '::%s%s::%s',
            $severity->githubAnnotation(),
            $properties === [] ? '' : ' ' . implode(',', $properties),
            self::escapeWorkflowData($message),
        );

        $this->inner->writeln(AnsiFormatter::escape($command));
    }

    private function bufferJson(Severity $severity, string $message, ?SourceLocation $location): void
    {
        $entry = ['severity' => strtolower($severity->name), 'message' => $message];

        if ($location !== null) {
            $entry['file'] = $location->file;

            if ($location->line !== null) {
                $entry['line'] = $location->line;
            }

            if ($location->column !== null) {
                $entry['column'] = $location->column;
            }
        }

        $this->buffer[] = $entry;
    }

    private static function describeLocation(SourceLocation $location): string
    {
        return $location->file
            . ($location->line !== null ? ":{$location->line}" : '')
            . ($location->column !== null ? ":{$location->column}" : '');
    }

    // ── GitHub workflow-command escaping (actions/toolkit core/command.ts)

    /** Message payload: `%`, CR and LF must be percent-encoded. */
    private static function escapeWorkflowData(string $value): string
    {
        return strtr($value, ['%' => '%25', "\r" => '%0D', "\n" => '%0A']);
    }

    /** Property values additionally encode the `:` and `,` delimiters. */
    private static function escapeWorkflowProperty(string $value): string
    {
        return strtr($value, ['%' => '%25', "\r" => '%0D', "\n" => '%0A', ':' => '%3A', ',' => '%2C']);
    }

    // ── Output delegation ────────────────────────────────────────────────

    #[\Override]
    public function write(string $message, Verbosity $verbosity = Verbosity::Normal): void
    {
        $this->inner->write($message, $verbosity);
    }

    #[\Override]
    public function writeln(string $message = '', Verbosity $verbosity = Verbosity::Normal): void
    {
        $this->inner->writeln($message, $verbosity);
    }

    #[\Override]
    public function newLine(int $count = 1): void
    {
        $this->inner->newLine($count);
    }
}
