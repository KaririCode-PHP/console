<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Input;

use KaririCode\Console\Attribute\Argument;
use KaririCode\Console\Attribute\MapInput;
use KaririCode\Console\Attribute\Option;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Core\ConcreteInputDefinition;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Input\ArrayInput;
use KaririCode\Console\Input\InputMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(InputMapper::class)]
#[CoversClass(Argument::class)]
#[CoversClass(Option::class)]
#[CoversClass(MapInput::class)]
final class InputMapperTest extends TestCase
{
    public function testMapsArgumentAndOptionsToReadonlyDto(): void
    {
        $dto = InputMapper::map(DeployInput::class, self::parsed(DeployInput::class, new ArrayInput(
            arguments: ['environment' => 'production'],
            options: ['force' => true, 'tag' => 'v2.3'],
        )));

        self::assertSame('production', $dto->environment);
        self::assertTrue($dto->force);
        self::assertSame('v2.3', $dto->tag);
    }

    public function testUsesDefaultsWhenInputIsEmpty(): void
    {
        $dto = InputMapper::map(DeployInput::class, self::parsed(DeployInput::class, new ArrayInput()));

        self::assertSame('staging', $dto->environment);
        self::assertFalse($dto->force);
        self::assertSame('latest', $dto->tag);
    }

    public function testCoercesIntegerParameter(): void
    {
        $dto = InputMapper::map(CountInput::class, self::parsed(CountInput::class, new ArgvInput(['42'])));

        self::assertSame(42, $dto->count);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedIntegers(): iterable
    {
        yield 'letters'  => ['4x2'];
        yield 'fraction' => ['1.5'];
        yield 'empty'    => [''];
    }

    #[DataProvider('malformedIntegers')]
    public function testRejectsMalformedIntegerInsteadOfFallingBackToDefault(string $raw): void
    {
        $input = self::parsed(CountInput::class, new ArgvInput([$raw]));

        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9003);

        InputMapper::map(CountInput::class, $input);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function booleanWords(): iterable
    {
        yield 'yes'   => ['yes', true];
        yield 'on'    => ['on', true];
        yield 'y'     => ['y', true];
        yield '1'     => ['1', true];
        yield 'no'    => ['no', false];
        yield 'off'   => ['off', false];
        yield 'false' => ['false', false];
    }

    #[DataProvider('booleanWords')]
    public function testCoercesBooleanWordsFromOptionValues(string $word, bool $expected): void
    {
        $dto = InputMapper::map(ToggleInput::class, self::parsed(ToggleInput::class, new ArgvInput(["--dry-run={$word}"])));

        self::assertSame($expected, $dto->dryRun);
    }

    public function testRejectsUnrecognisedBooleanWord(): void
    {
        $input = self::parsed(ToggleInput::class, new ArgvInput(['--dry-run=nope']));

        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9004);

        InputMapper::map(ToggleInput::class, $input);
    }

    public function testRegisterIntoForwardsArrayOptions(): void
    {
        $dto = InputMapper::map(TagsInput::class, self::parsed(TagsInput::class, new ArgvInput(['--tag', 'a', '--tag=b'])));

        self::assertSame(['a', 'b'], $dto->tag);
    }

    public function testCoercesFloatStrictly(): void
    {
        $dto = InputMapper::map(RatioInput::class, self::parsed(RatioInput::class, new ArgvInput(['--ratio=0.25'])));
        self::assertSame(0.25, $dto->ratio);

        $this->expectExceptionCode(9004);
        InputMapper::map(RatioInput::class, self::parsed(RatioInput::class, new ArgvInput(['--ratio=quarter'])));
    }

    public function testThrowsForClassWithoutMapInputAttribute(): void
    {
        $this->expectException(ConsoleException::class);

        InputMapper::map(NoAttributeDto::class, new ArrayInput());
    }

    public function testResultIsActuallyReadonly(): void
    {
        $dto = InputMapper::map(DeployInput::class, self::parsed(DeployInput::class, new ArrayInput()));

        self::assertTrue((new \ReflectionClass($dto))->isReadOnly());
    }

    /**
     * The production flow: configure() calls registerInto(), Application
     * parses, execute() maps.
     *
     * @param class-string $dto
     */
    private static function parsed(string $dto, Input $input): Input
    {
        $definition = new ConcreteInputDefinition();
        InputMapper::registerInto($dto, $definition);
        $input->parse($definition);

        return $input;
    }
}

#[MapInput]
final readonly class DeployInput
{
    public function __construct(
        #[Argument]
        public string $environment = 'staging',
        #[Option(acceptsValue: false)]
        public bool $force = false,
        #[Option]
        public string $tag = 'latest',
    ) {}
}

#[MapInput]
final readonly class CountInput
{
    public function __construct(
        #[Argument]
        public int $count = 0,
    ) {}
}

#[MapInput]
final readonly class ToggleInput
{
    public function __construct(
        #[Option(name: 'dry-run')]
        public bool $dryRun = false,
    ) {}
}

#[MapInput]
final readonly class TagsInput
{
    /** @param list<string> $tag */
    public function __construct(
        #[Option(isArray: true)]
        public array $tag = [],
    ) {}
}

#[MapInput]
final readonly class RatioInput
{
    public function __construct(
        #[Option]
        public float $ratio = 1.0,
    ) {}
}

final class NoAttributeDto
{
    public string $name = '';
}
