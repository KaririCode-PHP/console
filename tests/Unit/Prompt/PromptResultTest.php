<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Prompt;

use KaririCode\Console\Attribute\MapInput;
use KaririCode\Console\Attribute\Option;
use KaririCode\Console\Core\ConcreteInputDefinition;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Input\InputMapper;
use KaririCode\Console\Prompt\PromptResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromptResult::class)]
final class PromptResultTest extends TestCase
{
    public function testFromUserInputMarksAsNotDefault(): void
    {
        $result = PromptResult::fromUserInput('hello');

        self::assertSame('hello', $result->value);
        self::assertFalse($result->isDefault);
    }

    public function testFromDefaultMarksAsDefault(): void
    {
        $result = PromptResult::fromDefault('fallback');

        self::assertSame('fallback', $result->value);
        self::assertTrue($result->isDefault);
    }

    public function testAsBooleanRecognizesTrueValues(): void
    {
        foreach (['y', 'yes', 'Y', 'YES', '1', 'true', 'TRUE'] as $val) {
            self::assertTrue(
                PromptResult::fromUserInput($val)->asBoolean(),
                "Expected '{$val}' to be truthy",
            );
        }
    }

    public function testAsBooleanRecognizesFalseValues(): void
    {
        foreach (['n', 'no', '0', 'false', ''] as $val) {
            self::assertFalse(
                PromptResult::fromUserInput($val)->asBoolean(),
                "Expected '{$val}' to be falsy",
            );
        }
    }

    public function testAsIntConvertsNumericString(): void
    {
        self::assertSame(42, PromptResult::fromUserInput('42')->asInt());
    }

    /** Regression: "abc" silently became the default and "1.9" became 1. */
    public function testAsIntIsStrictAndOnlyAnEmptyAnswerTakesTheDefault(): void
    {
        self::assertSame(99, PromptResult::fromUserInput('')->asInt(99));
        self::assertSame(42, PromptResult::fromUserInput(' 42 ')->asInt());

        foreach (['abc', '1.9', '4x'] as $answer) {
            try {
                PromptResult::fromUserInput($answer)->asInt(99);
                self::fail("accepted {$answer}");
            } catch (ConsoleException $e) {
                self::assertSame(9003, $e->getCode());
            }
        }
    }

    /** @return iterable<string, array{string, ?bool}> */
    public static function booleanWords(): iterable
    {
        foreach (['1', 'true', 'yes', 'y', 'on', 'YES'] as $word) {
            yield "true:{$word}" => [$word, true];
        }
        foreach (['0', 'false', 'no', 'n', 'off'] as $word) {
            yield "false:{$word}" => [$word, false];
        }
        foreach (['treu', 'maybe', 'nope'] as $word) {
            yield "rejected:{$word}" => [$word, null];
        }
    }

    /**
     * Regression: the 4.2.2 changelog said PromptResult and InputMapper share
     * one boolean vocabulary; they did not ("on" was unknown here, typos were
     * silently false). Both are now checked against the same table.
     */
    #[DataProvider('booleanWords')]
    public function testBooleanVocabularyMatchesInputMapper(string $word, ?bool $expected): void
    {
        self::assertSame($expected, self::attempt(static fn(): bool => PromptResult::fromUserInput($word)->asBoolean()));

        $input = new ArgvInput(["--flag={$word}"]);
        $definition = new ConcreteInputDefinition();
        InputMapper::registerInto(BooleanProbe::class, $definition);
        $input->parse($definition);

        self::assertSame($expected, self::attempt(static fn(): bool => InputMapper::map(BooleanProbe::class, $input)->flag));
    }

    /** @param \Closure(): bool $read */
    private static function attempt(\Closure $read): ?bool
    {
        try {
            return $read();
        } catch (ConsoleException) {
            return null;
        }
    }

    public function testIsEmptyDetectsEmptyValue(): void
    {
        self::assertTrue(PromptResult::fromDefault('')->isEmpty());
        self::assertFalse(PromptResult::fromDefault('x')->isEmpty());
    }
}

#[MapInput]
final readonly class BooleanProbe
{
    public function __construct(
        #[Option]
        public bool $flag = false,
    ) {}
}
