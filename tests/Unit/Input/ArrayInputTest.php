<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Input;

use KaririCode\Console\Core\ConcreteInputDefinition;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Input\ArrayInput;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayInput::class)]
final class ArrayInputTest extends TestCase
{
    /**
     * The point of ArrayInput: a command tested with it must see exactly
     * what ArgvInput produces from the equivalent command line.
     */
    public function testProducesTheSameStateAsArgvInput(): void
    {
        $argv  = new ArgvInput(['deploy', '--tag', 'a', '--tag', 'b', '--force'], interactive: false);
        $array = new ArrayInput(['env' => 'deploy'], ['tag' => ['a', 'b'], 'force' => true]);

        $argv->parse(self::definition());
        $array->parse(self::definition());

        self::assertSame($argv->arguments, $array->arguments);
        self::assertSame($argv->options, $array->options);
    }

    /** Regression: ArrayInput ignored argument defaults (ArgvInput applied them). */
    public function testAppliesArgumentAndOptionDefaults(): void
    {
        $input = self::parse(['env' => 'prod']);

        self::assertSame('eu', $input->getArgument('region'));
        self::assertSame('text', $input->getOption('format'));
        self::assertNull($input->getOption('force'));
    }

    /** @return iterable<string, array{array<string, mixed>, array<string, mixed>, int}> */
    public static function rejectedInput(): iterable
    {
        yield 'unknown option'    => [['env' => 'x'], ['nope' => 1], 9004];
        yield 'unknown argument'  => [['env' => 'x', 'nope' => 1], [], 9003];
        yield 'missing required'  => [[], [], 9005];
        yield 'non-boolean flag'  => [['env' => 'x'], ['force' => 'maybe'], 9004];
    }

    /**
     * Regression: unknown names were accepted silently.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $options
     */
    #[DataProvider('rejectedInput')]
    public function testRejectsWhatArgvInputRejects(array $arguments, array $options, int $code): void
    {
        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode($code);

        self::parse($arguments, $options);
    }

    /** Regression: (bool) 'false' is true in PHP. */
    public function testFlagStringsAreParsedStrictly(): void
    {
        self::assertFalse(self::parse(['env' => 'x'], ['force' => 'false'])->getOption('force'));
        self::assertTrue(self::parse(['env' => 'x'], ['force' => 'yes'])->getOption('force'));
    }

    public function testArrayOptionWrapsAScalarAndDashedKeysAreAccepted(): void
    {
        self::assertSame(['a'], self::parse(['env' => 'x'], ['--tag' => 'a'])->getOption('tag'));
    }

    public function testHasNoRawTokensAndNoValuesBeforeParse(): void
    {
        $input = new ArrayInput(['env' => 'x']);

        self::assertSame([], $input->tokens);
        self::assertNull($input->getArgument('env'));
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $options
     */
    private static function parse(array $arguments, array $options = []): ArrayInput
    {
        $input = new ArrayInput($arguments, $options);
        $input->parse(self::definition());

        return $input;
    }

    private static function definition(): ConcreteInputDefinition
    {
        $definition = new ConcreteInputDefinition();
        $definition->addArgument(new ArgumentDescriptor('env', 'Target environment'));
        $definition->addArgument(new ArgumentDescriptor('region', 'Region', required: false, default: 'eu'));
        $definition->addOption(new OptionDescriptor('tag', 'Tags', isArray: true));
        $definition->addOption(new OptionDescriptor('force', 'Force', acceptsValue: false));
        $definition->addOption(new OptionDescriptor('format', 'Format', default: 'text'));

        return $definition;
    }
}
