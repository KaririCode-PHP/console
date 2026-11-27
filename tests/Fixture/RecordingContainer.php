<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Fixture;

use KaririCode\Contract\DI\BindableContainer;

/**
 * Test double: a minimal BindableContainer that records bindings and builds
 * singletons on first get().
 */
final class RecordingContainer implements BindableContainer
{
    /** @var array<string, array{concrete: callable|string|null, singleton: bool}> */
    public array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    #[\Override]
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $binding = $this->bindings[$id] ?? throw new \OutOfBoundsException($id);
        $value = is_callable($binding['concrete']) ? ($binding['concrete'])($this) : $binding['concrete'];

        if ($binding['singleton']) {
            $this->instances[$id] = $value;
        }

        return $value;
    }

    #[\Override]
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || array_key_exists($id, $this->instances);
    }

    #[\Override]
    public function bind(string $abstract, callable|string|null $concrete, bool $singleton = false): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'singleton' => $singleton];
    }

    #[\Override]
    public function instance(string $abstract, mixed $value): void
    {
        $this->instances[$abstract] = $value;
    }
}
