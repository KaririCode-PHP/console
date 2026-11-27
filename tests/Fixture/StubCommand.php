<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Fixture;

use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\ExitCode;

/**
 * Configurable command for tests: declare input and behaviour inline.
 */
final class StubCommand implements Command
{
    public int $executions = 0;

    public ?Input $lastInput = null;

    /**
     * @param (\Closure(InputDefinition): void)|null   $define
     * @param (\Closure(Input, Output): ExitCode)|null $body
     */
    public function __construct(
        public string $name = 'stub',
        public string $description = 'Stub command',
        private readonly ?\Closure $define = null,
        private readonly ?\Closure $body = null,
    ) {}

    public function configure(InputDefinition $definition): void
    {
        if ($this->define !== null) {
            ($this->define)($definition);
        }
    }

    public function execute(Input $input, Output $output): ExitCode
    {
        $this->executions++;
        $this->lastInput = $input;

        return $this->body !== null ? ($this->body)($input, $output) : ExitCode::Success;
    }
}
