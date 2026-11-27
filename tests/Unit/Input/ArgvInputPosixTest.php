<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Input;

use KaririCode\Console\Core\ConcreteInputDefinition;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * POSIX utility-syntax behaviours (IEEE Std 1003.1-2017 XBD §12.2).
 */
#[CoversClass(ArgvInput::class)]
final class ArgvInputPosixTest extends TestCase
{
    public function testGroupedFlagsAreEachSet(): void
    {
        $input = self::parse(['-abc']);

        self::assertTrue($input->getOption('all'));
        self::assertTrue($input->getOption('brief'));
        self::assertTrue($input->getOption('color'));
    }

    /** Regression: "-xyz" set only -x and silently dropped "yz". */
    public function testGroupMayEndWithAnOptionThatTakesTheRestAsItsValue(): void
    {
        self::assertSame('out.txt', self::parse(['-abo', 'out.txt'])->getOption('output'));
        self::assertSame('out.txt', self::parse(['-aboout.txt'])->getOption('output'));
        self::assertTrue(self::parse(['-aboout.txt'])->getOption('brief'));
    }

    /** Regression: the short form overwrote array options instead of appending. */
    public function testArrayOptionAccumulatesAcrossShortAndLongForms(): void
    {
        self::assertSame(['a', 'b', 'c'], self::parse(['-I', 'a', '--include', 'b', '-Ic'])->getOption('include'));
    }

    public function testLoneDashIsAnOperand(): void
    {
        self::assertSame('-', self::parse(['-'])->getArgument('file'));
    }

    public function testReparsingStartsFromAFreshState(): void
    {
        $input = new ArgvInput(['-a'], interactive: false);
        $input->parse(self::definition());

        $other = new ConcreteInputDefinition();
        $other->addOption(new OptionDescriptor('all', '', shortcut: 'a', acceptsValue: false));
        $input->parse($other);

        self::assertSame(['all' => true], $input->options);
    }

    /** @return iterable<string, array{list<string>, int}> */
    public static function rejectedTokens(): iterable
    {
        yield 'value given to a flag' => [['--all=yes'], 9004];
        yield 'unknown shortcut'      => [['-z'], 9004];
        yield 'value missing at end'  => [['--output'], 9004];
        yield 'value missing in group' => [['-ao'], 9004];
        yield 'surplus operand'       => [['one', 'two'], 9003];
    }

    /** @param list<string> $tokens */
    #[DataProvider('rejectedTokens')]
    public function testRejectsInsteadOfSilentlyIgnoring(array $tokens, int $code): void
    {
        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode($code);

        self::parse($tokens);
    }

    /** @param list<string> $tokens */
    private static function parse(array $tokens): ArgvInput
    {
        $input = new ArgvInput($tokens, interactive: false);
        $input->parse(self::definition());

        return $input;
    }

    private static function definition(): ConcreteInputDefinition
    {
        $definition = new ConcreteInputDefinition();
        $definition->addArgument(new ArgumentDescriptor('file', 'Input file', required: false));
        $definition->addOption(new OptionDescriptor('all', '', shortcut: 'a', acceptsValue: false));
        $definition->addOption(new OptionDescriptor('brief', '', shortcut: 'b', acceptsValue: false));
        $definition->addOption(new OptionDescriptor('color', '', shortcut: 'c', acceptsValue: false));
        $definition->addOption(new OptionDescriptor('output', '', shortcut: 'o'));
        $definition->addOption(new OptionDescriptor('include', '', shortcut: 'I', isArray: true));

        return $definition;
    }
}
