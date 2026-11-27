<?php

declare(strict_types=1);

namespace KaririCode\Console\Contract;

/**
 * Command input: raw tokens before parse(), typed values after it.
 *
 * Collections are exposed as read-only interface properties; single values
 * are looked up by name. Implementations must behave identically for the
 * same definition — ArrayInput exists so tests exercise exactly the rules
 * ArgvInput enforces in production (ARFA 1.3 P4).
 *
 * @package KaririCode\Console\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
interface Input
{
    /** @var array<string, mixed> Parsed arguments, defaults applied. Empty before parse(). */
    public array $arguments { get; }

    /** @var array<string, mixed> Parsed options, defaults applied. Empty before parse(). */
    public array $options { get; }

    /** @var list<string> Raw argv-style tokens (empty for non-argv inputs). */
    public array $tokens { get; }

    /** False disables prompts: they return their defaults without reading input. */
    public bool $interactive { get; }

    /**
     * Validates the raw input against $definition and applies defaults.
     *
     * @throws \KaririCode\Console\Exception\ConsoleException 9003–9005 on invalid input
     */
    public function parse(InputDefinition $definition): void;

    public function getArgument(string $name): mixed;

    public function hasArgument(string $name): bool;

    public function getOption(string $name, mixed $default = null): mixed;

    public function hasOption(string $name): bool;

}
