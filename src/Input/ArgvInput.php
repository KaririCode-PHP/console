<?php

declare(strict_types=1);

namespace KaririCode\Console\Input;

use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Parses argv-style tokens against a definition (POSIX utility syntax,
 * IEEE Std 1003.1-2017 XBD §12.2, plus GNU long options).
 *
 *   --option=value | --option value   long option with a value
 *   --flag                            long flag
 *   -o value | -ovalue                short option with a value
 *   -abc                              grouped short flags (Guideline 5):
 *                                     -a -b -c; the last may take a value
 *   --                                end of options: the rest are operands
 *   value                             positional argument
 *
 * Array options accumulate (`-I a --include b` → [a, b]) and an array
 * argument consumes every remaining operand. Unknown options, values
 * given to flags, and surplus operands are rejected rather than ignored.
 *
 * @package KaririCode\Console\Input
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class ArgvInput implements Input
{
    /** @var array<string, mixed> */
    public private(set) array $arguments = [];

    /** @var array<string, mixed> */
    public private(set) array $options = [];

    /** @var list<string> */
    public readonly array $tokens;

    /**
     * @param list<string>|null $tokens Defaults to $_SERVER['argv'] without the script name.
     */
    public function __construct(?array $tokens = null, public readonly bool $interactive = true)
    {
        /** @var list<string> $argv */
        $argv         = $_SERVER['argv'] ?? [];
        $this->tokens = $tokens ?? array_slice($argv, 1);
    }

    // ── Input contract ───────────────────────────────────────────────────

    #[\Override]
    public function parse(InputDefinition $definition): void
    {
        $this->arguments = [];
        $this->options   = [];

        $tokens       = $this->tokens;
        $count        = count($tokens);
        $operandIndex = 0;
        $endOfOptions = false;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!$endOfOptions && $token === '--') {
                $endOfOptions = true;
            } elseif (!$endOfOptions && str_starts_with($token, '--')) {
                $i = $this->parseLongOption(token: $token, tokens: $tokens, index: $i, definition: $definition);
            } elseif (!$endOfOptions && str_starts_with($token, '-') && $token !== '-') {
                $i = $this->parseShortOptions(token: $token, tokens: $tokens, index: $i, definition: $definition);
            } else {
                // A lone "-" is an operand by convention (stdin).
                $operandIndex = $this->parseOperand(token: $token, arguments: $definition->arguments, index: $operandIndex);
            }
        }

        $this->applyDefaults($definition);
    }

    #[\Override]
    public function getArgument(string $name): mixed
    {
        return $this->arguments[$name] ?? null;
    }

    #[\Override]
    public function hasArgument(string $name): bool
    {
        return array_key_exists($name, $this->arguments);
    }

    #[\Override]
    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    #[\Override]
    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    // ── Token parsing ────────────────────────────────────────────────────

    /**
     * @param list<string> $tokens
     * @return int Index of the last token consumed.
     */
    private function parseLongOption(string $token, array $tokens, int $index, InputDefinition $definition): int
    {
        $body   = substr($token, 2);
        $equals = strpos($body, '=');
        $name   = $equals === false ? $body : substr($body, offset: 0, length: $equals);
        $value  = $equals === false ? null : substr($body, $equals + 1);

        if (!$definition->hasOption($name)) {
            throw ConsoleException::invalidOption($name, 'unknown option');
        }

        $option = $definition->getOption($name);

        if ($option->isFlag()) {
            if ($value !== null) {
                throw ConsoleException::invalidOption($name, 'does not accept a value');
            }

            $this->options[$name] = true;

            return $index;
        }

        if ($value === null && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '-')) {
            $value = $tokens[++$index];
        }

        if ($value === null) {
            throw ConsoleException::invalidOption($name, 'expects a value');
        }

        $this->assignOption($option, $value);

        return $index;
    }

    /**
     * Handles `-a`, `-avalue`, `-a value` and grouped flags `-abc`.
     *
     * @param list<string> $tokens
     * @return int Index of the last token consumed.
     */
    private function parseShortOptions(string $token, array $tokens, int $index, InputDefinition $definition): int
    {
        $cluster = substr($token, 1);
        $length  = strlen($cluster);

        for ($position = 0; $position < $length; $position++) {
            $shortcut = $cluster[$position];
            /** @var OptionDescriptor $option (null throws below) */
            $option   = array_find(
                $definition->options,
                static fn(OptionDescriptor $o): bool => $o->shortcut === $shortcut,
            ) ?? throw ConsoleException::invalidOption($shortcut, 'unknown shortcut');

            if ($option->isFlag()) {
                $this->options[$option->name] = true;
                continue;
            }

            // A value-taking option ends the cluster: the rest is its value,
            // or the next token when nothing follows (POSIX Guideline 5).
            $inline = substr($cluster, $position + 1);

            if ($inline !== '') {
                $this->assignOption($option, $inline);
            } elseif (isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '-')) {
                $this->assignOption($option, $tokens[++$index]);
            } else {
                throw ConsoleException::invalidOption($option->name, 'expects a value');
            }

            break;
        }

        return $index;
    }

    /**
     * @param list<ArgumentDescriptor> $arguments
     * @return int Index of the argument the next operand fills.
     */
    private function parseOperand(string $token, array $arguments, int $index): int
    {
        $argument = $arguments[$index]
            ?? throw ConsoleException::invalidArgument($token, sprintf('unexpected operand (command accepts %d argument(s))', count($arguments)));

        if ($argument->isArray) {
            /** @var list<string> $values */
            $values                            = $this->arguments[$argument->name] ?? [];
            $values[]                          = $token;
            $this->arguments[$argument->name] = $values;

            return $index; // greedy: later operands feed the same slot
        }

        $this->arguments[$argument->name] = $token;

        return $index + 1;
    }

    /** Single assignment path, so long and short forms cannot diverge. */
    private function assignOption(OptionDescriptor $option, string $value): void
    {
        if (!$option->isArray) {
            $this->options[$option->name] = $value;
            return;
        }

        /** @var list<string> $values */
        $values                        = $this->options[$option->name] ?? [];
        $values[]                      = $value;
        $this->options[$option->name] = $values;
    }

    private function applyDefaults(InputDefinition $definition): void
    {
        foreach ($definition->arguments as $argument) {
            if ($argument->required && !array_key_exists($argument->name, $this->arguments)) {
                throw ConsoleException::missingRequiredArgument($argument->name);
            }

            if (!array_key_exists($argument->name, $this->arguments)) {
                $this->arguments[$argument->name] = $argument->default;
            }
        }

        foreach ($definition->options as $option) {
            if (!array_key_exists($option->name, $this->options)) {
                $this->options[$option->name] = $option->default;
            }
        }
    }
}
