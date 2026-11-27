<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Core;

use KaririCode\Console\Core\CommandRegistry;
use KaririCode\Console\Exception\CommandNotFoundException;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\CommandDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommandRegistry::class)]
final class CommandRegistryTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
    }

    public function testRegisterAndRetrieveDescriptor(): void
    {
        $descriptor = new CommandDescriptor(
            name: 'greet',
            description: 'Say hello',
            className: StubCommand::class,
        );

        $this->registry->register($descriptor);

        self::assertTrue($this->registry->has('greet'));
        self::assertSame($descriptor, $this->registry->get('greet'));
    }

    public function testAliasesAreResolvedToSameDescriptor(): void
    {
        $descriptor = new CommandDescriptor(
            name: 'list',
            description: 'List commands',
            className: StubCommand::class,
            aliases: ['commands', 'ls'],
        );

        $this->registry->register($descriptor);

        self::assertTrue($this->registry->has('commands'));
        self::assertTrue($this->registry->has('ls'));
        self::assertSame($descriptor, $this->registry->get('ls'));
    }

    public function testGetThrowsOnUnknownCommand(): void
    {
        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionCode(9001);

        $this->registry->get('unknown:command');
    }

    public function testAllExcludesHiddenByDefault(): void
    {
        $this->registry->register(new CommandDescriptor(
            name: 'visible',
            description: 'Visible command',
            className: StubCommand::class,
        ));

        $this->registry->register(new CommandDescriptor(
            name: 'hidden',
            description: 'Hidden command',
            className: StubCommand::class,
            hidden: true,
        ));

        $all = $this->registry->all();

        self::assertCount(1, $all);
        self::assertSame('visible', $all[0]->name);
    }

    public function testAllIncludesHiddenWhenRequested(): void
    {
        $this->registry->register(new CommandDescriptor(
            name: 'hidden',
            description: 'Hidden',
            className: StubCommand::class,
            hidden: true,
        ));

        self::assertCount(1, $this->registry->all(includeHidden: true));
    }

    public function testAllResultIsSortedByName(): void
    {
        foreach (['zebra', 'apple', 'mango'] as $name) {
            $this->registry->register(new CommandDescriptor($name, '', StubCommand::class));
        }

        $names = array_map(fn($d) => $d->name, $this->registry->all());

        self::assertSame(['apple', 'mango', 'zebra'], $names);
    }

    /** Regression: an overridden command stayed reachable through its old aliases. */
    public function testReplacingACommandDropsItsOldAliases(): void
    {
        $registry = new CommandRegistry();
        $registry->register(new CommandDescriptor('list', 'built-in', \stdClass::class, aliases: ['commands']));
        $registry->register(new CommandDescriptor('list', 'custom', \ArrayObject::class));

        self::assertSame('custom', $registry->get('list')->description);
        self::assertFalse($registry->has('commands'));
    }

    public function testAnAliasCannotShadowAnotherCommand(): void
    {
        $registry = new CommandRegistry();
        $registry->register(new CommandDescriptor('list', 'built-in', \stdClass::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already taken by "list"');

        $registry->register(new CommandDescriptor('deploy', 'x', \stdClass::class, aliases: ['list']));
    }

    public function testReRegisteringTheSameCommandIsIdempotent(): void
    {
        $registry = new CommandRegistry();
        $descriptor = new CommandDescriptor('cache:warm', 'x', \stdClass::class, aliases: ['warm']);
        $registry->register($descriptor);
        $registry->register($descriptor);

        self::assertCount(1, $registry->all());
        self::assertSame('cache:warm', $registry->get('warm')->name);
    }
}
