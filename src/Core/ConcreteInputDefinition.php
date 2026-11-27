<?php

declare(strict_types=1);

namespace KaririCode\Console\Core;

use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Default InputDefinition built by Application for each dispatch.
 *
 * Invariants enforced at add-time (fail fast, before any input is parsed):
 *   - argument and option names are unique;
 *   - a required argument never follows an optional one;
 *   - nothing follows an array argument (it consumes the remaining tokens);
 *   - an option shortcut maps to exactly one option.
 *
 * @package KaririCode\Console\Core
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class ConcreteInputDefinition implements InputDefinition
{
    /** @var list<ArgumentDescriptor> */
    public private(set) array $arguments = [];

    /** @var list<OptionDescriptor> */
    public array $options {
        get => array_values($this->optionsByName);
    }

    /** @var array<string, OptionDescriptor> */
    private array $optionsByName = [];

    // ── Arguments ────────────────────────────────────────────────────────

    #[\Override]
    public function addArgument(ArgumentDescriptor $argument): void
    {
        if ($this->hasArgument($argument->name)) {
            throw ConsoleException::invalidArgument($argument->name, 'already defined');
        }

        if (array_any($this->arguments, static fn(ArgumentDescriptor $a): bool => $a->isArray)) {
            throw ConsoleException::invalidArgument($argument->name, 'cannot add argument after an array argument');
        }

        if ($argument->required && array_any($this->arguments, static fn(ArgumentDescriptor $a): bool => !$a->required)) {
            throw ConsoleException::invalidArgument($argument->name, 'required argument cannot follow an optional argument');
        }

        $this->arguments[] = $argument;
    }

    #[\Override]
    public function hasArgument(string $name): bool
    {
        return array_any($this->arguments, static fn(ArgumentDescriptor $a): bool => $a->name === $name);
    }

    #[\Override]
    public function getArgument(string $name): ArgumentDescriptor
    {
        /** @var ArgumentDescriptor|null $argument */
        $argument = array_find($this->arguments, static fn(ArgumentDescriptor $a): bool => $a->name === $name);

        return $argument ?? throw ConsoleException::invalidArgument($name, 'not defined in this command');
    }

    // ── Options ──────────────────────────────────────────────────────────

    #[\Override]
    public function addOption(OptionDescriptor $option): void
    {
        if (isset($this->optionsByName[$option->name])) {
            throw ConsoleException::invalidOption($option->name, 'already defined');
        }

        /** @var OptionDescriptor|null $clash */

        $clash = $option->shortcut === null ? null : array_find(
            $this->optionsByName,
            static fn(OptionDescriptor $o): bool => $o->shortcut === $option->shortcut,
        );

        if ($clash !== null) {
            throw ConsoleException::invalidOption($option->name, "shortcut -{$option->shortcut} is already used by --{$clash->name}");
        }

        $this->optionsByName[$option->name] = $option;
    }

    #[\Override]
    public function hasOption(string $name): bool
    {
        return isset($this->optionsByName[$name]);
    }

    #[\Override]
    public function getOption(string $name): OptionDescriptor
    {
        return $this->optionsByName[$name]
            ?? throw ConsoleException::invalidOption($name, 'not defined in this command');
    }
}
