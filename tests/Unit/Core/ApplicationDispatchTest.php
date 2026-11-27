<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Core;

use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Core\Application;
use KaririCode\Console\Core\CommandRegistry;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Output\BufferedOutput;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\CommandDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
final class ApplicationDispatchTest extends TestCase
{
    // ── Fixtures ─────────────────────────────────────────────────────────

    private function appWith(Command $command): Application
    {
        $registry = new CommandRegistry();
        $registry->register(new CommandDescriptor(
            name: $command->name,
            description: $command->description,
            className: StubCommand::class,
            factory: static fn() => $command,
        ));

        return new Application($registry);
    }

    // ── Command-name stripping (regression: name must not become arg[0]) ──

    public function testDispatchStripsCommandNameFromPositionalArguments(): void
    {
        $command = new class implements Command {
            public ?string $receivedName = null;

            public string $name = 'greet';
            public string $description = '';

            public function configure(InputDefinition $definition): void
            {
                $definition->addArgument(new ArgumentDescriptor('name', 'Name', required: true));
            }

            public function execute(Input $input, Output $output): ExitCode
            {
                $name               = $input->getArgument('name');
                $this->receivedName = is_string($name) ? $name : '';
                return ExitCode::Success;
            }
        };

        $this->appWith($command)->dispatch(new ArgvInput(['greet', 'World']), new BufferedOutput());

        self::assertSame('World', $command->receivedName);
    }

    public function testDispatchDefaultsToListCommand(): void
    {
        $command = new class implements Command {
            public bool $ran = false;

            public string $name = 'list';
            public string $description = '';
            public function configure(InputDefinition $definition): void {}

            public function execute(Input $input, Output $output): ExitCode
            {
                $this->ran = true;
                return ExitCode::Success;
            }
        };

        $this->appWith($command)->dispatch(new ArgvInput([]), new BufferedOutput());

        self::assertTrue($command->ran, 'empty argv must dispatch to the default "list" command');
    }

    // ── Global verbosity flags ───────────────────────────────────────────

    public function testVerboseFlagRaisesOutputVerbosity(): void
    {
        $command = new class implements Command {
            public string $name = 'noisy';
            public string $description = '';
            public function configure(InputDefinition $definition): void {}

            public function execute(Input $input, Output $output): ExitCode
            {
                $output->writeln('verbose detail', Verbosity::Verbose);
                return ExitCode::Success;
            }
        };

        $quiet = new BufferedOutput();
        $this->appWith($command)->dispatch(new ArgvInput(['noisy']), $quiet);
        self::assertStringNotContainsString('verbose detail', $quiet->buffer);

        $verbose = new BufferedOutput();
        $this->appWith($command)->dispatch(new ArgvInput(['noisy', '-v']), $verbose);
        self::assertStringContainsString('verbose detail', $verbose->buffer);
    }

    public function testGlobalFlagIsNotPassedToCommandParser(): void
    {
        // The command declares NO -v option; dispatch must consume it globally
        // rather than letting ArgvInput reject it as an unknown shortcut.
        $command = new class implements Command {
            public string $name = 'plain';
            public string $description = '';
            public function configure(InputDefinition $definition): void {}

            public function execute(Input $input, Output $output): ExitCode
            {
                return ExitCode::Success;
            }
        };

        $exit = $this->appWith($command)->dispatch(new ArgvInput(['plain', '-vvv']), new BufferedOutput());

        self::assertSame(0, $exit);
    }

    public function testNoInteractionFlagDisablesInteractivity(): void
    {
        $command = new class implements Command {
            public ?bool $interactive = null;

            public string $name = 'ask';
            public string $description = '';
            public function configure(InputDefinition $definition): void {}

            public function execute(Input $input, Output $output): ExitCode
            {
                $this->interactive = $input->interactive;
                return ExitCode::Success;
            }
        };

        $this->appWith($command)->dispatch(
            new ArgvInput(['ask', '--no-interaction'], interactive: true),
            new BufferedOutput(),
        );

        self::assertFalse($command->interactive);
    }

    // ── Throwable handling (no PHP fatal escapes dispatch) ───────────────

    public function testUncaughtThrowableBecomesFailureExitCode(): void
    {
        $command = new class implements Command {
            public string $name = 'boom';
            public string $description = '';
            public function configure(InputDefinition $definition): void {}

            public function execute(Input $input, Output $output): ExitCode
            {
                throw new \RuntimeException('kernel panic');
            }
        };

        $output = new BufferedOutput();
        $exit   = $this->appWith($command)->dispatch(new ArgvInput(['boom']), $output);

        self::assertSame(1, $exit);
        self::assertStringContainsString('kernel panic', $output->buffer);
    }
}
