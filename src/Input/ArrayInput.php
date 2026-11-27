<?php

declare(strict_types=1);

namespace KaririCode\Console\Input;

use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\ValueObject\OptionDescriptor;
use KaririCode\Console\ValueObject\ArgumentDescriptor;

/**
 * Input from PHP arrays — for tests and programmatic invocation via run().
 *
 * Enforces exactly the rules ArgvInput enforces (unknown names rejected,
 * required arguments checked, defaults applied, array values normalised),
 * so a command tested with ArrayInput behaves as it will from the shell.
 *
 *   new ArrayInput(['name' => 'Walmir'], ['shout' => true, 'tag' => ['a', 'b']])
 *
 * Option keys may be written with or without leading dashes. There are no
 * raw tokens, so Application::dispatch() does not apply; use run().
 *
 * @package KaririCode\Console\Input
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class ArrayInput implements Input
{
    /** @var array<string, mixed> */
    public private(set) array $arguments = [];

    /** @var array<string, mixed> */
    public private(set) array $options = [];

    /** Array input has no argv representation. */
    public array $tokens {
        get => [];
    }

    /** @var array<array-key, mixed> Keys may be ints: PHP converts numeric-string keys. */
    private readonly array $rawArguments;

    /** @var array<array-key, mixed> Keys may be ints: PHP converts numeric-string keys. */
    private readonly array $rawOptions;

    /**
     * Parameter names are part of the public API (named arguments).
     *
     * @param array<array-key, mixed> $arguments Argument name => value.
     * @param array<array-key, mixed> $options   Option name (dashes optional) => value.
     */
    public function __construct(
        array $arguments = [],
        array $options = [],
        public readonly bool $interactive = false,
    ) {
        // Option keys may carry leading dashes ("--env" => "env").
        $this->rawArguments = $arguments;
        $this->rawOptions   = array_combine(
            array_map(static fn(int|string $name): string => ltrim((string) $name, '-'), array_keys($options)),
            array_values($options),
        );
    }

    // ── Input contract ───────────────────────────────────────────────────

    #[\Override]
    public function parse(InputDefinition $definition): void
    {
        $this->arguments = [];
        $this->options   = [];

        $this->rejectUnknownNames($definition);

        foreach ($definition->arguments as $argument) {
            $this->arguments[$argument->name] = $this->argumentValue($argument);
        }

        foreach ($definition->options as $option) {
            $this->options[$option->name] = array_key_exists($option->name, $this->rawOptions)
                ? self::optionValue($option, $this->rawOptions[$option->name])
                : $option->default;
        }
    }

    /** @throws ConsoleException 9003/9004 for names the definition does not declare */
    private function rejectUnknownNames(InputDefinition $definition): void
    {
        $argument = array_find(array_keys($this->rawArguments), static fn(int|string $name): bool => !$definition->hasArgument((string) $name));

        if ($argument !== null) {
            throw ConsoleException::invalidArgument((string) $argument, 'unknown argument');
        }

        $option = array_find(array_keys($this->rawOptions), static fn(int|string $name): bool => !$definition->hasOption((string) $name));

        if ($option !== null) {
            throw ConsoleException::invalidOption((string) $option, 'unknown option');
        }
    }

    /** @throws ConsoleException 9005 when a required argument is missing */
    private function argumentValue(ArgumentDescriptor $argument): mixed
    {
        if (array_key_exists($argument->name, $this->rawArguments)) {
            /** @var mixed $value Programmatic input; typed by the definition. */
            $value = $this->rawArguments[$argument->name];

            return $argument->isArray ? self::toList($value) : $value;
        }

        if ($argument->required) {
            throw ConsoleException::missingRequiredArgument($argument->name);
        }

        return $argument->default;
    }

    /** @throws ConsoleException 9004 when a flag value is not a boolean */
    private static function optionValue(OptionDescriptor $option, mixed $value): mixed
    {
        return match (true) {
            // Strict: (bool) 'false' would be true.
            $option->isFlag() => is_bool($value) ? $value
                : (filter_var($value, filter: FILTER_VALIDATE_BOOL, options: FILTER_NULL_ON_FAILURE)
                    ?? throw ConsoleException::invalidOption($option->name, 'flag expects a boolean')),
            $option->isArray => self::toList($value),
            default => $value,
        };
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

    /** @return list<mixed> */
    private static function toList(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [$value];
    }
}
