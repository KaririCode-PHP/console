<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Command;

use KaririCode\Console\Command\CompletionCommand;
use KaririCode\Console\Command\HelpCommand;
use KaririCode\Console\Command\ListCommand;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Core\Application;
use KaririCode\Console\Enum\OutputFormat;
use KaririCode\Console\Enum\Shell;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Provider\ConsoleServiceProvider;
use KaririCode\Console\Tests\Fixture\AliasedHiddenCommand;
use KaririCode\Console\Tests\Fixture\SplitOutput;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(HelpCommand::class)]
#[CoversClass(ListCommand::class)]
#[CoversClass(CompletionCommand::class)]
#[CoversClass(OutputFormat::class)]
#[CoversClass(Shell::class)]
final class BuiltinCommandsTest extends TestCase
{
    // ── help ─────────────────────────────────────────────────────────────

    /** Regression: `help <command>` never showed arguments or options. */
    public function testHelpDescribesUsageArgumentsOptionsAndAliases(): void
    {
        $out = self::dispatch(['help', 'deploy']);

        self::assertStringContainsString('deploy [options] [--] <env> [<regions>...]', $out->stdout->buffer);
        self::assertStringContainsString('Target environment', $out->stdout->buffer);
        self::assertStringContainsString('[default: ["eu"]]', $out->stdout->buffer);
        self::assertStringContainsString('-t, --tag=TAG', $out->stdout->buffer);
        self::assertStringContainsString('(multiple values allowed)', $out->stdout->buffer);
        self::assertStringContainsString('--force', $out->stdout->buffer);
    }

    /** An argument named "e" must not turn the synopsis red (<e> is the error style). */
    public function testHelpEscapesNamesThatCollideWithStyleTags(): void
    {
        $out = self::dispatch(['help', 'tagged']);

        self::assertStringContainsString('tagged [--] <e> [<info>]', $out->stdout->buffer);
    }

    public function testHelpForUnknownCommandFailsOnTheErrorOutput(): void
    {
        $out = new SplitOutput();

        self::assertSame(1, self::app()->dispatch(new ArgvInput(['help', 'nope']), $out));
        self::assertSame('', $out->stdout->buffer);
        self::assertStringContainsString('"nope" is not registered', $out->stderr->buffer);
    }

    // ── list ─────────────────────────────────────────────────────────────

    /** Regression: an unknown format silently fell back to a table. */
    public function testListRejectsUnknownFormat(): void
    {
        $out = new SplitOutput();

        self::assertSame(2, self::app()->dispatch(new ArgvInput(['list', '--format=jsn']), $out));
        self::assertSame('', $out->stdout->buffer);
        self::assertStringContainsString('Unsupported format "jsn"', $out->stderr->buffer);
    }

