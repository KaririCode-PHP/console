<?php

declare(strict_types=1);

namespace KaririCode\Console\Integration;

use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\CommandLoader;

/**
 * Bridges KaririCode\ClassDiscovery to the Console command loader chain.
 *
 * When ClassDiscovery is available in the container, this bridge delegates
 * scanning to its AttributeScanner; otherwise it falls through to
 * AttributeCommandLoader's built-in token parser (ADR-001).
 *
 * Decoupling strategy: the bridge takes a \Closure (e.g. a first-class
 * callable `$scanner->scan(...)`) rather than a concrete ClassDiscovery
 * type, so Console has zero hard dependency on that package, while a
 * non-callable is still rejected at construction (fail fast).
 *
 *   $registry->addLoader(new ClassDiscoveryBridge(
 *       static fn(string $attribute): array => $classIndex[$attribute] ?? [],
 *   ));
 *
 * Side effects: calls the scanner closure once (memoised).
 *
 * @package KaririCode\Console\Integration
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class ClassDiscoveryBridge implements CommandLoader
{
    /**
     * @param \Closure(class-string): iterable<class-string> $scanner
     *   Accepts an attribute FQCN, returns all classes bearing it.
     */
    public function __construct(
        private readonly \Closure $scanner,
    ) {}

    /** @var array<string, class-string<Command>>|null Memoised discovery result. */
    private ?array $cache = null;

    // ── CommandLoader contract ───────────────────────────────────────────

    #[\Override]
    public function load(): array
    {
        return $this->cache ??= $this->discover();
    }

    #[\Override]
    public function isWarm(): bool
    {
        return $this->cache !== null;
    }

    // ── Discovery ────────────────────────────────────────────────────────

    /** @return array<string, class-string<Command>> */
    private function discover(): array
    {
        /** @var list<class-string> $classes */
        $classes = ($this->scanner)(AsCommand::class);

        $commands = [];

        foreach ($classes as $className) {
            if (!is_a($className, class: Command::class, allow_string: true)) {
                continue; // #[AsCommand] on something that is not a Command
            }

            $reflection = new \ReflectionClass($className);
            $attributes = $reflection->getAttributes(AsCommand::class);

            if (empty($attributes)) {
                continue;
            }

            // Canonical name only; the registry expands aliases (see AttributeCommandLoader).
            $commands[$attributes[0]->newInstance()->name] = $className;
        }

        return $commands;
    }
}
