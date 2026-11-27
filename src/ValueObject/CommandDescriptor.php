<?php

declare(strict_types=1);

namespace KaririCode\Console\ValueObject;

use KaririCode\Console\Contract\Command;

/**
 * Immutable metadata snapshot for a registered command.
 *
 * The optional $factory closure is used by Application::instantiateFromDescriptor()
 * for commands that require constructor arguments (e.g. built-in
 * HelpCommand and ListCommand that receive the Application itself).
 * When $factory is null, Application falls back to new $className().
 *
 * This avoids a circular dependency: Application cannot pass itself to
 * built-in command constructors at registration time (Application does
 * not yet exist), so the factory closes over the Application after
 * construction (Value Object, Spec v4.3.3 §5.2).
 *
 *   $registry->register(new CommandDescriptor(
 *       name: 'cache:warm', description: 'Warms the cache',
 *       className: WarmCommand::class, aliases: ['warm'],
 *   ));
 *
 * @package KaririCode\Console\ValueObject
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final readonly class CommandDescriptor
{
    /**
     * Names and aliases are spliced into generated shell completion code
     * (bash case patterns, zsh arrays, fish conditions), so they are
     * restricted to characters that are inert in all three shells.
     */
    private const string NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9:._-]*$/';

    /**
     * @param class-string $className Unverified: loader maps may name any class;
     *                               Application checks the Command contract at instantiation.
     * @param list<string>          $aliases
     * @param (\Closure(): Command)|null $factory
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $className,
        public array $aliases = [],
        public bool $hidden = false,
        public ?\Closure $factory = null,
    ) {
        foreach ([$name, ...$aliases] as $candidate) {
            if (preg_match(self::NAME_PATTERN, $candidate) !== 1) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid command name %s: use letters, digits and ":._-", starting with a letter or digit.',
                    var_export($candidate, return: true),
                ));
            }
        }
    }
}
