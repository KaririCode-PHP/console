<?php

declare(strict_types=1);

namespace KaririCode\Console\ValueObject;

use KaririCode\Console\Exception\ConsoleException;

/**
 * Immutable descriptor for a command argument position.
 *
 * Arguments are positional tokens that are not prefixed with '--'.
 * Required arguments must appear before optional ones in the definition
 * (enforced by ConcreteInputDefinition at addArgument() time).
 *
 *   new ArgumentDescriptor('env', 'Target environment');
 *   new ArgumentDescriptor('regions', 'Regions', required: false, default: ['eu'], isArray: true);
 *
 * @package KaririCode\Console\ValueObject
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final readonly class ArgumentDescriptor
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $required = true,
        public mixed $default = null,
        public bool $isArray = false,
    ) {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_:.-]*$/', $name) !== 1) {
            throw ConsoleException::invalidArgument($name, 'name must start with a letter or digit and contain no spaces');
        }

        if ($required && $default !== null) {
            throw ConsoleException::invalidArgument($name, 'a required argument cannot have a default');
        }

        if ($isArray && $default !== null && !is_array($default)) {
            throw ConsoleException::invalidArgument($name, 'an array argument default must be an array');
        }
    }

    public function isOptional(): bool
    {
        return !$this->required;
    }
}
