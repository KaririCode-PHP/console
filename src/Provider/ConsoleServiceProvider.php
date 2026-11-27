<?php

declare(strict_types=1);

namespace KaririCode\Console\Provider;

use KaririCode\Console\Command\CompletionCommand;
use KaririCode\Console\Command\HelpCommand;
use KaririCode\Console\Command\ListCommand;
use KaririCode\Console\Contract\CommandLoader;
use KaririCode\Console\Core\Application;
use KaririCode\Console\Core\CommandRegistry;
use KaririCode\Console\Integration\ConfiguratorBridge;
use KaririCode\Console\Integration\Instrumentation;
use KaririCode\Console\Loader\AttributeCommandLoader;
use KaririCode\Console\Middleware\EventMiddleware;
use KaririCode\Console\Middleware\LoggingMiddleware;
use KaririCode\Console\Middleware\ObservabilityMiddleware;
use KaririCode\Console\Middleware\SignalMiddleware;
use KaririCode\Console\Middleware\TimingMiddleware;
use KaririCode\Contract\DI\BindableContainer;
use KaririCode\Contract\DI\ServiceProvider;

/**
 * Assembles a ready-to-run Application: registry, loaders, built-in
 * commands (help, list, completion) and the middleware stack. As a
 * kariricode/contract service provider, register() binds Application as a
 * singleton the container builds on first resolution (ADR-016).
 *
 *   $app = (new ConsoleServiceProvider(
 *       configurator:    new ConfiguratorBridge(fn() => $config['console']),
 *       instrumentation: new Instrumentation(logger: $psrLogger, events: $psr14Dispatcher),
 *   ))->build();
 *
 * Middleware order, outermost first:
 *   Observability → Logging → Events → Signal → Timing → execute()
 * The span covers the whole stack; Timing sits innermost so logs, events
 * and metrics all report the duration of execute() alone.
 *
 * @package KaririCode\Console\Provider
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final readonly class ConsoleServiceProvider implements ServiceProvider
{
    public function __construct(
        private ?ConfiguratorBridge $configurator = null,
        private ?CommandLoader $discovery = null,
        private Instrumentation $instrumentation = new Instrumentation(),
    ) {}

    // ── Contract DI ──────────────────────────────────────────────────────

    /**
     * Binds Application; nothing is built until the container resolves it,
     * so register() never reads configuration or scans for commands.
     */
    #[\Override]
    public function register(BindableContainer $container): void
    {
        $container->bind(abstract: Application::class, concrete: fn(): Application => $this->build(), singleton: true);
    }

    /** Console needs nothing from the finished container. */
    #[\Override]
    public function boot(): void {}

    // ── Factory ──────────────────────────────────────────────────────────

    /** Explicit $name/$version override the configuration. */
    public function build(?string $name = null, ?string $version = null): Application
    {
        $registry = new CommandRegistry();

        if ($this->discovery !== null) {
            $registry->addLoader($this->discovery);
        }

        $scanPaths = $this->configurator?->getScanPaths() ?? [];

        if ($scanPaths !== []) {
            $registry->addLoader(new AttributeCommandLoader($scanPaths));
        }

        $application = new Application(
            registry: $registry,
            name: $name ?? $this->configurator?->getApplicationName() ?? Application::DEFAULT_NAME,
            version: $version ?? $this->configurator?->getApplicationVersion() ?? Application::DEFAULT_VERSION,
        );

        // Built-ins go through registerCommand() so their name, description,
        // aliases and hidden flag come from the command itself — one source of truth.
        $registry->registerCommand(new HelpCommand($application));
        $registry->registerCommand(new ListCommand($application));
        $registry->registerCommand(new CompletionCommand($application));

        $this->wireMiddleware($application);

        return $application;
    }

    /** Zero-configuration factory for scripts and tests. */
    public static function buildApplication(string $name = Application::DEFAULT_NAME, string $version = Application::DEFAULT_VERSION): Application
    {
        return (new self())->build($name, $version);
    }

    // ── Private ──────────────────────────────────────────────────────────

    private function wireMiddleware(Application $application): void
    {
        $telemetry = $this->instrumentation;

        if ($telemetry->observability !== null) {
            $application->addMiddleware(new ObservabilityMiddleware($telemetry->observability));
        }

        if ($telemetry->logger !== null) {
            $application->addMiddleware(new LoggingMiddleware($telemetry->logger));
        }

        if ($telemetry->events !== null) {
            $application->addMiddleware(new EventMiddleware($telemetry->events));
        }

        $application->addMiddleware(new SignalMiddleware());
        $application->addMiddleware(new TimingMiddleware());
    }
}
