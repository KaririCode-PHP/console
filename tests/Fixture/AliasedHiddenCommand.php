<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Fixture;

use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\ExitCode;

#[AsCommand('cache:warm', description: 'Warms the cache', aliases: ['warm'], hidden: true)]
final class AliasedHiddenCommand implements Command
{
    public string $name {
        get => 'cache:warm';
    }

    public string $description {
        get => 'Warms the cache';
    }

    public function configure(InputDefinition $definition): void {}

    public function execute(Input $input, Output $output): ExitCode
    {
        return ExitCode::Success;
    }
}
