<?php

declare(strict_types=1);

namespace KaririCode\Console\ValueObject;

use KaririCode\Console\Enum\ExitCode;

/**
 * Immutable record of a completed command execution.
 *
 * Carries the exit code and optional diagnostic message.
 * Produced by Application::run() and returned to the CLI entry-point.
 *
 *   $result = CommandResult::failure('deploy', 'remote refused', durationMs: 12.5);
 *   exit($result->toExitInt());
 *
 * @package KaririCode\Console\ValueObject
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final readonly class CommandResult
{
    public function __construct(
        public ExitCode $exitCode,
        public string $commandName,
        public ?string $errorMessage = null,
        public float $durationMs = 0.0,
    ) {}

    // ── Factories ────────────────────────────────────────────────────────

    public static function success(string $commandName, float $durationMs = 0.0): self
    {
        return new self(exitCode: ExitCode::Success, commandName: $commandName, durationMs: $durationMs);
    }

    public static function failure(
        string $commandName,
        string $errorMessage,
        float $durationMs = 0.0,
    ): self {
        return new self(exitCode: ExitCode::Failure, commandName: $commandName, errorMessage: $errorMessage, durationMs: $durationMs);
    }

    public static function invalid(string $commandName, string $reason, float $durationMs = 0.0): self
    {
        return new self(exitCode: ExitCode::Invalid, commandName: $commandName, errorMessage: $reason, durationMs: $durationMs);
    }

    // ── Accessors ────────────────────────────────────────────────────────

    public function isSuccess(): bool
    {
        return $this->exitCode->isSuccess();
    }

    public function toExitInt(): int
    {
        return $this->exitCode->exitCode();
    }
}
