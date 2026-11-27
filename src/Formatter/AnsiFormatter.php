<?php

declare(strict_types=1);

namespace KaririCode\Console\Formatter;

use KaririCode\Console\Contract\OutputFormatter;

/**
 * Renders console markup into ANSI SGR sequences, or strips it.
 *
 * Built-in styles:
 *   <info>     green            <comment>  yellow
 *   <question> cyan             <e>        white on red (errors)
 *   <b>        bold             <u>        underline
 *
 * Semantics (aligned with Symfony's OutputFormatter):
 *   - Only the built-in tags are markup. Any other <…> is literal text, so
 *     usage lines (`<name>`), generics (`list<string>`) and comparisons
 *     (`a < b > c`) survive intact.
 *   - `\<` renders a literal `<` — the escape that escape() produces.
 *   - Styles nest: closing a tag restores the styles still open around it,
 *     and any style left open is reset at the end of the message so colour
 *     never bleeds into later output.
 *
 * @package KaririCode\Console\Formatter
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class AnsiFormatter implements OutputFormatter
{
    /** Style tag → ANSI SGR opening sequence. */
    private const array STYLES = [
        'info'     => "\e[32m",
        'comment'  => "\e[33m",
        'question' => "\e[36m",
        'e'        => "\e[37;41m",
        'b'        => "\e[1m",
        'u'        => "\e[4m",
    ];

    private const string RESET = "\e[0m";

    /** Matches the `\<` escape or an opening/closing tag. */
    private const string TOKEN_PATTERN = '/\\\\<|<(\/?)([a-z]+)>/';

    public function __construct(public bool $decorated = true) {}

    // ── Escaping ─────────────────────────────────────────────────────────

    /**
     * Makes untrusted text safe to embed in markup and to print on a terminal.
     *
     *   - `<` becomes `\<`, so the text can never open or close a style.
     *   - Control characters other than tab and newline — including ESC,
     *     CR and the C1 range — are shown in caret notation (`^[`, `^M`)
     *     instead of being executed by the terminal (CWE-150).
     *   - Invalid UTF-8 is scrubbed first, so the result is always valid.
     */
    public static function escape(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');

        $text = preg_replace_callback(
            '/[\x00-\x08\x0B-\x1F\x7F\x{80}-\x{9F}]/u',
            callback: static fn(array $match): string => self::caretNotation($match[0]),
            subject: $text,
        ) ?? '';

        return str_replace('<', replace: '\\<', subject: $text);
    }

    // ── OutputFormatter contract ─────────────────────────────────────────

    #[\Override]
    public function format(string $message): string
    {
        /** @var list<string> $open */
        $open = [];

        $formatted = preg_replace_callback(
            self::TOKEN_PATTERN,
            // A by-reference closure (not an arrow fn): the open-style stack
            // must persist across callback invocations.
            callback: function (array $match) use (&$open): string {
                return $this->renderToken($match, $open);
            },
            subject: $message,
        ) ?? $message;

        // Unclosed styles are reset so colour never leaks into later writes.
        return $this->decorated && $open !== [] ? $formatted . self::RESET : $formatted;
    }

    // ── Rendering ────────────────────────────────────────────────────────

    /**
     * @param array<array-key, string> $match
     * @param list<string>       $open  Styles currently open (innermost last).
     */
    private function renderToken(array $match, array &$open): string
    {
        if ($match[0] === '\\<') {
            return '<';
        }

        $isClosing = $match[1] === '/';
        $tag       = $match[2];

        if (!isset(self::STYLES[$tag])) {
            return $match[0]; // not markup: keep the literal text
        }

        if (!$this->decorated) {
            return '';
        }

        if (!$isClosing) {
            $open[] = $tag;
            return self::STYLES[$tag];
        }

        $position = array_search($tag, haystack: array_reverse($open, preserve_keys: true), strict: true);

        if ($position === false) {
            return $match[0]; // unbalanced close: visible, never silently eaten
        }

        array_splice($open, offset: $position, length: 1);

        return self::RESET . implode('', array_map(
            static fn(string $style): string => self::STYLES[$style],
            $open,
        ));
    }

    /** `\e` → `^[`, `\r` → `^M`, DEL → `^?`; C1 controls → U+FFFD. */
    private static function caretNotation(string $char): string
    {
        // Cannot fail here: the input was scrubbed and matched as valid UTF-8.
        $code = (int) mb_ord($char, 'UTF-8');

        return match (true) {
            $code === 0x7F => '^?',
            $code < 0x20   => '^' . chr($code + 0x40),
            default        => "\u{FFFD}",
        };
    }
}
