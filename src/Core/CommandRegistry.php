<?php

declare(strict_types=1);

namespace KaririCode\Console\Core;

use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\CommandLoader;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\ValueObject\CommandDescriptor;

/**
 * Central registry for all registered commands.
 *
 * Loaders are evaluated lazily on first access (no I/O before the first lookup).
 * Descriptors are the only data stored here; command instances are
 * created on demand to keep memory usage proportional to invocation
 * count rather than registry size.
 *
 *   $registry = new CommandRegistry();
 *   $registry->addLoader(new ExplicitCommandLoader(['deploy' => DeployCommand::class]));
 *   $descriptor = $registry->get('deploy');
 *
 * Side effects: the first lookup runs the registered loaders, which may read files.
 *
 * @package KaririCode\Console\Core
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class CommandRegistry
{
    /** @var list<CommandLoader> */
    private array $loaders = [];

    /** @var array<string, CommandDescriptor> — name → descriptor */
    private array $descriptors = [];

    private bool $loaded = false;

    // ── Registration ─────────────────────────────────────────────────────

    public function addLoader(CommandLoader $loader): void
    {
        $this->loaded = false;
        $this->loaders[] = $loader;
    }

    /**
     * Registers (or replaces, by primary name) a command.
     *
     * Replacing a command also drops the aliases of the one it replaces, so
     * an overridden `list` is not still reachable as `commands`. An alias may
     * not shadow a different command's name or alias.
     *
     * @throws \InvalidArgumentException when an alias belongs to another command
     */
    public function register(CommandDescriptor $descriptor): void
    {
        $replaced = $this->descriptors[$descriptor->name] ?? null;

        if ($replaced !== null && $replaced->name === $descriptor->name) {
            $this->descriptors = array_filter(
                $this->descriptors,
                static fn(CommandDescriptor $d): bool => $d !== $replaced,
            );
        }

        foreach ($descriptor->aliases as $alias) {
            $owner = $this->descriptors[$alias] ?? null;

            if ($owner !== null && $owner->name !== $descriptor->name) {
                throw new \InvalidArgumentException(
                    "Alias \"{$alias}\" of command \"{$descriptor->name}\" is already taken by \"{$owner->name}\".",
                );
            }
        }

        $this->descriptors[$descriptor->name] = $descriptor;

        foreach ($descriptor->aliases as $alias) {
            $this->descriptors[$alias] = $descriptor;
        }
    }

    /**
     * Registers an already-built command, honouring its #[AsCommand]
     * aliases and hidden flag exactly as loader-discovered commands do.
     */
    public function registerCommand(Command $command): void
    {
        $metadata = $this->readAsCommandMetadata($command::class);

        $this->register(new CommandDescriptor(
            name: $command->name,
            description: $command->description,
            className: $command::class,
            aliases: $metadata->aliases ?? [],
            hidden: $metadata->hidden ?? false,
            factory: static fn(): Command => $command,
        ));
    }

    // ── Lookup ───────────────────────────────────────────────────────────

    public function has(string $name): bool
    {
        $this->ensureLoaded();

        return isset($this->descriptors[$name]);
    }

    public function get(string $name): CommandDescriptor
    {
        $this->ensureLoaded();

        return $this->descriptors[$name]
            ?? throw ConsoleException::commandNotFound($name);
    }

    /**
     * Returns all non-alias, non-hidden descriptors, sorted by name.
     *
     * @return list<CommandDescriptor>
     */
    public function all(bool $includeHidden = false): array
    {
        $this->ensureLoaded();

        $unique = [];
        foreach ($this->descriptors as $descriptor) {
            $unique[$descriptor->name] = $descriptor;
        }

        $visible = $includeHidden
            ? array_values($unique)
            : array_values(array_filter($unique, static fn(CommandDescriptor $d): bool => !$d->hidden));

        usort($visible, static fn(CommandDescriptor $a, CommandDescriptor $b): int => strcmp($a->name, $b->name));

        return $visible;
    }

    // ── Lazy load ────────────────────────────────────────────────────────

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $name => $className) {
                $metadata = $this->readAsCommandMetadata($className);

                $descriptor = new CommandDescriptor(
                    name: $name,
                    description: $metadata->description ?? '',
                    className: $className,
                    aliases: $metadata->aliases ?? [],
                    hidden: $metadata->hidden ?? false,
                );

                $this->register($descriptor);
            }
        }

        $this->loaded = true;
    }

    private function readAsCommandMetadata(string $className): ?AsCommand
    {
        if (!class_exists($className)) {
            return null;
        }

        $attributes = (new \ReflectionClass($className))
            ->getAttributes(AsCommand::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
