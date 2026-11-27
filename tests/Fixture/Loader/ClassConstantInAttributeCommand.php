<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Fixture\Loader;

use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\ExitCode;

// The `::class` below precedes the declaration: the 4.2 loader mistook it
// for the class keyword and skipped this file.
#[AsCommand('report:build', description: \stdClass::class, aliases: ['rb'])]
final class ClassConstantInAttributeCommand implements Command
{
    public string $name {
        get => 'report:build';
    }

    public string $description {
        get => 'Builds a report';
    }

    public function configure(InputDefinition $definition): void {}

    public function execute(Input $input, Output $output): ExitCode
    {
        return ExitCode::Success;
    }
}
