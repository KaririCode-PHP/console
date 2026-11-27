<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Integration;

use KaririCode\Console\Command\CompletionCommand;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Provider\ConsoleServiceProvider;
use KaririCode\Console\Output\BufferedOutput;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\OptionDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;

/**
 * What each shell actually completes — not just whether the script parses.
 *
 * bash: an interactive bash in a pty types the prefix, presses Tab and Enter;
 *       the completed line is printed (readline's own word splitting applies).
 * zsh:  an interactive zsh in a zpty with compinit; a compadd wrapper records
 *       every candidate the completion system offers.
 * fish: `complete -C`, fish's own query interface.
 */
#[CoversClass(CompletionCommand::class)]
#[RequiresOperatingSystemFamily('Linux')]
final class CompletionBehaviourTest extends TestCase
{
    private const string BIN = __DIR__ . '/bin';

    /** @return iterable<string, array{string, string}> buffer => completed line */
    public static function bashLines(): iterable
    {
        yield 'command name'             => ['app dep', '[deploy]'];
        yield 'command option'           => ['app deploy --e', '[deploy][--env]'];
        yield 'shared prefix to colon'   => ['app ca', '[cache:]'];
        // Regression: COMP_WORDBREAKS contains ':' and split the name apart.
        yield 'name containing a colon'  => ['app cache:w', '[cache:warm]'];
    }

    #[DataProvider('bashLines')]
    public function testBash(string $buffer, string $completedLine): void
    {
        self::requireShell('bash');

        $script = self::script('bash');
        exec(sprintf('%s %s %s app %s', escapeshellarg(PHP_BINARY), escapeshellarg(self::BIN . '/bash-complete'), escapeshellarg($script), escapeshellarg($buffer)), $out);
        unlink($script);

        self::assertSame($completedLine, $out[0] ?? null);
    }

    /** @return iterable<string, array{string, list<string>}> buffer => candidates */
    public static function candidates(): iterable
    {
        yield 'all commands'            => ['app ', ['cache:clear', 'cache:warm', 'completion', 'deploy', 'help', 'list']];
        yield 'name containing a colon' => ['app cache:w', ['cache:warm']];
        // Regression (zsh): the top-level _arguments swallowed dash-words, so
        // subcommand options never completed.
        yield 'command options'         => ['app deploy --', ['--env', '--force']];
        yield 'option prefix'           => ['app list --f', ['--filter', '--format']];
    }

    /** @param list<string> $expected */
    #[DataProvider('candidates')]
    public function testZsh(string $buffer, array $expected): void
    {
        self::requireShell('zsh');

        $script = self::script('zsh');
        exec(sprintf('timeout 15 zsh %s %s %s', escapeshellarg(self::BIN . '/zsh-complete'), escapeshellarg($script), escapeshellarg($buffer)), $out);
        unlink($script);

        self::assertSame($expected, $out);
    }

    /** @param list<string> $expected */
    #[DataProvider('candidates')]
    public function testFish(string $buffer, array $expected): void
    {
        self::requireShell('fish');

        $script = self::script('fish');
        exec(sprintf('fish --no-config -c %s', escapeshellarg('source ' . escapeshellarg($script) . '; complete -C ' . escapeshellarg($buffer))), $out);
        unlink($script);

        $names = array_map(static fn(string $line): string => explode("\t", $line)[0], $out);
        sort($names);

        self::assertSame($expected, $names);
    }

    private static function script(string $shell): string
    {
        $app = ConsoleServiceProvider::buildApplication('demo', '1.0')
            ->add(new StubCommand('deploy', 'Deploys it', static function (InputDefinition $d): void {
                $d->addOption(new OptionDescriptor('env', 'Target [env]: prod'));
                $d->addOption(new OptionDescriptor('force', 'Skip checks', acceptsValue: false));
            }))
            ->add(new StubCommand('cache:warm', 'Warms the cache'))
            ->add(new StubCommand('cache:clear', 'Clears the cache'));

        $output = new BufferedOutput();
        self::assertSame(0, $app->dispatch(new ArgvInput(['completion', $shell, '--program', 'app']), $output));

        $path = (string) tempnam(sys_get_temp_dir(), "kc-{$shell}");
        file_put_contents($path, $output->buffer);

        return $path;
    }

    private static function requireShell(string $shell): void
    {
        if (trim((string) shell_exec('command -v ' . escapeshellarg($shell))) === '') {
            self::markTestSkipped("{$shell} is not installed.");
        }
    }
}
