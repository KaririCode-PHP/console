<?php

declare(strict_types=1);

namespace KaririCode\Console\Attribute;

use Attribute;

/**
 * Declares a positional argument on a command class or DTO constructor parameter.
 *
 * When applied to a constructor parameter inside a #[MapInput] DTO, the
 * name defaults to the parameter name and InputMapper handles wiring
 * automatically (ADR-004).
 *
 *   public function __construct(
 *       #[Argument(description: 'Target environment')] public string $environment,
 *   ) {}
 *
 * @package KaririCode\Console\Attribute
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final readonly class Argument
{
    public function __construct(
        /** Argument name; defaults to parameter name when used on constructor params. */
        public ?string $name = null,
        public string $description = '',
        public bool $required = true,
        public mixed $default = null,
        public bool $isArray = false,
    ) {}
}
