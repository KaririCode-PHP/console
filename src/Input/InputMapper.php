<?php

declare(strict_types=1);

namespace KaririCode\Console\Input;

use KaririCode\Console\Attribute\Argument;
use KaririCode\Console\Attribute\MapInput;
use KaririCode\Console\Attribute\Option;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Maps parsed Input values into a final readonly DTO constructor.
 *
 * The constructor signature is read via reflection, all argument/option
 * values are collected, and the DTO is built with a single
 * `new $class(...$values)`. Because nothing is assigned after
 * construction, the DTO can be a final readonly class (a DPO — Spec v4.3.3
 * §5.1). For comparison, Symfony 7.4's #[MapInput] populates public
 * properties of the DTO.
 *
 * The command still declares its input: call registerInto() from
 * configure() to add the DTO's #[Argument]/#[Option] parameters.
 *
 * Usage:
 *
 *   $dto = InputMapper::map(DeployInput::class, $input);
 *   echo $dto->environment; // 'production'
 *   echo $dto->force;       // true
 *
 * @package KaririCode\Console\Input
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
final class InputMapper
{
    // ── Public API ───────────────────────────────────────────────────────

    /**
     * Hydrates a DTO class from the parsed Input.
     *
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return T
     * @throws \ReflectionException
     */
    public static function map(string $dtoClass, Input $input): object
    {
        self::assertIsMapInputTarget($dtoClass);

        $reflection = new \ReflectionClass($dtoClass);
        $constructor = $reflection->getConstructor()
            ?? throw ConsoleException::executionFailed(
                $dtoClass,
                'InputMapper requires a constructor with typed parameters',
            );

        $args = array_map(
            static fn(\ReflectionParameter $param): mixed => self::resolveParameter($param, $input),
            $constructor->getParameters(),
        );

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Registers all #[Argument] and #[Option] parameters from $dtoClass
     * into $definition. Call it from the command's configure().
     *
     * @template T of object
     * @param class-string<T> $dtoClass
     */
    public static function registerInto(string $dtoClass, InputDefinition $definition): void
    {
        $reflection = new \ReflectionClass($dtoClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return;
        }

        foreach ($constructor->getParameters() as $param) {
            $argAttrs = $param->getAttributes(Argument::class);
            $optAttrs = $param->getAttributes(Option::class);

            if ($argAttrs !== []) {
                /** @var Argument $attr */
                $attr = $argAttrs[0]->newInstance();
                $definition->addArgument(new ArgumentDescriptor(
                    name: $attr->name ?? $param->getName(),
                    description: $attr->description,
                    required: !$param->isOptional(),
                    default: $param->isOptional() ? ($param->getDefaultValue() ?? null) : null,
                    isArray: $attr->isArray,
                ));
            } elseif ($optAttrs !== []) {
                /** @var Option $attr */
                $attr = $optAttrs[0]->newInstance();
                $definition->addOption(new OptionDescriptor(
                    name: $attr->name ?? $param->getName(),
                    description: $attr->description,
                    shortcut: $attr->shortcut,
                    default: $param->isOptional() ? $param->getDefaultValue() : $attr->default,
                    acceptsValue: $attr->acceptsValue,
                    isArray: $attr->isArray,
                ));
            }
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private static function resolveParameter(\ReflectionParameter $param, Input $input): mixed
    {
        /** @var mixed $default The DTO parameter's declared default, of any type. */
        $default  = $param->isOptional() ? $param->getDefaultValue() : null;
        $type     = $param->getType();
        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'string';

        $argumentName = self::argumentNameOf($param);

        if ($argumentName !== null && $input->hasArgument($argumentName)) {
            return self::convert(
                raw: $input->getArgument($argumentName),
                type: $typeName,
                default: $default,
                reject: static fn(string $reason): ConsoleException => ConsoleException::invalidArgument($argumentName, $reason),
            );
        }

        $optionName = self::optionNameOf($param);

        if ($optionName !== null && $input->hasOption($optionName)) {
            return self::convert(
                raw: $input->getOption($optionName),
                type: $typeName,
                default: $default,
                reject: static fn(string $reason): ConsoleException => ConsoleException::invalidOption($optionName, $reason),
            );
        }

        return $default;
    }

    /** #[Argument] name, else the parameter name when it is not an option. */
    private static function argumentNameOf(\ReflectionParameter $param): ?string
    {
        $named = ($param->getAttributes(Argument::class)[0] ?? null)?->newInstance()->name;

        return $named ?? ($param->getAttributes(Option::class) === [] ? $param->getName() : null);
    }

    /** #[Option] name, else the parameter name when it is not an argument. */
    private static function optionNameOf(\ReflectionParameter $param): ?string
    {
        $named = ($param->getAttributes(Option::class)[0] ?? null)?->newInstance()->name;

        return $named ?? ($param->getAttributes(Argument::class) === [] ? $param->getName() : null);
    }

    /**
     * @param \Closure(string): ConsoleException $reject Builds the error for this input.
     *
     * @throws ConsoleException 9003/9004 when the value cannot represent the type
     */
    private static function convert(mixed $raw, string $type, mixed $default, \Closure $reject): mixed
    {
        try {
            return self::coerce(raw: $raw, type: $type, default: $default);
        } catch (\UnexpectedValueException $e) {
            throw $reject($e->getMessage());
        }
    }

    /**
     * Converts a raw CLI value to the parameter's declared type, strictly:
     * malformed input is an error, never silently replaced by a default
     * ("abc" for an int, "1.5" for an int, "nope" for a bool).
     *
     * @throws \UnexpectedValueException when $raw cannot represent $type
     */
    private static function coerce(mixed $raw, string $type, mixed $default): mixed
    {
        if ($raw === null) {
            return $default;
        }

        return match ($type) {
            'bool'  => is_bool($raw) ? $raw : self::toBool($raw),
            'int'   => is_int($raw) ? $raw : (filter_var($raw, filter: FILTER_VALIDATE_INT, options: FILTER_NULL_ON_FAILURE)
                ?? throw new \UnexpectedValueException('expects an integer, got ' . self::describe($raw))),
            'float' => is_float($raw) ? $raw : (filter_var($raw, filter: FILTER_VALIDATE_FLOAT, options: FILTER_NULL_ON_FAILURE)
                ?? throw new \UnexpectedValueException('expects a number, got ' . self::describe($raw))),
            'array' => is_array($raw) ? $raw : [$raw],
            default => is_scalar($raw) ? (string) $raw
                : throw new \UnexpectedValueException('expects a single value, got ' . self::describe($raw)),
        };
    }

    /** @throws \UnexpectedValueException */
    private static function toBool(mixed $raw): bool
    {
        $word = is_scalar($raw) ? strtolower(trim((string) $raw)) : '';

        return match ($word) {
            '1', 'true', 'yes', 'y', 'on'       => true,
            '0', 'false', 'no', 'n', 'off', ''  => false,
            default => throw new \UnexpectedValueException('expects yes/no, true/false, on/off or 1/0, got ' . self::describe($raw)),
        };
    }

    private static function describe(mixed $raw): string
    {
        return is_scalar($raw) ? var_export($raw, return: true) : get_debug_type($raw);
    }

    /**
     * @param class-string $dtoClass
     */
    private static function assertIsMapInputTarget(string $dtoClass): void
    {
        if (!class_exists($dtoClass)) {
            throw ConsoleException::executionFailed($dtoClass, 'class does not exist');
        }

        $attrs = (new \ReflectionClass($dtoClass))->getAttributes(MapInput::class);

        if ($attrs === []) {
            throw ConsoleException::executionFailed(
                $dtoClass,
                "InputMapper requires the class to be annotated with #[MapInput]",
            );
        }
    }
}
