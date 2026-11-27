<?php

declare(strict_types=1);

namespace KaririCode\Console\Loader;

use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\CommandLoader;

/**
 * Loads commands from an explicit name → FQCN map.
 *
 * Use when attribute scanning is too expensive (e.g., large vendor
 * trees) or when deterministic ordering is required for tests.
 *
 *   $registry->addLoader(new ExplicitCommandLoader(['cache:warm' => WarmCommand::class]));
 *
 * @package KaririCode\Console\Loader
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class ExplicitCommandLoader implements CommandLoader
{
    /**
     * @param array<string, class-string<Command>> $map  name → FQCN
     */
    public function __construct(private readonly array $map) {}

    #[\Override]
    public function load(): array
    {
        return $this->map;
    }

    #[\Override]
    public function isWarm(): bool
    {
        return true;
    }
}
