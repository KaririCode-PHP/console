<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Core;

use KaririCode\Console\Core\Application;
use KaririCode\Console\Core\CommandRegistry;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Input\ArrayInput;
use KaririCode\Console\Provider\ConsoleServiceProvider;
use KaririCode\Console\Loader\ExplicitCommandLoader;
use KaririCode\Console\Output\BufferedOutput;
use KaririCode\Console\Tests\Fixture\AliasedHiddenCommand;
use KaririCode\Console\Tests\Fixture\Loader\ClassConstantInAttributeCommand;
use KaririCode\Console\Tests\Fixture\SplitOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Commands registered by class name (every loader) are built by
 * Application::instantiate() — a path no earlier test executed.
 */
#[CoversClass(Application::class)]
#[CoversClass(ExplicitCommandLoader::class)]
#[CoversClass(CommandRegistry::class)]
final class LoaderInstantiationTest extends TestCase
{
    public function testLoaderRegisteredCommandIsInstantiatedAndRun(): void
    {
        $app = self::appWith(['report:build' => ClassConstantInAttributeCommand::class]);

        $result = $app->run('report:build', new ArrayInput(), new BufferedOutput());

        self::assertTrue($result->isSuccess());
    }

    /** @return iterable<string, array{string, string}> */
    public static function brokenRegistrations(): iterable
    {
        yield 'class does not exist'        => ['App\\Missing\\Command', 'class App\\Missing\\Command does not exist'];
        yield 'class is not a Command'      => [\ArrayObject::class, 'ArrayObject does not implement'];
    }

    /** Regression: a missing class was reported as `Command "App\X" is not registered`. */
    #[\PHPUnit\Framework\Attributes\DataProvider('brokenRegistrations')]
    public function testBrokenRegistrationFailsAgainstTheCommandName(string $className, string $reason): void
    {
        $app    = self::appWith(['broken' => $className]);
        $output = new SplitOutput();

        $status = $app->dispatch(new ArgvInput(['broken']), $output);

        self::assertSame(1, $status);
        self::assertStringContainsString('"broken"', $output->stderr->buffer);
        self::assertStringContainsString($reason, $output->stderr->buffer);
    }

    public function testHelpAndCompletionDegradeGracefullyAroundABrokenCommand(): void
    {
        $app = self::appWith(['broken' => 'App\\Missing\\Command', 'cache:warm' => AliasedHiddenCommand::class]);

        self::assertNull($app->buildDefinitionFor('broken'));

        $output = new BufferedOutput();
        self::assertSame(0, $app->dispatch(new ArgvInput(['completion', 'fish', '--program', 'app']), $output));
        self::assertStringContainsString("-a broken", $output->buffer, 'still completes the command name');

        $help = new BufferedOutput();
        self::assertSame(0, $app->dispatch(new ArgvInput(['help', 'broken']), $help));
        self::assertStringContainsString('Usage:', $help->buffer);
    }

    /** @param array<string, string> $map */
    private static function appWith(array $map): Application
    {
        $app = ConsoleServiceProvider::buildApplication('demo', '1.0');
        /** @var array<string, class-string<\KaririCode\Console\Contract\Command>> $map Deliberately unverified: some entries are broken. */
        $app->registry->addLoader(new ExplicitCommandLoader($map));

        return $app;
    }
}
