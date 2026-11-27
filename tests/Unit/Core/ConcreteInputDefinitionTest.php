<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Core;

use KaririCode\Console\Core\ConcreteInputDefinition;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConcreteInputDefinition::class)]
final class ConcreteInputDefinitionTest extends TestCase
{
    private ConcreteInputDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new ConcreteInputDefinition();
    }

    // ── Argument ordering invariants ─────────────────────────────────────

    public function testRequiredArgumentAfterOptionalThrows(): void
    {
        $this->definition->addArgument(new ArgumentDescriptor('optional', '', required: false));

        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9003);

        $this->definition->addArgument(new ArgumentDescriptor('required', '', required: true));
    }

    public function testArgumentAfterArrayArgumentThrows(): void
    {
        $this->definition->addArgument(new ArgumentDescriptor('files', '', isArray: true));

        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9003);

        $this->definition->addArgument(new ArgumentDescriptor('trailing', ''));
    }

    public function testValidOrderingIsAccepted(): void
    {
        $this->definition->addArgument(new ArgumentDescriptor('required', '', required: true));
        $this->definition->addArgument(new ArgumentDescriptor('optional', '', required: false));
        $this->definition->addArgument(new ArgumentDescriptor('rest', '', required: false, isArray: true));

        self::assertCount(3, $this->definition->arguments);
    }

    public function testGetUnknownArgumentThrows(): void
    {
        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9003);

        $this->definition->getArgument('ghost');
    }

    // ── Option invariants ────────────────────────────────────────────────

    public function testDuplicateOptionThrows(): void
    {
        $this->definition->addOption(new OptionDescriptor('format', ''));

        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9004);

        $this->definition->addOption(new OptionDescriptor('format', ''));
    }

    public function testGetUnknownOptionThrows(): void
    {
        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9004);

        $this->definition->getOption('ghost');
    }

    public function testHasArgumentAndHasOption(): void
    {
        $this->definition->addArgument(new ArgumentDescriptor('name', ''));
        $this->definition->addOption(new OptionDescriptor('verbose', '', acceptsValue: false));

        self::assertTrue($this->definition->hasArgument('name'));
        self::assertFalse($this->definition->hasArgument('missing'));
        self::assertTrue($this->definition->hasOption('verbose'));
        self::assertFalse($this->definition->hasOption('missing'));
    }
}
