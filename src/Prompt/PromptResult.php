<?php

declare(strict_types=1);

namespace KaririCode\Console\Prompt;

use KaririCode\Console\Exception\ConsoleException;

/**
 * Immutable result of a user prompt.
 *
 * Carries the raw string value and whether the user explicitly provided
 * a response or the default was used. This distinction matters for
 * commands that behave differently when a user actively confirms vs.
 * silently accepts the default (ARFA 1.3 P1).
 *
 *   $retries = $prompter->text('Retries', default: '3')->asInt();
 *
 * @package KaririCode\Console\Prompt
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
final readonly class PromptResult
{
    public function __construct(
        public string $value,
        public bool $isDefault = false,
    ) {}

    // ── Factories ────────────────────────────────────────────────────────

    public static function fromUserInput(string $value): self
    {
        return new self($value, isDefault: false);
    }

    public static function fromDefault(string $default): self
    {
        return new self($default, isDefault: true);
    }

    // ── Typed coercions ──────────────────────────────────────────────────

    /**
     * Same vocabulary as InputMapper: 1/true/yes/y/on and 0/false/no/n/off
     * (an empty answer is false). Anything else is rejected rather than
     * silently read as "no".
     *
     * @throws ConsoleException 9003 on an unrecognised answer
     */
    public function asBoolean(): bool
    {
        return match (strtolower(trim($this->value))) {
            '1', 'true', 'yes', 'y', 'on'       => true,
            '0', 'false', 'no', 'n', 'off', ''  => false,
            default => throw ConsoleException::invalidArgument('answer', "expects yes/no, got {$this->quoted()}"),
        };
    }

    /**
     * An empty answer yields $default; anything else must be an integer
     * ("1.9" and "abc" are rejected, not truncated or replaced).
     *
     * @throws ConsoleException 9003 when the answer is not an integer
     */
    public function asInt(int $default = 0): int
    {
        if (trim($this->value) === '') {
            return $default;
        }

        return filter_var(trim($this->value), filter: FILTER_VALIDATE_INT, options: FILTER_NULL_ON_FAILURE)
            ?? throw ConsoleException::invalidArgument('answer', "expects an integer, got {$this->quoted()}");
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    private function quoted(): string
    {
        return var_export($this->value, return: true);
    }
}
