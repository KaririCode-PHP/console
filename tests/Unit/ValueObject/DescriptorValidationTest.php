<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\ValueObject;

use KaririCode\Console\Core\ConcreteInputDefinition;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\CommandDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Definitions fail at configure() time, not later as confusing parse errors.
 */
#[CoversClass(OptionDescriptor::class)]
#[CoversClass(ArgumentDescriptor::class)]
#[CoversClass(ConcreteInputDefinition::class)]
#[CoversClass(CommandDescriptor::class)]
final class DescriptorValidationTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): mixed, int}> */
    public static function invalidDeclarations(): iterable
    {
        yield 'option name with space'      => [static fn() => new OptionDescriptor('dry run', ''), 9004];
        yield 'option name with dashes'     => [static fn() => new OptionDescriptor('--force', ''), 9004];
        yield 'two-letter shortcut'         => [static fn() => new OptionDescriptor('force', '', shortcut: 'fo'), 9004];
        yield 'array flag'                  => [static fn() => new OptionDescriptor('v', '', acceptsValue: false, isArray: true), 9004];
        yield 'flag with truthy default'    => [static fn() => new OptionDescriptor('f', '', default: true, acceptsValue: false), 9004];
        yield 'array option scalar default' => [static fn() => new OptionDescriptor('tag', '', default: 'a', isArray: true), 9004];
        yield 'required arg with default'   => [static fn() => new ArgumentDescriptor('name', '', required: true, default: 'x'), 9003];
        yield 'array arg scalar default'    => [static fn() => new ArgumentDescriptor('files', '', required: false, default: 'a', isArray: true), 9003];
        yield 'empty argument name'         => [static fn() => new ArgumentDescriptor('', ''), 9003];
    }

    #[DataProvider('invalidDeclarations')]
    public function testRejectsContradictoryDescriptors(\Closure $declare, int $code): void
    {
        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode($code);

        $declare();
    }

    public function testRejectsDuplicateArgumentNames(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addArgument(new ArgumentDescriptor('name', ''));

        $this->expectExceptionCode(9003);

        $definition->addArgument(new ArgumentDescriptor('name', ''));
    }

    /** Regression: the second option with the same shortcut was unreachable. */
    public function testRejectsDuplicateShortcuts(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addOption(new OptionDescriptor('force', '', shortcut: 'f'));

        $this->expectExceptionCode(9004);
        $this->expectExceptionMessage('already used by --force');

        $definition->addOption(new OptionDescriptor('file', '', shortcut: 'f'));
    }

    public function testExposesDeclarationsAsOrderedLists(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addArgument(new ArgumentDescriptor('a', ''));
        $definition->addOption(new OptionDescriptor('z', ''));
        $definition->addOption(new OptionDescriptor('b', ''));

        self::assertSame(['a'], array_map(static fn(ArgumentDescriptor $d) => $d->name, $definition->arguments));
        self::assertSame(['z', 'b'], array_map(static fn(OptionDescriptor $d) => $d->name, $definition->options));
    }

    /** Names are spliced into completion scripts, so only inert characters are allowed. */
    #[Group('security')]
    public function testCommandNamesAndAliasesAreAllowListed(): void
    {
        self::assertSame('cache:warm', (new CommandDescriptor('cache:warm', '', \stdClass::class, aliases: ['cw', 'warm-up']))->name);

        foreach (['deploy $(id)', 'a;b', '-dash', 'quo"te'] as $name) {
            try {
                new CommandDescriptor('ok', '', \stdClass::class, aliases: [$name]);
                self::fail("accepted alias {$name}");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
