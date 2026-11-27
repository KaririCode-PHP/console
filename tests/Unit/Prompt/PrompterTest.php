<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Prompt;

use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Input\ArrayInput;
use KaririCode\Console\Output\BufferedOutput;
use KaririCode\Console\Prompt\Prompter;
use KaririCode\Console\Tests\Fixture\SplitOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Prompter::class)]
final class PrompterTest extends TestCase
{
    public function testNonInteractivePromptsReturnDefaultsWithoutReading(): void
    {
        $prompter = self::prompter("SHOULD NOT BE READ\n", interactive: false);

        self::assertSame('d', $prompter->text('Name', default: 'd')->value);
        self::assertSame('s', $prompter->password('Secret', 's')->value);
        self::assertFalse($prompter->confirm('Sure?', default: false));
        self::assertSame('b', $prompter->select('Pick', ['a', 'b'], 1));
        self::assertSame([], $prompter->multiselect('Pick', ['a', 'b']));
    }

    public function testSelectAcceptsAValidIndexAndDefaultsOnEmptyAnswer(): void
    {
        self::assertSame('c', self::prompter("2\n")->select('Pick', ['a', 'b', 'c']));
        self::assertSame('b', self::prompter("\n")->select('Pick', ['a', 'b', 'c'], 1));
    }

    /** Regression: "99" silently selected the last choice. */
    public function testSelectReasksOnOutOfRangeAnswer(): void
    {
        $out = new SplitOutput();

        self::assertSame('a', self::prompter("99\nabc\n0\n", out: $out)->select('Pick', ['a', 'b']));
        self::assertSame(2, substr_count($out->stderr->buffer, 'is not one of 0..1'));
    }

    public function testSelectGivesUpAfterThreeInvalidAnswers(): void
    {
        $this->expectException(ConsoleException::class);
        $this->expectExceptionCode(9003);

        self::prompter("9\n9\n9\n0\n")->select('Pick', ['a', 'b']);
    }

    public function testSelectRejectsEmptyChoicesAndBadDefault(): void
    {
        try {
            self::prompter('')->select('Pick', []);
            self::fail('empty choices accepted');
        } catch (ConsoleException $e) {
            self::assertSame(9003, $e->getCode());
        }

        $this->expectExceptionCode(9003);
        self::prompter('')->select('Pick', ['a'], 5);
    }

    public function testSelectReindexesAssociativeChoices(): void
    {
        self::assertSame('Beta', self::prompter("1\n")->select('Pick', ['x' => 'Alpha', 'y' => 'Beta']));
    }

    /** Regression: "a,b" was cast to 0 and silently selected the first choice. */
    public function testMultiselectRejectsMalformedAnswersAndDeduplicates(): void
    {
        $out = new SplitOutput();

        self::assertSame(['b', 'c'], self::prompter("a,b\n2, 1,1\n", out: $out)->multiselect('Pick', ['a', 'b', 'c']));
        self::assertStringContainsString('must be indices', $out->stderr->buffer);
    }

    public function testMultiselectEmptyAnswerKeepsDefaults(): void
    {
        self::assertSame(['a', 'c'], self::prompter("\n")->multiselect('Pick', ['a', 'b', 'c'], [2, 0]));
    }

    public function testConfirmUnderstandsYesNoAndDefault(): void
    {
        self::assertTrue(self::prompter("yes\n")->confirm('Sure?', default: false));
        self::assertFalse(self::prompter("n\n")->confirm('Sure?'));
        self::assertTrue(self::prompter("\n")->confirm('Sure?'));
    }

    public function testTextFallsBackToDefaultOnEmptyAnswer(): void
    {
        $answer = self::prompter("\n")->text('Name', default: 'Walmir');

        self::assertSame('Walmir', $answer->value);
    }

    /** A piped or in-memory stream never echoes, so no stty is involved. */
    #[Group('security')]
    public function testPasswordReadsNonTerminalInputDirectly(): void
    {
        self::assertSame('s3cret', self::prompter("s3cret\n")->password('Secret')->value);
    }

    #[Group('security')]
    public function testChoicesAreEscapedBeforeDisplay(): void
    {
        $out = new SplitOutput();

        self::prompter("0\n", out: $out)->select('Pick', ['<e>fake</e>']);

        self::assertStringContainsString('[0] <e>fake</e>', $out->stdout->buffer);
    }

    public function testRejectsAnInputThatIsNotAStream(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Prompter(new ArrayInput(), new BufferedOutput(), 'not a stream'); // @phpstan-ignore argument.type (deliberately invalid)
    }

    public function testSpinRunsTheCallbackAndReturnsItsValueInBothModes(): void
    {
        $plain = new SplitOutput();
        $value = (new Prompter(new ArrayInput(), $plain, self::stream('')))->spin('Working', static fn(): int => 42);

        $color = new \KaririCode\Console\Output\BufferedOutput(decorated: true);
        $again = (new Prompter(new ArrayInput(), $color, self::stream('')))->spin('Working', static fn(): string => 'ok');

        self::assertSame([42, 'ok'], [$value, $again]);
        self::assertSame("Working…\n", $plain->stdout->buffer);
        self::assertStringContainsString('✓', $color->buffer);
    }

    public function testTextShowsEscapedSuggestions(): void
    {
        $out = new SplitOutput();

        self::prompter("\n", out: $out)->text('Branch', suggestions: ['main', '<e>dev</e>']);

        self::assertStringContainsString('Suggestions: main, <e>dev</e>', $out->stdout->buffer);
    }

    public function testMultiselectRejectsAnOutOfRangeDefault(): void
    {
        $this->expectExceptionCode(9003);

        self::prompter('')->multiselect('Pick', ['a'], [3]);
    }

    /** @return resource */
    private static function stream(string $content)
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    /** ASVS 5.0 V6.2.8: a secret is used exactly as received (no trimming). */
    public function testPasswordKeepsSurroundingSpaces(): void
    {
        self::assertSame('  s3cret  ', self::prompter("  s3cret  \r\n")->password('Secret')->value);
    }

    private static function prompter(string $answers, bool $interactive = true, ?SplitOutput $out = null): Prompter
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $answers);
        rewind($stream);

        return new Prompter(new ArrayInput(interactive: $interactive), $out ?? new SplitOutput(), $stream);
    }
}
