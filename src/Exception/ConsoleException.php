<?php

declare(strict_types=1);

namespace KaririCode\Console\Exception;

use KaririCode\Contract\Exception\ExceptionInterface;
use RuntimeException;

/**
 * Root exception hierarchy for KaririCode\Console.
 *
 * All Console exceptions extend this class. Semantic factory methods
 * provide consistent error codes (9000–9099) and human-readable
 * messages without requiring callers to know error code assignments
 * (Spec v4.3.3 §8 — error model: semantic exception factories).
 *
 * Error code registry (Console range: 9000–9099, ADR-003) and the
 * kariricode/contract family each failure implements (ADR-014):
 *   9001  Command not found           CommandNotFoundException    NotFound
 *   9002  Command execution failed    CommandExecutionException   Runtime
 *   9003  Invalid argument            InvalidArgumentException    Validation
 *   9004  Invalid option              InvalidOptionException      Validation
 *   9005  Missing required argument   InvalidArgumentException    Validation
 *   9006  Loader failure              CommandLoaderException      Configuration
 *   9007  Output write failure        OutputWriteException        Runtime
 *
 * The class is abstract: every failure is a final subclass that implements
 * exactly one family, so an edge maps it without knowing this package.
 *
 * @package KaririCode\Console\Exception
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
abstract class ConsoleException extends RuntimeException implements ExceptionInterface
{
    // ── Semantic factories ───────────────────────────────────────────────

    public static function commandNotFound(string $name): self
    {
        return new CommandNotFoundException(
            "Command \"{$name}\" is not registered. Run 'list' to see available commands.",
            9001,
        );
    }

    public static function executionFailed(string $name, string $reason, ?\Throwable $previous = null): self
    {
        return new CommandExecutionException(
            message: "Command \"{$name}\" failed: {$reason}",
            code: 9002,
            previous: $previous,
        );
    }

    public static function invalidArgument(string $name, string $reason): self
    {
        return new InvalidArgumentException(
            "Argument \"{$name}\" is invalid: {$reason}",
            9003,
        );
    }

    public static function invalidOption(string $name, string $reason): self
    {
        return new InvalidOptionException(
            "Option \"--{$name}\" is invalid: {$reason}",
            9004,
        );
    }

    public static function missingRequiredArgument(string $name): self
    {
        return new InvalidArgumentException(
            "Required argument \"{$name}\" was not provided.",
            9005,
        );
    }

    public static function loaderFailure(string $path, string $reason, ?\Throwable $previous = null): self
    {
        return new CommandLoaderException(
            message: "Command loader failed for path \"{$path}\": {$reason}",
            code: 9006,
            previous: $previous,
        );
    }

    public static function outputWriteFailure(string $reason): self
    {
        return new OutputWriteException(
            "Output stream write failed: {$reason}",
            9007,
        );
    }
}
