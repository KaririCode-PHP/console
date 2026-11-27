<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Loader;

use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Core\CommandRegistry;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Integration\ClassDiscoveryBridge;
use KaririCode\Console\Loader\AttributeCommandLoader;
use KaririCode\Console\Tests\Fixture\Loader\AnnotatedButNotACommand;
use KaririCode\Console\Tests\Fixture\Loader\ClassConstantInAttributeCommand;
use KaririCode\Console\ValueObject\CommandDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeCommandLoader::class)]
#[CoversClass(ClassDiscoveryBridge::class)]
#[CoversClass(CommandRegistry::class)]
#[CoversClass(AsCommand::class)]
final class AttributeCommandLoaderTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../Fixture/Loader';

    /** Regression: a `::class` before the declaration made the file invisible. */
    public function testDiscoversCommandWhoseAttributeUsesAClassConstant(): void
    {
        $commands = (new AttributeCommandLoader([self::FIXTURES]))->load();

        self::assertSame(ClassConstantInAttributeCommand::class, $commands['report:build'] ?? null);
    }

    public function testReturnsCanonicalNamesOnlyAndSkipsNonCommands(): void
    {
        $commands = (new AttributeCommandLoader([self::FIXTURES]))->load();

        self::assertSame(['report:build'], array_keys($commands));
    }

    /** Regression: loader alias keys + registry expansion listed commands twice. */
    public function testAliasedCommandIsListedOnceAndReachableByAlias(): void
    {
        $registry = new CommandRegistry();
        $registry->addLoader(new AttributeCommandLoader([self::FIXTURES]));

        self::assertSame(['report:build'], array_map(static fn(CommandDescriptor $d) => $d->name, $registry->all()));
        self::assertSame('report:build', $registry->get('rb')->name);
    }

    public function testMissingDirectoryFailsFast(): void
    {
        $this->expectException(ConsoleException::class);

        (new AttributeCommandLoader([self::FIXTURES . '/nope']))->load();
    }

    public function testDiscoveryBridgeAlsoSkipsNonCommandsAndAliasKeys(): void
    {
        $bridge = new ClassDiscoveryBridge(static fn(string $attribute): array => [
            ClassConstantInAttributeCommand::class,
            AnnotatedButNotACommand::class,
        ]);

        self::assertSame(['report:build' => ClassConstantInAttributeCommand::class], $bridge->load());
        self::assertTrue($bridge->isWarm());
    }
}
