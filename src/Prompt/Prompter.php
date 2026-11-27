<?php

declare(strict_types=1);

namespace KaririCode\Console\Prompt;

use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Formatter\AnsiFormatter;

/**
 * Interactive prompts: text, password, confirm, select, multiselect, spin.
 *
 * When Input::$interactive is false (CI, `-n`, piped runs) every prompt
 * returns its default immediately, without reading or blocking.
 *
 * Answers are validated, never guessed: an out-of-range or malformed
 * choice is re-asked up to MAX_ATTEMPTS times and then rejected (9003),
 * instead of silently selecting an option the user did not pick.
 *
 * Secrets fail closed: when the input is an interactive terminal whose
 * echo cannot be disabled, password() refuses rather than display the
 * secret (Spec v4.3.3 §20, fail closed; same policy as Symfony's QuestionHelper).
 *
 * Renamed from InteractiveHelper in 4.3.0 (Spec v4.3.3 §18: no Helper names).
 *
 *   $prompter = new Prompter($input, $output);
 *   $database = $prompter->select('Database', ['MySQL', 'PostgreSQL']);
 *
 * Side effects: reads the input stream and writes prompts; password() runs `stty`
 *               to toggle terminal echo and always restores it.
 *
 * @package KaririCode\Console\Prompt
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
final class Prompter
{
    /** Attempts allowed for select()/multiselect() before giving up. */
    private const int MAX_ATTEMPTS = 3;

    /** @var resource */
    private readonly mixed $inputStream;

    /**
     * @param resource|null $inputStream Readable stream; defaults to STDIN. Tests
     *                                   pass php://memory to script the answers.
     *
     * @throws \InvalidArgumentException when $inputStream is not an open stream
     */
    public function __construct(
        private readonly Input $input,
        private readonly Output $output,
        mixed $inputStream = null,
    ) {
        $stream = $inputStream ?? (\defined('STDIN') ? \STDIN : fopen('php://stdin', 'r'));

        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new \InvalidArgumentException('Prompter input must be an open stream resource.');
        }

        $this->inputStream = $stream;
    }

    // ── Text ─────────────────────────────────────────────────────────────

    /**
     * @param list<string>|null $suggestions Shown as hints (up to six).
     */
    public function text(
        string $label,
        string $placeholder = '',
        string $default = '',
        ?array $suggestions = null,
    ): PromptResult {
        if (!$this->input->interactive) {
            return PromptResult::fromDefault($default);
        }

        $hint        = $placeholder !== '' ? ' [' . AnsiFormatter::escape($placeholder) . ']' : '';
        $defaultHint = $default !== '' ? ' (default: <comment>' . AnsiFormatter::escape($default) . '</comment>)' : '';

        $this->output->writeln("<info>{$label}</info>{$defaultHint}{$hint}");

        if ($suggestions !== null && $suggestions !== []) {
            $this->output->writeln('  Suggestions: ' . AnsiFormatter::escape(implode(', ', array_slice($suggestions, offset: 0, length: 6))));
        }

        $raw = $this->ask();

        return $raw !== '' ? PromptResult::fromUserInput($raw) : PromptResult::fromDefault($default);
    }

    // ── Password ─────────────────────────────────────────────────────────

    /**
     * @throws ConsoleException 9002 when the terminal cannot hide the input
     */
    public function password(string $label, string $default = ''): PromptResult
    {
        if (!$this->input->interactive) {
            return PromptResult::fromDefault($default);
        }

        $this->output->writeln("<info>{$label}</info>");
        $this->output->write('> ');
        $raw = $this->readHidden();
        $this->output->newLine();

        // Only an empty answer means "use the default"; a secret of spaces is kept.
        return $raw !== '' ? PromptResult::fromUserInput($raw) : PromptResult::fromDefault($default);
    }

    // ── Confirm ──────────────────────────────────────────────────────────

    /** Only "y"/"yes" confirm; an empty answer takes $default; anything else is "no". */
    public function confirm(string $label, bool $default = true): bool
    {
        if (!$this->input->interactive) {
            return $default;
        }

        $hint = $default ? '<info>Y</info>/n' : 'y/<info>N</info>';
        $this->output->writeln("<info>{$label}</info> [{$hint}]");

        $raw = strtolower($this->ask());

        return $raw === '' ? $default : in_array($raw, haystack: ['y', 'yes'], strict: true);
    }

    // ── Select ───────────────────────────────────────────────────────────

    /**
     * @param array<array-key, string> $choices Re-indexed from 0 (associative arrays accepted).
     *
     * @throws ConsoleException 9003 on empty choices, a bad default, or MAX_ATTEMPTS invalid answers
     */
    public function select(string $label, array $choices, int $defaultIndex = 0): string
    {
        $choices = array_values($choices);

        if ($choices === []) {
            throw ConsoleException::invalidArgument('choices', 'select() needs at least one choice');
        }

        if (!isset($choices[$defaultIndex])) {
            throw ConsoleException::invalidArgument('defaultIndex', "{$defaultIndex} is outside 0.." . (count($choices) - 1));
        }

        if (!$this->input->interactive) {
            return $choices[$defaultIndex];
        }

        $this->output->writeln("<info>{$label}</info>");

        foreach ($choices as $index => $choice) {
            $marker = $index === $defaultIndex ? '<comment>❯</comment>' : ' ';
            $this->output->writeln("  {$marker} [{$index}] " . AnsiFormatter::escape($choice));
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $raw = $this->ask();

            if ($raw === '') {
                return $choices[$defaultIndex];
            }

            if (ctype_digit($raw) && isset($choices[(int) $raw])) {
                return $choices[(int) $raw];
            }

            $this->output->errorOutput->writeln('<e>' . AnsiFormatter::escape("\"{$raw}\" is not one of 0.." . (count($choices) - 1)) . '</e>');
        }

        throw ConsoleException::invalidArgument('choice', 'no valid answer after ' . self::MAX_ATTEMPTS . ' attempts');
    }

    // ── Multi-select ─────────────────────────────────────────────────────

    /**
     * Accepts comma-separated indices ("0,2,3"). An empty answer keeps the
     * defaults; with no defaults, nothing is selected.
     *
     * @param array<array-key, string> $choices  Re-indexed from 0 (associative arrays accepted).
     * @param list<int>|null           $defaults Indices pre-selected.
     * @return list<string> Selected choices, in choice order, without duplicates.
     *
     * @throws ConsoleException 9003 on a default outside the choices or MAX_ATTEMPTS invalid answers
     */
    public function multiselect(string $label, array $choices, ?array $defaults = null): array
    {
        $choices  = array_values($choices);
        $defaults ??= [];

        foreach ($defaults as $index) {
            if (!isset($choices[$index])) {
                throw ConsoleException::invalidArgument('defaults', "index {$index} is outside the choices");
            }
        }

        if (!$this->input->interactive) {
            return self::pick($choices, $defaults);
        }

        $this->output->writeln("<info>{$label}</info> <comment>(comma-separated indices)</comment>");

        foreach ($choices as $index => $choice) {
            $marker = in_array($index, haystack: $defaults, strict: true) ? '<comment>●</comment>' : '○';
            $this->output->writeln("  {$marker} [{$index}] " . AnsiFormatter::escape($choice));
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $raw = $this->ask();

            if ($raw === '') {
                return self::pick($choices, $defaults);
            }

            $tokens = array_map(trim(...), explode(',', $raw));

            if (array_all($tokens, static fn(string $t): bool => ctype_digit($t) && isset($choices[(int) $t]))) {
                return self::pick($choices, array_map(intval(...), $tokens));
            }

            $this->output->errorOutput->writeln('<e>' . AnsiFormatter::escape("\"{$raw}\" must be indices between 0 and " . (count($choices) - 1)) . '</e>');
        }

        throw ConsoleException::invalidArgument('choices', 'no valid answer after ' . self::MAX_ATTEMPTS . ' attempts');
    }

    // ── Spin ─────────────────────────────────────────────────────────────

    /**
     * Runs $callback between a status label and a checkmark. Synchronous
     * PHP has no thread to animate a spinner while the callback runs.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function spin(string $label, callable $callback): mixed
    {
        if (!$this->output->decorated) {
            $this->output->writeln("<info>{$label}…</info>");

            return $callback();
        }

        $this->output->write("<info>{$label}</info> ");
        $result = $callback();
        $this->output->writeln('<info>✓</info>');

        return $result;
    }

    // ── Internals ────────────────────────────────────────────────────────

    private function ask(): string
    {
        $this->output->write('> ');

        return trim((string) fgets($this->inputStream));
    }

    /**
     * Reads a line with terminal echo disabled.
     *
     * Only a TTY echoes, so piped or in-memory input is read as is. On a TTY
     * the echo is turned off with stty and always restored (finally), even
     * if reading throws.
     *
     * @throws ConsoleException 9002 when the input is a TTY that cannot hide echo
     */
    private function readHidden(): string
    {
        if (!@stream_isatty($this->inputStream)) {
            return self::withoutLineTerminator((string) fgets($this->inputStream));
        }

        $sttyMode = self::canUseStty() ? trim((string) shell_exec('stty -g 2>/dev/null')) : '';

        if ($sttyMode === '') {
            throw ConsoleException::executionFailed('password', 'unable to hide input on this terminal; refusing to echo a secret');
        }

        shell_exec('stty -echo');

        try {
            return self::withoutLineTerminator((string) fgets($this->inputStream));
        } finally {
            shell_exec('stty ' . escapeshellarg($sttyMode));
        }
    }

    /**
     * Secrets are returned exactly as typed (ASVS 5.0 V6.2.8): only the line
     * terminator is removed, never surrounding spaces.
     */
    private static function withoutLineTerminator(string $line): string
    {
        return rtrim($line, "\r\n");
    }

    private static function canUseStty(): bool
    {
        return DIRECTORY_SEPARATOR === '/' && \function_exists('shell_exec');
    }

    /**
     * @param list<string> $choices
     * @param list<int>    $indices
     * @return list<string>
     */
    private static function pick(array $choices, array $indices): array
    {
        $wanted = array_flip($indices);

        return array_values(array_filter(
            $choices,
            callback: static fn(int $index): bool => isset($wanted[$index]),
            mode: ARRAY_FILTER_USE_KEY,
        ));
    }
}
