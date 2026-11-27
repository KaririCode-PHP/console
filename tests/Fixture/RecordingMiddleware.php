<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Fixture;

use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Contract\CommandMiddleware;
use KaririCode\Console\ValueObject\CommandResult;

/**
 * Middleware that records "<label>-before" / "<label>-after" into a
 * (possibly shared) log, to assert pipeline ordering.
 */
final readonly class RecordingMiddleware implements CommandMiddleware
{
    /** @param \ArrayObject<int, string> $log */
    public function __construct(
        private string $label,
        public \ArrayObject $log = new \ArrayObject(),
    ) {}

    public function handle(CommandContext $context, callable $next): CommandResult
    {
        $this->log[] = "{$this->label}-before";
        $result      = $next($context);
        $this->log[] = "{$this->label}-after";

        return $result;
    }
}
