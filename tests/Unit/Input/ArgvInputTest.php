<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Input;

use KaririCode\Console\Core\ConcreteInputDefinition;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArgvInput::class)]
final class ArgvInputTest extends TestCase
{
    // ── Positional arguments ──────────────────────────────────────────

    public function testParsesPositionalArgument(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addArgument(new ArgumentDescriptor('name', 'User name'));

        $input = new ArgvInput(['walmir']);
        $input->parse($definition);

        self::assertSame('walmir', $input->getArgument('name'));
    }

    public function testThrowsOnMissingRequiredArgument(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addArgument(new ArgumentDescriptor('name', 'Required'));

        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9005);

        $input = new ArgvInput([]);
        $input->parse($definition);
    }

    // ── Long options ──────────────────────────────────────────────────

    public function testParsesLongOptionWithEquals(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addOption(new OptionDescriptor('format', 'Format'));

        $input = new ArgvInput(['--format=json']);
        $input->parse($definition);

        self::assertSame('json', $input->getOption('format'));
    }

    public function testParsesLongOptionWithSeparateValue(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addOption(new OptionDescriptor('output', 'Output path'));

        $input = new ArgvInput(['--output', '/tmp/result.txt']);
        $input->parse($definition);

        self::assertSame('/tmp/result.txt', $input->getOption('output'));
    }

    public function testParsesBooleanFlag(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addOption(new OptionDescriptor('verbose', 'Verbose mode', acceptsValue: false));

        $input = new ArgvInput(['--verbose']);
        $input->parse($definition);

        self::assertTrue($input->getOption('verbose'));
    }

    // ── End-of-options sentinel ───────────────────────────────────────

    public function testEndOfOptionsSentinelTreatsRemainingTokensAsArguments(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addArgument(new ArgumentDescriptor('script', 'Script name'));

        $input = new ArgvInput(['--', '--not-an-option']);
        $input->parse($definition);

        self::assertSame('--not-an-option', $input->getArgument('script'));
    }

    // ── Option default ────────────────────────────────────────────────

    public function testOptionDefaultIsAppliedWhenAbsent(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addOption(new OptionDescriptor('env', 'Environment', default: 'production'));

        $input = new ArgvInput([]);
        $input->parse($definition);

        self::assertSame('production', $input->getOption('env'));
    }

    // ── Unknown option ────────────────────────────────────────────────

    public function testThrowsOnUnknownLongOption(): void
    {
        $definition = new ConcreteInputDefinition();

        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9004);

        $input = new ArgvInput(['--ghost']);
        $input->parse($definition);
    }

    // ── Array argument (regression: greedy capture) ───────────────────

    public function testArrayArgumentCapturesAllTokens(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addArgument(new ArgumentDescriptor('files', 'Files', required: false, isArray: true));

        $input = new ArgvInput(['a.php', 'b.php', 'c.php']);
        $input->parse($definition);

        self::assertSame(['a.php', 'b.php', 'c.php'], $input->getArgument('files'));
    }

    public function testArrayArgumentFollowsScalarArgument(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addArgument(new ArgumentDescriptor('command', 'Command'));
        $definition->addArgument(new ArgumentDescriptor('args', 'Args', required: false, isArray: true));

        $input = new ArgvInput(['build', 'x', 'y', 'z']);
        $input->parse($definition);

        self::assertSame('build', $input->getArgument('command'));
        self::assertSame(['x', 'y', 'z'], $input->getArgument('args'));
    }

    // ── Argument default (regression: parity with options) ────────────

    public function testOptionalArgumentDefaultIsApplied(): void
    {
        $definition = new ConcreteInputDefinition();
        $definition->addArgument(new ArgumentDescriptor('env', 'Environment', required: false, default: 'production'));

        $input = new ArgvInput([]);
        $input->parse($definition);

        self::assertSame('production', $input->getArgument('env'));
    }
}
