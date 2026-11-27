<?php

declare(strict_types=1);

namespace KaririCode\Console\Middleware;

use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Contract\CommandMiddleware;
use KaririCode\Console\ValueObject\CommandResult;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Structured PSR-3 records for every command run (ARFA 1.3 P5).
 *
 * Records `console.command.start`, then `console.command.finish` (info, or
 * error for a failed result) or `console.command.error` for a throwable,
 * which is passed under the `exception` key as PSR-3 §1.3 requires. Every
 * record carries `command` and `trace_id` (E-021); command arguments are
 * never logged (personal data).
 *
 *   $application->addMiddleware(new LoggingMiddleware($psrLogger));
 *
 * Side effects: writes to the injected logger; re-throws what it receives.
 *
 * @package KaririCode\Console\Middleware
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
final readonly class LoggingMiddleware implements CommandMiddleware
{
    public function __construct(private LoggerInterface $logger) {}

    #[\Override]
    public function handle(CommandContext $context, callable $next): CommandResult
    {
        $correlation = ['command' => $context->commandName, 'trace_id' => $context->traceId->value];

        $this->logger->info('console.command.start', $correlation);

        try {
            $result = $next($context);
        } catch (\Throwable $e) {
            $this->logger->error('console.command.error', $correlation + ['exception' => $e]);

            throw $e;
        }

        $this->logger->log(
            level: $result->isSuccess() ? LogLevel::INFO : LogLevel::ERROR,
            message: 'console.command.finish',
            context: $correlation + array_filter(
                ['exit_code' => $result->toExitInt(), 'duration_ms' => $result->durationMs, 'error' => $result->errorMessage],
                static fn(mixed $value): bool => $value !== null,
            ),
        );

        return $result;
    }
}
