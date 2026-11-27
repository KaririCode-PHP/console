<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Core;

use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Core\Application;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Provider\ConsoleServiceProvider;
use KaririCode\Console\Tests\Fixture\AliasedHiddenCommand;
use KaririCode\Console\Tests\Fixture\SplitOutput;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\GlobalOptions;
use KaririCode\Console\ValueObject\OptionDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Global-flag grammar, error routing and exit statuses of dispatch()/run().
 */
#[CoversClass(Application::class)]
#[CoversClass(GlobalOptions::class)]
final class ApplicationGrammarTest extends TestCase
{
    // ── `--` terminator (regression: flags after it were consumed) ───────

    public function testGlobalFlagsAfterTerminatorReachTheCommandAsOperands(): void
    {
        [$app, $echo] = self::appWithEcho();
        $output       = new SplitOutput();

        $status = $app->dispatch(new ArgvInput(['echo', '--', '-v', '-q']), $output);

        self::assertSame(0, $status);
        self::assertSame(['-v', '-q'], $echo->lastInput?->getArgument('words'));
        self::assertSame(Verbosity::Normal, $output->verbosity);
    }

    public function testCommandNameMayFollowTheTerminator(): void
    {
        [$app, $echo] = self::appWithEcho();

        $app->dispatch(new ArgvInput(['--', 'echo', '-n']), new SplitOutput());

        self::assertSame(['-n'], $echo->lastInput?->getArgument('words'));
        self::assertTrue($echo->lastInput->interactive, '-n after -- is not --no-interaction');
    }

    // ── --help / --version (GNU Coding Standards §4.8) ───────────────────

    public function testHelpFlagDescribesTheCommandInsteadOfRunningIt(): void
    {
        [$app, $echo] = self::appWithEcho();
        $output       = new SplitOutput();

        $status = $app->dispatch(new ArgvInput(['echo', '--help']), $output);

        self::assertSame(0, $status);
        self::assertSame(0, $echo->executions);
        self::assertStringContainsString('echo [--] [<words>...]', $output->stdout->buffer);
    }

    public function testHelpFlagAloneDescribesTheApplication(): void
    {
        [$app] = self::appWithEcho();
        $output = new SplitOutput();

        $app->dispatch(new ArgvInput(['-h']), $output);

        self::assertStringContainsString('Global options:', $output->stdout->buffer);
        self::assertStringContainsString('echo', $output->stdout->buffer);
    }

    public function testVersionFlagPrintsNameAndVersion(): void
    {
        [$app] = self::appWithEcho();
        $output = new SplitOutput();

        self::assertSame(0, $app->dispatch(new ArgvInput(['-V']), $output));
        self::assertSame("demo 1.2.3\n", $output->stdout->buffer);
    }

    // ── Reserved options (regression: --debug hijacked command options) ─

    public function testDebugIsNotAGlobalFlagAnymore(): void
    {
        $command = new StubCommand('build', define: static function (InputDefinition $d): void {
            $d->addOption(new OptionDescriptor('debug', 'Emit debug symbols', acceptsValue: false));
        });
        $app    = ConsoleServiceProvider::buildApplication('demo', '1.0')->add($command);
        $output = new SplitOutput();

        $app->dispatch(new ArgvInput(['build', '--debug']), $output);

        self::assertTrue($command->lastInput?->getOption('debug'));
        self::assertSame(Verbosity::Normal, $output->verbosity);
    }

    /** @return iterable<string, array{OptionDescriptor}> */
    public static function reservedOptions(): iterable
    {
        yield 'long --verbose' => [new OptionDescriptor('verbose', '', acceptsValue: false)];
        yield 'long --help'    => [new OptionDescriptor('help', '', acceptsValue: false)];
        yield 'shortcut -v'    => [new OptionDescriptor('value', '', shortcut: 'v')];
        yield 'shortcut -n'    => [new OptionDescriptor('name', '', shortcut: 'n')];
    }

