# ADR-005 — Middleware Pipeline as First-Class Cross-Cutting Mechanism

**Status:** Accepted  
**Date:** 2026-03-06  
**ARFA:** 1.3 P5 (hosts the telemetry layers)

## Context

Symfony Console and Laravel Artisan expose cross-cutting concerns (timing, logging, signal handling) via a global event dispatcher (`ConsoleEvents::COMMAND`, `ConsoleEvents::TERMINATE`). Event listeners cannot short-circuit execution and must be registered on the application object, not on individual commands.

## Decision

`MiddlewarePipeline` provides an ordered list of `CommandMiddleware` layers that wrap command execution. Each layer receives the full `CommandContext` and a `$next` callable. Returning without calling `$next` short-circuits the pipeline.

Application stores the pipeline and applies it in `run()`. Default stack (installed by `ConsoleServiceProvider`):

```
TimingMiddleware → LoggingMiddleware? → SignalMiddleware → execute()
```

## Consequences

- Cross-cutting logic is composable and testable independently of Application
- Short-circuit is possible (dry-run, rate-limiting, auth gates)
- Middleware can be added per-application or per-environment
- No event dispatcher required — reduces coupling and makes tests simpler

## Rejected alternatives

- Global event dispatcher (Symfony approach): cannot short-circuit, not composable
- Decorator per command: too much boilerplate

---

## Amendment — 4.3.0 (2026-09-28)

- Provider order, outermost first: Observability → Logging → Events → Signal →
  Timing → `execute()`. `TimingMiddleware` was registered outermost while the
  provider comment claimed logging saw its measurement; innermost, every outer
  layer now reports the duration of `execute()` alone.
- New `EventMiddleware` wires `EventDispatcherBridge`, which nothing used, and
  `ObservabilityMiddleware` adds metrics and spans (ADR-010). Layers that observe
  failures re-throw; `run()` converts the exception.
- Classification corrected: ARFA P2 is `Result` composition on a persistent
  runtime (Level 2); the pipeline is not claimed as P2.
