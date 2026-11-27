<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Signal;

use KaririCode\Console\Enum\Signal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Signal::class)]
final class SignalEnumTest extends TestCase
{
    public function testLabelReturnsHumanReadableName(): void
    {
        self::assertSame('SIGINT', Signal::Interrupt->label());
        self::assertSame('SIGTERM', Signal::Terminate->label());
        self::assertSame('SIGHUP', Signal::Hangup->label());
    }

    public function testPcntlConstantReturnsZeroWhenExtensionAbsent(): void
    {
        if (Signal::isSupported()) {
            $this->markTestSkipped('PCNTL is available — zero-fallback path not exercised');
        }

        self::assertSame(0, Signal::Interrupt->pcntlConstant());
    }

    public function testFromPcntlIntReturnsNullForUnknown(): void
    {
        if (!Signal::isSupported()) {
            $this->markTestSkipped('PCNTL unavailable');
        }

        self::assertNull(Signal::fromPcntlInt(99999));
    }

    public function testFromPcntlIntResolvesKnownSignals(): void
    {
        if (!Signal::isSupported()) {
            $this->markTestSkipped('PCNTL unavailable');
        }

        self::assertSame(Signal::Interrupt, Signal::fromPcntlInt(SIGINT));
        self::assertSame(Signal::Terminate, Signal::fromPcntlInt(SIGTERM));
        self::assertSame(Signal::Hangup, Signal::fromPcntlInt(SIGHUP));
    }
}
