<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Provider;

use KaririCode\Console\Core\Application;
use KaririCode\Console\Core\CommandRegistry;
use KaririCode\Console\Integration\ConfiguratorBridge;
use KaririCode\Console\Provider\ConsoleConfigProvider;
use KaririCode\Console\Provider\ConsoleServiceProvider;
use KaririCode\Console\Tests\Fixture\RecordingContainer;
use KaririCode\Contract\Configuration\ConfigProvider;
use KaririCode\Contract\DI\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsoleServiceProvider::class)]
#[CoversClass(ConsoleConfigProvider::class)]
#[CoversClass(ConfiguratorBridge::class)]
#[CoversClass(Application::class)]
final class ConsoleProvidersTest extends TestCase
{
    public function testServiceProviderImplementsTheContract(): void
    {
        self::assertInstanceOf(ServiceProvider::class, new ConsoleServiceProvider());
        self::assertInstanceOf(ConfigProvider::class, new ConsoleConfigProvider());
    }

    public function testRegisterBindsApplicationAsALazySingleton(): void
    {
        $reads = 0;
        $configurator = new ConfiguratorBridge(static function () use (&$reads): array {
            $reads++;

            return ['name' => 'di-app', 'version' => '2.0'];
        });
        $container = new RecordingContainer();

        (new ConsoleServiceProvider(configurator: $configurator))->register($container);

        self::assertSame([Application::class], array_keys($container->bindings));
        self::assertTrue($container->bindings[Application::class]['singleton']);
        self::assertSame(0, $reads, 'register() binds and never resolves');

        $first = $container->get(Application::class);
        $second = $container->get(Application::class);

        self::assertInstanceOf(Application::class, $first);
        self::assertSame($first, $second);
        self::assertSame(['di-app', '2.0'], [$first->name, $first->version]);
        self::assertTrue($first->registry->has('list'), 'the built-in commands are registered');
        self::assertSame(1, $reads);
    }

    public function testBootHasNothingToWire(): void
    {
        $container = new RecordingContainer();
        $provider = new ConsoleServiceProvider();
        $provider->register($container);
        $provider->boot();

        self::assertSame([Application::class], array_keys($container->bindings));
        self::assertFalse($container->has(CommandRegistry::class));
    }

    public function testConfigProviderDeclaresTheKeysConsoleReads(): void
    {
        $provider = new ConsoleConfigProvider();

        self::assertSame('console', $provider->namespace());
        self::assertSame(
            ['name' => 'KaririCode Console', 'version' => 'UNKNOWN', 'scan_paths' => [], 'debug' => false],
            $provider->defaults(),
        );
        self::assertSame(
            ['name' => 'string', 'version' => 'string', 'scan_paths' => 'array', 'debug' => 'bool'],
            $provider->schema(),
        );
        self::assertSame(array_keys($provider->defaults()), array_keys($provider->schema()));
    }

    public function testExplicitNameAndVersionWinOverConfiguration(): void
    {
        $configurator = new ConfiguratorBridge(static fn(): array => ['name' => 'cfg', 'version' => '1.0']);

        $app = (new ConsoleServiceProvider(configurator: $configurator))->build('explicit', '3.0');

        self::assertSame(['explicit', '3.0'], [$app->name, $app->version]);
    }

    public function testEveryBuiltInCommandIsRegistered(): void
    {
        $app = ConsoleServiceProvider::buildApplication();

        foreach (['help', 'list', 'completion'] as $name) {
            self::assertTrue($app->registry->has($name), $name);
        }
    }

    public function testBridgeKeepsOnlyStringScanPathsAsAList(): void
    {
        $bridge = new ConfiguratorBridge(static fn(): array => ['scan_paths' => ['src/A', 42, 'src/B', null], 'debug' => true]);

        self::assertSame(['src/A', 'src/B'], $bridge->getScanPaths());
        self::assertTrue($bridge->isDebug());
    }

    public function testDefaultsMatchWhatTheBridgeAndApplicationFallBackTo(): void
    {
        $defaults = (new ConsoleConfigProvider())->defaults();
        $bridge = new ConfiguratorBridge(static fn(): array => []);
        $application = new Application(new CommandRegistry());
        $built = ConsoleServiceProvider::buildApplication();

        self::assertSame($defaults['name'], $bridge->getApplicationName());
        self::assertSame($defaults['version'], $bridge->getApplicationVersion());
        self::assertSame($defaults['scan_paths'], $bridge->getScanPaths());
        self::assertSame($defaults['debug'], $bridge->isDebug());
        self::assertSame([$defaults['name'], $defaults['version']], [$application->name, $application->version]);
        self::assertSame([$defaults['name'], $defaults['version']], [$built->name, $built->version]);
    }
}
