<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Enum;

use KaririCode\Console\Enum\ExitCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExitCode::class)]
final class ExitCodeTest extends TestCase
{
    public function testExitCodeValues(): void
    {
        self::assertSame(0, ExitCode::Success->exitCode());
        self::assertSame(1, ExitCode::Failure->exitCode());
        self::assertSame(2, ExitCode::Invalid->exitCode());
    }

    public function testFromIntMapsZeroToSuccess(): void
    {
        self::assertSame(ExitCode::Success, ExitCode::fromInt(0));
    }

    public function testFromIntMapsOneToFailure(): void
    {
        self::assertSame(ExitCode::Failure, ExitCode::fromInt(1));
    }

    public function testFromIntMapsTwoToInvalidAndAnyOtherFailureToFailure(): void
    {
        // Only 2 means "invalid usage"; every other non-zero status is a failure.
        self::assertSame(ExitCode::Invalid, ExitCode::fromInt(2));
        self::assertSame(ExitCode::Failure, ExitCode::fromInt(3));
        self::assertSame(ExitCode::Failure, ExitCode::fromInt(130));
        self::assertSame(ExitCode::Failure, ExitCode::fromInt(-1));
    }

    public function testIsSuccessReturnsTrueOnlyForSuccess(): void
    {
        self::assertTrue(ExitCode::Success->isSuccess());
        self::assertFalse(ExitCode::Failure->isSuccess());
        self::assertFalse(ExitCode::Invalid->isSuccess());
    }
}
