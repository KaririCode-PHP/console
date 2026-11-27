<?php

declare(strict_types=1);

namespace KaririCode\Console\Integration;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Groups the optional telemetry sinks for ConsoleServiceProvider (P5 signals:
 * logs, lifecycle events, metrics and spans). Logs and events use the PSR
 * standards the ecosystem shares (PSR-3, PSR-14, Spec v4.3.3 §31); metrics and
 * spans use ObservabilityBridge until the ecosystem settles a metrics port.
 *
 *   new Instrumentation(
 *       logger: $psrLogger,
 *       events: $psr14Dispatcher,
 *       observability: new ObservabilityBridge($meter->record(...), $tracer->export(...)),
 *   );
 *
 * @package KaririCode\Console\Integration
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.3.0
 */
final readonly class Instrumentation
{
    public function __construct(
        public ?LoggerInterface $logger = null,
        public ?EventDispatcherInterface $events = null,
        public ?ObservabilityBridge $observability = null,
    ) {}
}
