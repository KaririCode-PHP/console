<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Core;

use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Contract\CommandMiddleware;
use KaririCode\Console\Core\MiddlewarePipeline;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Tests\Fixture\RecordingMiddleware;
use KaririCode\Console\ValueObject\CommandResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MiddlewarePipeline::class)]
final class MiddlewarePipelineTest extends TestCase
{
    private function makeContext(): CommandContext
    {
        return $this->createStub(CommandContext::class);
    }

    private function makeResult(ExitCode $code = ExitCode::Success, string $name = 'test'): CommandResult
    {
        return new CommandResult($code, $name);
    }

    private function makePassing(): RecordingMiddleware
    {
        return new RecordingMiddleware('m');
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function testEmptyPipelineCallsTerminalDirectly(): void
    {
        $pipeline = new MiddlewarePipeline();
        $context  = $this->makeContext();
        $called   = false;

        $result = $pipeline->run($context, function () use (&$called): CommandResult {
            $called = true;
            return new CommandResult(ExitCode::Success, 'cmd');
        });

        self::assertTrue($called);
        self::assertTrue($result->isSuccess());
    }

    public function testMiddlewareExecutesAroundTerminal(): void
    {
        $layer = $this->makePassing();

        $pipeline = (new MiddlewarePipeline())->pipe($layer);

        $pipeline->run($this->makeContext(), fn() => $this->makeResult());

        self::assertSame(['m-before', 'm-after'], $layer->log->getArrayCopy());
    }

    public function testMultipleLayersExecuteInRegistrationOrder(): void
    {
        /** @var \ArrayObject<int, string> $order */
        $order  = new \ArrayObject();
        $first  = new RecordingMiddleware('first', $order);
        $second = new RecordingMiddleware('second', $order);

        $pipeline = (new MiddlewarePipeline())->pipe($first)->pipe($second);
        $pipeline->run($this->makeContext(), fn() => $this->makeResult());

        // First registered = outermost
        self::assertSame(
            ['first-before', 'second-before', 'second-after', 'first-after'],
            $order->getArrayCopy(),
        );
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $terminal = false;

        $blocker = new class implements CommandMiddleware {
            public function handle(CommandContext $context, callable $next): CommandResult
            {
                // Short-circuits — does NOT call $next
                return new CommandResult(ExitCode::Invalid, 'blocked');
            }
        };

        $pipeline = (new MiddlewarePipeline())->pipe($blocker);
        $result   = $pipeline->run($this->makeContext(), function () use (&$terminal): CommandResult {
            $terminal = true;
            return new CommandResult(ExitCode::Success, 'never');
        });

        self::assertFalse($terminal);
        self::assertSame(2, $result->toExitInt());
    }

    public function testPipeReturnsNewImmutableInstance(): void
    {
        $pipeline = new MiddlewarePipeline();
        $piped    = $pipeline->pipe($this->makePassing());

        self::assertTrue($pipeline->isEmpty());
        self::assertFalse($piped->isEmpty());
    }
}
