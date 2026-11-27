<?php

declare(strict_types=1);

namespace KaririCode\Console\Core;

use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\ValueObject\TraceId;

/**
 * Immutable per-dispatch context (Value Object, Spec v4.3.3 §5.2).
 *
 *   $context = new ConcreteCommandContext(
 *       commandName: 'deploy', input: $input, output: $output,
 *       command: $command, traceId: TraceId::generate(),
 *   );
 *
 * @package KaririCode\Console\Core
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
final readonly class ConcreteCommandContext implements CommandContext
{
    public function __construct(
        public string $commandName,
        public Input $input,
        public Output $output,
        public Command $command,
        public TraceId $traceId,
    ) {}
}
