<?php

declare(strict_types=1);

namespace KaririCode\Console\ValueObject;

use KaririCode\Console\Enum\Verbosity;

/**
 * Global flags and the command name that Application::dispatch() reads
 * from argv before the command's own parser sees anything.
 *
 * Grammar: global flags are recognised anywhere before `--`; the first
 * other operand names the command (or, when none precedes `--`, the first
 * token after it). Everything else reaches the command in order, and a
 * `--` is forwarded so the command's parser also treats what follows as
 * operands (POSIX Guideline 10).
 *
 *   $options = GlobalOptions::parse(['-v', 'deploy', '--', '-x'], interactive: true);
 *   // commandName 'deploy', verbosity Verbose, passthrough ['--', '-x']
 *
 * @package KaririCode\Console\ValueObject
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.4.0
 */
final readonly class GlobalOptions
{
    private const array NO_INTERACTION = ['-n', '--no-interaction'];

    private const array HELP = ['-h', '--help'];

    private const array VERSION = ['-V', '--version'];

    /**
     * @param list<string> $passthrough Tokens for the command's own parser.
     */
    public function __construct(
        public ?string $commandName,
        public array $passthrough,
        public ?Verbosity $verbosity,
        public bool $interactive,
        public bool $wantsHelp,
        public bool $wantsVersion,
    ) {}

    /**
     * @param list<string> $tokens Raw argv tokens, without the script name.
     */
    public static function parse(array $tokens, bool $interactive): self
    {
        $split    = array_search('--', haystack: $tokens, strict: true);
        $flags    = $split === false ? $tokens : array_slice($tokens, offset: 0, length: $split);
        $operands = $split === false ? [] : array_slice($tokens, $split + 1);

        $levels = array_values(array_filter(array_map(Verbosity::fromFlag(...), $flags)));
        $rest   = array_values(array_filter($flags, static fn(string $token): bool => !self::isGlobalFlag($token)));
        /** @var int|null $index */
        $index  = array_find_key($rest, static fn(string $token): bool => !str_starts_with($token, '-'));

        if ($index !== null) {
            $commandName = $rest[$index];
            array_splice($rest, offset: $index, length: 1);
        } else {
            $commandName = array_shift($operands);
        }

        return new self(
            commandName: $commandName,
            passthrough: $split === false ? $rest : [...$rest, '--', ...$operands],
            verbosity: $levels === [] ? null : $levels[count($levels) - 1],
            interactive: $interactive && array_intersect($flags, self::NO_INTERACTION) === [],
            wantsHelp: array_intersect($flags, self::HELP) !== [],
            wantsVersion: array_intersect($flags, self::VERSION) !== [],
        );
    }

    private static function isGlobalFlag(string $token): bool
    {
        return Verbosity::fromFlag($token) !== null
            || in_array($token, haystack: [...self::NO_INTERACTION, ...self::HELP, ...self::VERSION], strict: true);
    }
}
