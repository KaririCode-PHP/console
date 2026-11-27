<?php

declare(strict_types=1);

namespace KaririCode\Console\Attribute;

use Attribute;

/**
 * Marks a class as a console command and carries its registration metadata.
 *
 * Used by AttributeCommandLoader (via KaririCode\ClassDiscovery) to
 * auto-register commands without manual wiring.
 *
 * Usage:
 *   #[AsCommand('user:create', description: 'Create a new user')]
 *   final class CreateUserCommand implements Command { ... }
 *
 * @package KaririCode\Console\Attribute
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsCommand
{
    /** @param list<string> $aliases */
    public function __construct(
        public string $name,
        public string $description = '',
        public array $aliases = [],
        public bool $hidden = false,
    ) {}
}
