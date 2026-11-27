<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Enum;

use KaririCode\Console\Enum\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Severity::class)]
final class SeverityTest extends TestCase
{
    public function testLabelsArePadded(): void
    {
        self::assertSame('ERROR', Severity::Error->label());
        self::assertSame('WARN ', Severity::Warning->label());
        self::assertSame('INFO ', Severity::Info->label());
        self::assertSame('HINT ', Severity::Hint->label());
    }

    public function testAnsiColorIsNonEmptyForEveryCase(): void
    {
        foreach (Severity::cases() as $severity) {
            self::assertNotSame('', $severity->ansiColor());
        }
    }

    public function testGithubAnnotationMapping(): void
    {
        self::assertSame('error', Severity::Error->githubAnnotation());
        self::assertSame('warning', Severity::Warning->githubAnnotation());
        self::assertSame('notice', Severity::Info->githubAnnotation());
        self::assertSame('notice', Severity::Hint->githubAnnotation());
    }

    public function testIsAtLeastFollowsSeverityOrder(): void
    {
        // Error is the most severe; Hint the least.
        self::assertTrue(Severity::Error->isAtLeast(Severity::Warning));
        self::assertTrue(Severity::Error->isAtLeast(Severity::Error));
        self::assertFalse(Severity::Warning->isAtLeast(Severity::Error));
        self::assertFalse(Severity::Hint->isAtLeast(Severity::Info));
    }
}
