<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\ValueObject;

use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\ValueObject\GlobalOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Global-flag grammar in isolation. Each case pins a behaviour that mutation
 * testing showed no other test proved.
 */
#[CoversClass(GlobalOptions::class)]
final class GlobalOptionsTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function globalFlags(): iterable
    {
        foreach (['-n', '--no-interaction', '-h', '--help', '-V', '--version', '-q', '--quiet', '-v', '-vv', '-vvv', '--verbose'] as $flag) {
            yield $flag => [$flag];
        }
    }

    /** Short and long forms alike are consumed, never passed to the command's parser. */
    #[DataProvider('globalFlags')]
    public function testGlobalFlagsNeverReachTheCommand(string $flag): void
    {
        $options = GlobalOptions::parse(['deploy', $flag, '--dry-run'], interactive: true);

        self::assertSame('deploy', $options->commandName);
        self::assertSame(['--dry-run'], $options->passthrough);
    }

    public function testTokensBeforeAndAfterTheTerminatorKeepTheirOrder(): void
    {
        $options = GlobalOptions::parse(['deploy', '-x', 'a', '--', '-v', 'b'], interactive: true);

        self::assertSame('deploy', $options->commandName);
        self::assertSame(['-x', 'a', '--', '-v', 'b'], $options->passthrough);
        self::assertNull($options->verbosity, '-v after -- is an operand');
    }

    public function testVerbosityFlagMayFollowTheCommandAndTheLastOneWins(): void
    {
        self::assertSame(Verbosity::Verbose, GlobalOptions::parse(['deploy', '-v'], interactive: true)->verbosity);
        self::assertSame(Verbosity::Quiet, GlobalOptions::parse(['-vvv', 'deploy', 'x', '-q'], interactive: true)->verbosity);
    }

    public function testInteractivityNeedsAnInteractiveInputAndNoOptOut(): void
    {
        self::assertTrue(GlobalOptions::parse(['deploy', 'x'], interactive: true)->interactive);
        self::assertFalse(GlobalOptions::parse(['deploy', 'x'], interactive: false)->interactive);
        self::assertFalse(GlobalOptions::parse(['deploy', '--no-interaction'], interactive: true)->interactive);
    }

    public function testCommandNameMayFollowTheTerminatorWhenNoneCameBefore(): void
    {
        $options = GlobalOptions::parse(['-v', '--', 'deploy', '-n'], interactive: true);

        self::assertSame('deploy', $options->commandName);
        self::assertSame(['--', '-n'], $options->passthrough);
        self::assertTrue($options->interactive);
    }

    public function testHelpAndVersionAreDetectedInEitherForm(): void
    {
        self::assertTrue(GlobalOptions::parse(['--help'], interactive: true)->wantsHelp);
        self::assertTrue(GlobalOptions::parse(['deploy', '-h'], interactive: true)->wantsHelp);
        self::assertTrue(GlobalOptions::parse(['--version'], interactive: true)->wantsVersion);
        self::assertFalse(GlobalOptions::parse(['deploy'], interactive: true)->wantsVersion);
        self::assertNull(GlobalOptions::parse([], interactive: true)->commandName);
    }
}
