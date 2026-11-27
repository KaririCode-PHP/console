<?php

declare(strict_types=1);

namespace KaririCode\Console\Contract;

use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Declares a command's accepted arguments and options.
 *
 * Mutated only through addArgument()/addOption() during configure(), which
 * enforce the ordering invariants; read through the collection properties
 * during parsing.
 *
 * @package KaririCode\Console\Contract
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
interface InputDefinition
{
    /** @var list<ArgumentDescriptor> In positional order. */
    public array $arguments { get; }

    /** @var list<OptionDescriptor> In declaration order. */
    public array $options { get; }

    public function addArgument(ArgumentDescriptor $argument): void;

    public function hasArgument(string $name): bool;

    /** @throws \KaririCode\Console\Exception\ConsoleException 9003 when undeclared */
    public function getArgument(string $name): ArgumentDescriptor;

    public function addOption(OptionDescriptor $option): void;

    public function hasOption(string $name): bool;

    /** @throws \KaririCode\Console\Exception\ConsoleException 9004 when undeclared */
    public function getOption(string $name): OptionDescriptor;
}
