<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit\Exception;

use KaririCode\Console\Exception\CommandExecutionException;
use KaririCode\Console\Exception\CommandLoaderException;
use KaririCode\Console\Exception\CommandNotFoundException;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Exception\InvalidArgumentException;
use KaririCode\Console\Exception\InvalidOptionException;
use KaririCode\Console\Exception\OutputWriteException;
use KaririCode\Contract\Exception\Configuration;
use KaririCode\Contract\Exception\ExceptionInterface;
use KaririCode\Contract\Exception\NotFound;
use KaririCode\Contract\Exception\Runtime;
use KaririCode\Contract\Exception\Validation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsoleException::class)]
#[CoversClass(CommandLoaderException::class)]
#[CoversClass(OutputWriteException::class)]
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

    public function testOutputWriteFailureHasCode9007AndItsOwnType(): void
    {
        $exception = ConsoleException::outputWriteFailure('broken pipe');

        self::assertInstanceOf(OutputWriteException::class, $exception);
        self::assertSame(9007, $exception->getCode());
    }

    /**
     * ADR-014: every factory yields a final class that implements exactly one
     * kariricode/contract family, under the contract's root interface.
     *
     * @return iterable<string, array{ConsoleException, class-string, class-string}>
     */
    public static function families(): iterable
    {
        yield '9001' => [ConsoleException::commandNotFound('x'), CommandNotFoundException::class, NotFound::class];
        yield '9002' => [ConsoleException::executionFailed('x', 'y'), CommandExecutionException::class, Runtime::class];
        yield '9003' => [ConsoleException::invalidArgument('x', 'y'), InvalidArgumentException::class, Validation::class];
        yield '9004' => [ConsoleException::invalidOption('x', 'y'), InvalidOptionException::class, Validation::class];
        yield '9005' => [ConsoleException::missingRequiredArgument('x'), InvalidArgumentException::class, Validation::class];
        yield '9006' => [ConsoleException::loaderFailure('x', 'y'), CommandLoaderException::class, Configuration::class];
        yield '9007' => [ConsoleException::outputWriteFailure('x'), OutputWriteException::class, Runtime::class];
    }

    /**
     * @param class-string $type
     * @param class-string $family
     */
    #[DataProvider('families')]
    public function testEachFailureImplementsOneContractFamily(ConsoleException $exception, string $type, string $family): void
    {
        $families = [Validation::class, NotFound::class, Configuration::class, Runtime::class,
            \KaririCode\Contract\Exception\Protocol::class, \KaririCode\Contract\Exception\ContainerFailure::class];

        self::assertSame($type, $exception::class);
        self::assertTrue((new \ReflectionClass($exception))->isFinal());
        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertSame([$family], array_values(array_intersect($families, class_implements($exception) ?: [])));
    }

    public function testTheRootIsAbstract(): void
    {
        self::assertTrue((new \ReflectionClass(ConsoleException::class))->isAbstract());
    }

    public function testLoaderFailurePreservesPreviousThrowable(): void
    {
        $previous = new \RuntimeException('disk full');
        $exception = ConsoleException::loaderFailure('/var/app/cmd', 'disk full', $previous);

        self::assertSame(9006, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}
