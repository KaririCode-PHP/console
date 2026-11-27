<?php

declare(strict_types=1);

namespace KaririCode\Console\Attribute;

use Attribute;

/**
 * Declares a named option (--flag or --key=value) on a command class or DTO constructor parameter.
 *
 * When applied to a constructor parameter inside a #[MapInput] DTO, the
 * name defaults to the parameter name and InputMapper handles wiring
 * automatically (ADR-004).
 *
 *   public function __construct(
 *       #[Option(shortcut: 't', isArray: true)] public array $tag = [],
 *   ) {}
 *
 * @package KaririCode\Console\Attribute
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final readonly class Option
{
    public function __construct(
        /** Option name; defaults to parameter name when used on constructor params. */
        public ?string $name = null,
        public string $description = '',
        public ?string $shortcut = null,
        public mixed $default = null,
        public bool $acceptsValue = true,
        public bool $isArray = false,
    ) {}
}
