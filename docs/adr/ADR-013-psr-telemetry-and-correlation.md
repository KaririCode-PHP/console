# ADR-013 — PSR-3/PSR-14 Telemetry and Trace Correlation

**Status:** Accepted
**Date:** 2026-10-02
**Component:** KaririCode\Console 4.5
**ARFA:** 1.3 P5 (Continuous observability)

## Context

Up to 4.4, Console emitted logs and lifecycle events through `\Closure`
bridges (`LoggingBridge`, `EventDispatcherBridge`). The cross-component audit
in the Contract work, based on Spec v4.3.3, found three coupling styles in the
ecosystem where §31 allows one: every coupling goes through
`kariricode/contract`, with PSR standards as the natural exception, and
logging and events are covered by PSR-3 and PSR-14 rather than by contract
ports. Principle 16 permits PSR contracts as dependencies. Separately, E-021
requires correlated telemetry: logs, events and spans of one run must share an
identifier, and Console's did not.

## Decision

- `LoggingMiddleware` takes a `Psr\Log\LoggerInterface`; a throwable travels in
  the context under `exception` (PSR-3 §1.3).
- `EventMiddleware` takes a `Psr\EventDispatcher\EventDispatcherInterface` and
  dispatches event objects named per §8.4: `CommandStarted`,
  `CommandFinished`, `CommandFailed`.
- `Application::run()` creates a `TraceId` per run — a W3C Trace Context
  trace-id from `random_bytes()` (E-019) — exposed as `CommandContext::$traceId`
  and carried by every log record (`trace_id`), every event and the span
  (with its own 8-byte span-id).
- The `\Closure` bridges for logs and events are removed; `ObservabilityBridge`
  stays for metrics and spans until the ecosystem decides between a contract
  port and PSR-14 events.

## Consequences

- Runtime dependencies: `psr/log` and `psr/event-dispatcher` (interfaces).
  The conformance rule allows PHP, extensions, `psr/*` and `kariricode/contract`.
- Hosts pass their PSR logger and dispatcher directly; no adapter closures.
- Breaking for 4.4 users of `LoggingBridge`/`EventDispatcherBridge`
  (unpublished line).

## References

- PSR-3 (Logger Interface), PSR-14 (Event Dispatcher)
- W3C Recommendation, *Trace Context* (trace-id and parent-id formats)
- KaririCode Spec v4.3.3 §8.4, §28 (principle 16), §31, E-019, E-021
