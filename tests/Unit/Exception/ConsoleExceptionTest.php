<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Exception;

use KaririCode\Console\Exception\CommandExecutionException;
use KaririCode\Console\Exception\CommandNotFoundException;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Exception\InvalidArgumentException;
use KaririCode\Console\Exception\InvalidOptionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsoleException::class)]
final class ConsoleExceptionTest extends TestCase
{
    public function testCommandNotFoundHasCorrectCodeAndSubtype(): void
    {
        $exception = ConsoleException::commandNotFound('foo:bar');

        self::assertInstanceOf(CommandNotFoundException::class, $exception);
        self::assertSame(9001, $exception->getCode());
        self::assertStringContainsString('foo:bar', $exception->getMessage());
    }

    public function testExecutionFailedHasCorrectCodeAndSubtype(): void
    {
        $exception = ConsoleException::executionFailed('app:run', 'timeout');

        self::assertInstanceOf(CommandExecutionException::class, $exception);
        self::assertSame(9002, $exception->getCode());
        self::assertStringContainsString('timeout', $exception->getMessage());
    }

    public function testInvalidArgumentHasCorrectCode(): void
    {
        $exception = ConsoleException::invalidArgument('name', 'too long');

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame(9003, $exception->getCode());
    }

    public function testInvalidOptionHasCorrectCode(): void
    {
        $exception = ConsoleException::invalidOption('format', 'unknown value');

        self::assertInstanceOf(InvalidOptionException::class, $exception);
        self::assertSame(9004, $exception->getCode());
    }

    public function testMissingRequiredArgumentHasCode9005(): void
    {
        $exception = ConsoleException::missingRequiredArgument('entity');

        self::assertSame(9005, $exception->getCode());
        self::assertStringContainsString('entity', $exception->getMessage());
    }

    public function testLoaderFailurePreservesPreviousThrowable(): void
    {
        $previous = new \RuntimeException('disk full');
        $exception = ConsoleException::loaderFailure('/var/app/cmd', 'disk full', $previous);

        self::assertSame(9006, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}
