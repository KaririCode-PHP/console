<?php

declare(strict_types=1);

namespace KaririCode\Console\ValueObject;

use KaririCode\Console\Exception\ConsoleException;

/**
 * Immutable descriptor for a command option (--flag or --key=value).
 *
 *   new OptionDescriptor('tag', 'Release tags', shortcut: 't', isArray: true);
 *
 * @package KaririCode\Console\ValueObject
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final readonly class OptionDescriptor
{
    public function __construct(
        public string $name,
        public string $description,
        public ?string $shortcut = null,
        public mixed $default = null,
        public bool $acceptsValue = true,
        public bool $isArray = false,
    ) {
        $problem = self::nameProblem($name)
            ?? self::shortcutProblem($shortcut)
            ?? self::valueProblem(default: $default, acceptsValue: $acceptsValue, isArray: $isArray);

        if ($problem !== null) {
            throw ConsoleException::invalidOption($name, $problem);
        }
    }

    private static function nameProblem(string $name): ?string
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_:.-]*$/', $name) === 1
            ? null
            : 'name must start with a letter or digit and contain no spaces or "="';
    }

    private static function shortcutProblem(?string $shortcut): ?string
    {
        return $shortcut === null || preg_match('/^[A-Za-z0-9]$/', $shortcut) === 1
            ? null
            : 'shortcut must be exactly one letter or digit';
    }

    private static function valueProblem(mixed $default, bool $acceptsValue, bool $isArray): ?string
    {
        if (!$acceptsValue && ($isArray || ($default !== null && $default !== false))) {
            return 'a flag cannot be an array or have a non-false default';
        }

        return $isArray && $default !== null && !is_array($default) ? 'an array option default must be an array' : null;
    }

    public function isFlag(): bool
    {
        return !$this->acceptsValue;
    }
}
