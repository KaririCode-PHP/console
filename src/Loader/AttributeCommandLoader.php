<?php

declare(strict_types=1);

namespace KaririCode\Console\Loader;

use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\CommandLoader;
use KaririCode\Console\Exception\ConsoleException;

/**
 * Discovers command classes by scanning for #[AsCommand] attributes.
 *
 * When a KaririCode\ClassDiscovery scanner is provided (via the bridge),
 * it handles recursive directory traversal and FQCN extraction.
 * Without one, a direct glob-based fallback is used for lightweight
 * environments (see ADR-001, discovery strategy).
 *
 * Discovery result is memoised so repeated calls within the same
 * request never re-scan the filesystem.
 *
 *   $registry->addLoader(new AttributeCommandLoader([__DIR__ . '/src/Command']));
 *
 * Side effects: reads the files under the configured paths, once (memoised).
 *
 * @package KaririCode\Console\Loader
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class AttributeCommandLoader implements CommandLoader
{
    /** @var list<string>  absolute directory paths to scan */

    /** @var array<string, class-string<Command>>|null  memoised result */
    private ?array $cache = null;

    /** @param list<string> $paths */
    /**
     * @param list<string> $paths Directories scanned recursively for *.php files.
     */
    public function __construct(private readonly array $paths) {}

    // ── CommandLoader contract ───────────────────────────────────────────

    #[\Override]
    public function load(): array
    {
        return $this->cache ??= $this->discover();
    }

    #[\Override]
    public function isWarm(): bool
    {
        return $this->cache !== null;
    }

    // ── Discovery ────────────────────────────────────────────────────────

    /** @return array<string, class-string<Command>> */
    private function discover(): array
    {
        $commands = [];

        foreach ($this->paths as $path) {
            if (!is_dir($path)) {
                throw ConsoleException::loaderFailure($path, 'directory does not exist');
            }

            foreach ($this->globPhpFiles($path) as $file) {
                $className = $this->extractClassName($file);

                if ($className === null || !class_exists($className) || !is_a($className, class: Command::class, allow_string: true)) {
                    continue;
                }

                $reflection = new \ReflectionClass($className);
                $attributes = $reflection->getAttributes(AsCommand::class);

                if (empty($attributes)) {
                    continue;
                }

                // Canonical name only: CommandRegistry expands #[AsCommand] aliases
                // itself; emitting them here too listed the command twice.
                $commands[$attributes[0]->newInstance()->name] = $className;
            }
        }

        return $commands;
    }

    /**
     * Recursively collects all .php files under a directory.
     *
     * @return list<string>
     */
    private function globPhpFiles(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = (string) $file->getRealPath();
            }
        }

        return $files;
    }

    /**
     * Extracts the fully qualified class name from a PHP file by
     * parsing the namespace and class declarations via token_get_all().
     *
     * Returns null when the file contains no class declaration.
     *
     * @return string|null FQCN of the first declared class; not yet verified to exist
     */
    private function extractClassName(string $file): ?string
    {
        $source = @file_get_contents($file);

        if ($source === false) {
            return null;
        }

        $tokens    = token_get_all($source);
        $namespace = '';

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->parseNamespaceName($tokens, $index + 1);
            } elseif ($this->declaresType(id: $token[0], tokens: $tokens, index: $index)) {
                $name = $this->parseIdentifier($tokens, $index + 1);

                return $name === null ? null : ltrim("{$namespace}\\{$name}", '\\');
            }
        }

        return null;
    }

    /** @param list<array{0: int, 1: string, 2: int}|string> $tokens */
    private function declaresType(int $id, array $tokens, int $index): bool
    {
        return $id === T_INTERFACE || ($id === T_CLASS && $this->isClassDeclaration($tokens, $index));
    }

    /** @param list<mixed> $tokens */
    /**
     * A T_CLASS token declares a class only when not preceded by `::`
     * (`Foo::class`) or `new` (anonymous class) — the same disambiguation
     * Composer's class-map generator performs.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function isClassDeclaration(array $tokens, int $index): bool
    {
        for ($j = $index - 1; $j >= 0; $j--) {
            $token = $tokens[$j];

            if (is_array($token) && in_array($token[0], haystack: [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], strict: true)) {
                continue;
            }

            return !(is_array($token) && in_array($token[0], haystack: [T_DOUBLE_COLON, T_NEW], strict: true));
        }

        return true;
    }

    /** @param list<array{0: int, 1: string, 2: int}|string> $tokens */
    private function parseNamespaceName(array $tokens, int $offset): string
    {
        $parts = [];
        $count = count($tokens);

        for ($i = $offset; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }

                continue;
            }

            if (in_array($token[0], haystack: [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], strict: true)) {
                $parts[] = $token[1];
            }
        }

        return implode('', $parts);
    }

    /** @param list<mixed> $tokens */
    /** @param list<array{0: int, 1: string, 2: int}|string> $tokens */
    private function parseIdentifier(array $tokens, int $offset): ?string
    {
        $count = count($tokens);

        for ($i = $offset; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_WHITESPACE) {
                continue;
            }

            if ($token[0] === T_STRING) {
                return $token[1];
            }

            break;
        }

        return null;
    }
}
