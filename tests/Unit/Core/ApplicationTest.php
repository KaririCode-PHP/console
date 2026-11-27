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
use KaririCode\Console\Input\ArrayInput;
use KaririCode\Console\Output\BufferedOutput;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\CommandDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    // ── Fixtures ──────────────────────────────────────────────────────

    private function makeGreetDescriptor(): CommandDescriptor
    {
        return new CommandDescriptor(
            name: 'greet',
            description: 'Greet the user',
            className: StubCommand::class,
            factory: static function (): Command {
                return new class implements Command {
                    public string $name = 'greet';
                    public string $description = 'Greet';
                    public function configure(InputDefinition $definition): void {}
                    public function execute(Input $input, Output $output): ExitCode
                    {
                        $output->writeln('Hello!');
                        return ExitCode::Success;
                    }
                };
            },
        );
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function testApplicationExposesNameAndVersion(): void
    {
        $app = new Application(new CommandRegistry(), 'Test App', '4.0.0');

        self::assertSame('Test App', $app->name);
        self::assertSame('4.0.0', $app->version);
    }

    public function testRunDispatchesCommandViaFactoryAndReturnsSuccess(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->makeGreetDescriptor());

        $app = new Application($registry);
        $output = new BufferedOutput();

        $result = $app->run('greet', new ArrayInput(), $output);

        self::assertTrue($result->isSuccess());
        self::assertSame('greet', $result->commandName);
        self::assertStringContainsString('Hello!', $output->buffer);
    }

    public function testRunReturnsFailureForUnregisteredCommand(): void
    {
        $app = new Application(new CommandRegistry());

        $result = $app->run('missing:command', new ArrayInput(), new BufferedOutput());

        self::assertFalse($result->isSuccess());
        self::assertSame(1, $result->toExitInt());
        self::assertSame('missing:command', $result->commandName);
    }

    public function testCommandResultCarriesElapsedTime(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->makeGreetDescriptor());

        $result = (new Application($registry))->run('greet', new ArrayInput(), new BufferedOutput());

        self::assertGreaterThanOrEqual(0.0, $result->durationMs);
    }
}
