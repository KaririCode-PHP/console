<?php

declare(strict_types=1);

namespace KaririCode\Console\Integration;

/**
 * Typed, lazy view over the application's 'console' configuration.
 *
 * The reader closure runs once, on first access — not at construction —
 * so building the service graph never triggers configuration I/O. This
 * lazy resolution is why these are methods and not property hooks: Spec
 * v4.3.3 §11 forbids I/O and lazy initialisation inside hooks.
 *
 * Recognised keys:
 *   name        string        application name        (default "KaririCode Console")
 *   version     string        application version     (default "UNKNOWN")
 *   scan_paths  list<string>  directories for #[AsCommand] discovery
 *   debug       bool          free-form flag for the host application
 *
 * Side effects: calls the configuration reader on first access.
 *
 * @package KaririCode\Console\Integration
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class ConfiguratorBridge
{
    /** @var array<string, mixed>|null */
    private ?array $config = null;

    /**
     * @param \Closure(): array<string, mixed> $configReader Returns the 'console' config array.
     */
    public function __construct(private readonly \Closure $configReader) {}

    // ── Typed accessors ──────────────────────────────────────────────────

    public function getApplicationName(): string
    {
        /** @var mixed $name Untyped configuration value; narrowed below. */
        $name = $this->resolveConfig()['name'] ?? null;

        return is_string($name) ? $name : 'KaririCode Console';
    }

    public function getApplicationVersion(): string
    {
        /** @var mixed $version Untyped configuration value; narrowed below. */
        $version = $this->resolveConfig()['version'] ?? null;

        return is_string($version) ? $version : 'UNKNOWN';
    }

    /** @return list<string> */
    public function getScanPaths(): array
    {
        /** @var mixed $paths Untyped configuration value; narrowed below. */
        $paths = $this->resolveConfig()['scan_paths'] ?? [];

        if (!is_array($paths)) {
            return [];
        }

        /** @var list<string> $strings array_filter(is_string) keeps only strings */
        $strings = array_values(array_filter($paths, is_string(...)));

        return $strings;
    }

    public function isDebug(): bool
    {
        return (bool) ($this->resolveConfig()['debug'] ?? false);
    }

    // ── Lazy resolution ──────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function resolveConfig(): array
    {
        return $this->config ??= ($this->configReader)();
    }
}