    /** Regression: JSON went through the markup formatter. */
    public function testListJsonIsValidAndKeepsDescriptionsVerbatim(): void
    {
        $out = self::dispatch(['list', '--format=json', '--filter=tag']);

        self::assertSame(
            [['name' => 'tagged', 'description' => 'Uses <b>markup</b> & <file>', 'aliases' => [], 'hidden' => false]],
            json_decode($out->stdout->buffer, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testListHidesHiddenCommandsUnlessAllIsGiven(): void
    {
        self::assertStringNotContainsString('cache:warm', self::dispatch(['list', '--format=plain'])->stdout->buffer);
        self::assertStringContainsString('cache:warm', self::dispatch(['list', '--format=plain', '--all'])->stdout->buffer);
    }

    // ── completion ───────────────────────────────────────────────────────

    /** Regression (CWE-78): --program was spliced raw into the generated script. */
    #[Group('security')]
    public function testCompletionRejectsProgramNamesThatCouldInjectShellCode(): void
    {
        $out = new SplitOutput();

        $status = self::app()->dispatch(new ArgvInput(['completion', 'bash', '--program', 'x;touch /tmp/pwned;#']), $out);

        self::assertSame(2, $status);
        self::assertSame('', $out->stdout->buffer);
        self::assertStringContainsString('Invalid program name', $out->stderr->buffer);
    }

    public function testCompletionRejectsUnsupportedShell(): void
    {
        self::assertSame(2, self::app()->dispatch(new ArgvInput(['completion', 'powershell']), new SplitOutput()));
    }

    public function testCompletionScriptIsWrittenVerbatimNotAsMarkup(): void
    {
        $script = self::dispatch(['completion', 'fish', '--program', 'my-app'])->stdout->buffer;

        self::assertStringContainsString('complete -c my-app', $script);
        self::assertStringContainsString('<b>markup</b>', $script);
    }

    /** Regression: `_describe` split namespaced names at the first colon. */
    public function testZshCompletionEscapesColonsInCommandNames(): void
    {
        self::assertStringContainsString("'cache\\:warm:Warms'", self::zshWith('cache:warm'));
    }

    /** Names are spliced into shell code, so only inert characters are accepted. */
    public function testCommandNamesAreRestrictedToShellInertCharacters(): void
    {
        foreach (['x;touch p', 'a b', '$(id)', 'cmd)', ''] as $name) {
            try {
                new \KaririCode\Console\ValueObject\CommandDescriptor($name, '', StubCommand::class);
                self::fail("accepted: {$name}");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        new \KaririCode\Console\ValueObject\CommandDescriptor('ok', '', StubCommand::class, aliases: ['bad alias']);
    }

    private static function zshWith(string $name): string
    {
        $out = new SplitOutput();
        ConsoleServiceProvider::buildApplication()->add(new StubCommand($name, 'Warms'))->dispatch(new ArgvInput(['completion', 'zsh']), $out);

        return $out->stdout->buffer;
    }

    public function testHelpWithoutArgumentDescribesTheApplication(): void
    {
        $out = self::dispatch(['help']);

        self::assertStringContainsString('demo 1.0', $out->stdout->buffer);
        self::assertStringContainsString('-V, --version', $out->stdout->buffer);
        self::assertStringContainsString('deploy', $out->stdout->buffer);
        self::assertStringNotContainsString('cache:warm', $out->stdout->buffer, 'hidden commands stay hidden');
    }

    public function testHelpListsAliases(): void
    {
        self::assertMatchesRegularExpression('/Aliases:\s+warm/', self::dispatch(['help', 'cache:warm'])->stdout->buffer);
    }

    public function testListDefaultsToAnAlignedTable(): void
    {
        $lines = array_values(array_filter(explode("\n", self::dispatch(['list'])->stdout->buffer)));

        self::assertStringStartsWith('+', $lines[0]);
        self::assertCount(1, array_unique(array_map(mb_strwidth(...), $lines)), 'markup in descriptions does not break alignment');
    }

    /** @param list<string> $tokens */
    private static function dispatch(array $tokens): SplitOutput
    {
        $out = new SplitOutput();
        self::app()->dispatch(new ArgvInput($tokens), $out);

        return $out;
    }

    private static function app(): Application
    {
        return ConsoleServiceProvider::buildApplication('demo', '1.0')
            ->add(new StubCommand('deploy', 'Deploys', static function (InputDefinition $d): void {
                $d->addArgument(new ArgumentDescriptor('env', 'Target environment'));
                $d->addArgument(new ArgumentDescriptor('regions', 'Regions', required: false, default: ['eu'], isArray: true));
                $d->addOption(new OptionDescriptor('tag', 'Release tags', shortcut: 't', isArray: true));
                $d->addOption(new OptionDescriptor('force', 'Skip checks', acceptsValue: false));
            }))
            ->add(new StubCommand('tagged', 'Uses <b>markup</b> & <file>', static function (InputDefinition $d): void {
                $d->addArgument(new ArgumentDescriptor('e', 'Collides with the error style'));
                $d->addArgument(new ArgumentDescriptor('info', 'Collides with the info style', required: false));
            }))
            ->add(new AliasedHiddenCommand());
    }
}