    #[DataProvider('reservedOptions')]
    public function testCommandDeclaringAReservedOptionIsRejected(OptionDescriptor $option): void
    {
        $command = new StubCommand('clash', define: static fn(InputDefinition $d) => $d->addOption($option));
        $app     = ConsoleServiceProvider::buildApplication()->add($command);
        $output  = new SplitOutput();

        $result = $app->run('clash', new ArgvInput([]), $output);

        self::assertSame(ExitCode::Invalid, $result->exitCode);
        self::assertSame(0, $command->executions);
        self::assertStringContainsString('collides with a global option', $output->stderr->buffer);
    }

    // ── Error routing and exit statuses ──────────────────────────────────

    public function testErrorsGoToTheErrorOutputAndNeverToStdout(): void
    {
        $output = new SplitOutput();

        $status = ConsoleServiceProvider::buildApplication()->dispatch(new ArgvInput(['nope']), $output);

        self::assertSame(1, $status);
        self::assertSame('', $output->stdout->buffer);
        self::assertStringContainsString('"nope" is not registered', $output->stderr->buffer);
    }

    /** @return iterable<string, array{list<string>}> */
    public static function usageErrors(): iterable
    {
        yield 'unknown option'    => [['echo', '--nope']];
        yield 'unknown shortcut'  => [['greet', '-z']];
        yield 'missing argument'  => [['greet']];
        yield 'surplus operand'   => [['greet', 'a', 'b']];
    }

    /** @param list<string> $tokens */
    #[DataProvider('usageErrors')]
    public function testUsageErrorsExitWithStatusTwo(array $tokens): void
    {
        [$app] = self::appWithEcho();
        $app->add(new StubCommand('greet', define: static function (InputDefinition $d): void {
            $d->addArgument(new ArgumentDescriptor('name', 'Who to greet'));
        }));

        self::assertSame(2, $app->dispatch(new ArgvInput($tokens), new SplitOutput()));
    }

    #[Group('security')]
    public function testErrorTextIsEscapedSoInputCannotForgeMarkup(): void
    {
        [$app] = self::appWithEcho();
        $output = new SplitOutput();

        $app->dispatch(new ArgvInput(['echo', '--x</e><info>forged']), $output);

        // Unescaped, the non-decorated formatter would have stripped both tags.
        self::assertStringContainsString('x</e><info>forged', $output->stderr->buffer);
    }

    public function testStackTraceIsShownOnlyAtDebugVerbosity(): void
    {
        $boom = static fn(Input $i, Output $o): ExitCode => throw new \LogicException('kaboom');

        foreach ([[[], false], [['-vvv'], true]] as [$flags, $expectTrace]) {
            [$app] = self::appWithEcho();
            $app->add(new StubCommand('boom', body: $boom));
            $output = new SplitOutput();

            self::assertSame(1, $app->dispatch(new ArgvInput([...$flags, 'boom']), $output));
            self::assertStringContainsString('kaboom', $output->stderr->buffer);
            self::assertSame($expectTrace, str_contains($output->stderr->buffer, '#0 '));
        }
    }

    // ── add() honours #[AsCommand] like the loaders do ───────────────────

    public function testAddHonoursAsCommandAliasesAndHiddenFlag(): void
    {
        $app = ConsoleServiceProvider::buildApplication()->add(new AliasedHiddenCommand());

        self::assertTrue($app->registry->has('warm'));
        self::assertNotContains('cache:warm', array_map(static fn($d) => $d->name, $app->registry->all()));
        self::assertContains('cache:warm', array_map(static fn($d) => $d->name, $app->registry->all(includeHidden: true)));
    }

    /** @return array{Application, StubCommand} */
    private static function appWithEcho(): array
    {
        $echo = new StubCommand('echo', 'Echoes its operands', static function (InputDefinition $d): void {
            $d->addArgument(new ArgumentDescriptor('words', 'Words to echo', required: false, isArray: true));
        });

        return [ConsoleServiceProvider::buildApplication('demo', '1.2.3')->add($echo), $echo];
    }
}
