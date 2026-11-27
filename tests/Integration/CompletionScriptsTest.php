<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Integration;

use KaririCode\Console\Command\CompletionCommand;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Provider\ConsoleServiceProvider;
use KaririCode\Console\Output\BufferedOutput;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;

/**
 * Generated scripts are parsed and sourced by the real shells. Hostile
 * descriptions must never execute: a canary file proves it.
 */
#[CoversClass(CompletionCommand::class)]
#[RequiresOperatingSystemFamily('Linux')]
#[Group('security')]
final class CompletionScriptsTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> shell, syntax check, source */
    public static function shells(): iterable
    {
        yield 'bash' => ['bash', 'bash -n %s', 'bash -c %s'];
        yield 'zsh'  => ['zsh', 'zsh -n %s', 'zsh -c %s'];
        yield 'fish' => ['fish', 'fish --no-execute %s', 'fish -c %s'];
    }

    #[DataProvider('shells')]
    public function testScriptIsValidAndSourcingItExecutesNothing(string $shell, string $check, string $source): void
    {
        if (trim((string) shell_exec('command -v ' . escapeshellarg($shell))) === '') {
            self::markTestSkipped("{$shell} is not installed.");
        }

        $canary  = sys_get_temp_dir() . '/kc-canary-' . bin2hex(random_bytes(4));
        // Quotes, both substitution syntaxes, a newline (comment breakout in
        // fish) and a trailing backslash (escapes fish's closing quote).
        $payload = "It's \"live\" \$(touch {$canary}) `touch {$canary}` (touch {$canary});\ntouch {$canary} ; \\";

        $app = ConsoleServiceProvider::buildApplication('demo', '1.0')->add(new StubCommand(
            'deploy',
            $payload,
            static function (InputDefinition $d) use ($payload): void {
                $d->addOption(new OptionDescriptor('env', $payload));
                $d->addArgument(new ArgumentDescriptor('target', $payload));
            },
        ));

        $output = new BufferedOutput();
        self::assertSame(0, $app->dispatch(new ArgvInput(['completion', $shell, '--program', 'demo']), $output));

        $script = tempnam(sys_get_temp_dir(), 'kc-completion');
        file_put_contents($script, $output->buffer);

        exec(sprintf($check, escapeshellarg($script)) . ' 2>&1', $diagnostics, $syntaxStatus);
        exec(sprintf($source, escapeshellarg("source {$script}")) . ' 2>&1', $ignored, $sourceStatus);
        unlink($script);

        self::assertSame(0, $syntaxStatus, implode("\n", $diagnostics));
        self::assertFileDoesNotExist($canary, 'sourcing the completion script executed embedded code');
    }
}
