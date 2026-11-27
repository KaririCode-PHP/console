<?php

declare(strict_types=1);

namespace KaririCode\Console\Middleware;

use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Contract\CommandMiddleware;
use KaririCode\Console\Event\CommandFailed;
use KaririCode\Console\Event\CommandFinished;
use KaririCode\Console\Event\CommandStarted;
use KaririCode\Console\ValueObject\CommandResult;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes the command lifecycle as PSR-14 events: CommandStarted, then
 * CommandFinished for any result or CommandFailed for a throwable, which is
 * re-thrown untouched so Application still converts it.
 *
 *   $application->addMiddleware(new EventMiddleware($psr14Dispatcher));
 *
 * Side effects: dispatches events, so listeners run synchronously here.
 *
 * @package KaririCode\Console\Middleware
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.3.0
 */
final readonly class EventMiddleware implements CommandMiddleware
{
    public function __construct(private EventDispatcherInterface $events) {}

    #[\Override]
    public function handle(CommandContext $context, callable $next): CommandResult
    {
        $this->events->dispatch(new CommandStarted(commandName: $context->commandName, traceId: $context->traceId));

        try {
            $result = $next($context);
        } catch (\Throwable $e) {
            $this->events->dispatch(new CommandFailed(commandName: $context->commandName, traceId: $context->traceId, error: $e));

            throw $e;
        }

        $this->events->dispatch(new CommandFinished(result: $result, traceId: $context->traceId));

        return $result;
    }
}
