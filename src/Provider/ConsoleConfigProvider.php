<?php

declare(strict_types=1);

namespace KaririCode\Console\Provider;

use KaririCode\Console\Core\Application;
use KaririCode\Contract\Configuration\ConfigProvider;

/**
 * Declares Console's configuration to a kariricode/contract configuration
 * builder: the 'console' key, the defaults Console falls back to and the
 * type of each key. The defaults are Application's own, which
 * ConfiguratorBridge and ConsoleServiceProvider also fall back to, so a key
 * that is not configured reads the same everywhere.
 *
 * Keys, relative to 'console':
 *   name        string  application name shown by help and list
 *   version     string  application version shown by --version
 *   scan_paths  array   directories searched for #[AsCommand] classes
 *   debug       bool    free-form flag for the host application
 *
 * Preconditions: none; the provider holds no state.
 *
 * Thread-safety: immutable and stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $builder->withProvider(new ConsoleConfigProvider());
 *     // console.name = 'KaririCode Console' unless the application sets it
 *
 * References: ADR-016; kariricode/contract SPEC-001 §2.1 (ConfigProvider).
 *
 * @see https://github.com/KaririCode-PHP/contract/blob/main/docs/spec/SPEC-001-contract.md
 *
 * @package KaririCode\Console\Provider
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.6.0
 */
final readonly class ConsoleConfigProvider implements ConfigProvider
{
    public const string NAMESPACE = 'console';

    #[\Override]
    public function namespace(): string
    {
        return self::NAMESPACE;
    }

    /**
     * @return array{name: string, version: string, scan_paths: list<string>, debug: bool}
     */
    #[\Override]
    public function defaults(): array
    {
        return [
            'name' => Application::DEFAULT_NAME,
            'version' => Application::DEFAULT_VERSION,
            'scan_paths' => [],
            'debug' => false,
        ];
    }

    /**
     * @return array{name: 'string', version: 'string', scan_paths: 'array', debug: 'bool'}
     */
    #[\Override]
    public function schema(): array
    {
        return [
            'name' => 'string',
            'version' => 'string',
            'scan_paths' => 'array',
            'debug' => 'bool',
        ];
    }
}
