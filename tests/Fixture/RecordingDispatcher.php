<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Fixture;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * PSR-14 dispatcher that keeps every event, optionally appending
 * "event:<ShortClassName>" to a shared signal list.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    /** @param \ArrayObject<int, string>|null $signals */
    public function __construct(private readonly ?\ArrayObject $signals = null) {}

    #[\Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;
        $this->signals?->append('event:' . (new \ReflectionClass($event))->getShortName());

        return $event;
    }
}
