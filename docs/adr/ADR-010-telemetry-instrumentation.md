# ADR-010 — Telemetry Instrumentation (ARFA P5)

**Status:** Accepted
**Date:** 2026-09-28
**Component:** KaririCode\Console 4.3
**ARFA:** 1.3 P5 (Continuous observability)

## Context

ARFA 1.3 Level 1 requires P1, P4 and P5, and P5 means every operation has a
trace, metrics and a structured log. Up to 4.2 Console had logs only, and
`EventDispatcherBridge` existed but was never called, while SPEC-001 claimed
it emitted lifecycle events.

## Decision

`ConsoleServiceProvider` accepts an `Instrumentation` parameter object with
three optional bridges, each taking `\Closure`s so Console has no telemetry
dependency:

| Bridge | Middleware | Signal |
|---|---|---|
| `LoggingBridge` | `LoggingMiddleware` | `console.command.start/finish/error` records |
| `EventDispatcherBridge` | `EventMiddleware` | `console.before_execute`, `after_execute`, `on_failure` |
| `ObservabilityBridge` | `ObservabilityMiddleware` | `console.command.duration` histogram + one `CommandSpan` |

Metric and span follow the OpenTelemetry semantic conventions for CLI
programs (status: Development):

- duration in seconds;
- span kind INTERNAL, named after the canonical command — a documented
  low-cardinality alternative to the executable name, which the conventions
  allow;
- `process.exit.code`, `process.pid`, `process.executable.name`, and
  `error.type` when the exit code is not 0 (exception class, or
  `failure`/`invalid`);
- `process.command_args` is never collected: the conventions advise against
  it by default, and arguments routinely carry personal data.

`ObservabilityMiddleware` is registered outermost so its span covers every
other layer.

## Alternatives Considered

- **Depend on `open-telemetry/api`.** Rejected: breaks the zero-dependency
  rule (Spec v4.3.3 §28, principle 16); an exporter can turn `CommandSpan` into a real span.
- **Events only.** Rejected: events are not metrics or traces.

## Consequences

- ARFA Level 1 can be declared without overclaiming (SPEC-001 §11).
- The OTel CLI conventions are still "Development"; attribute names may change
  and would be tracked here.

## References

- KaririCode Spec v4.3.3 §4 (conformance levels), ARFA 1.3 P5
- OpenTelemetry Semantic Conventions — CLI programs (execution spans)

## Amendment — 4.5.0 (2026-10-02)

Logs and events moved from `\Closure` bridges to PSR-3 and PSR-14, and every
signal now carries the run's `TraceId` (ADR-013). Metrics and spans keep
`ObservabilityBridge` until the ecosystem settles a metrics port.
