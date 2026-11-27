# ADR-003 — Error Code Allocation

**Status:** Accepted  
**Date:** 2026-03-06  
**Component:** KaririCode\Console V4.0  

## Context

Spec v4.3.3 §8 (error model) requires semantic exception factories with non-overlapping
error code ranges. Existing ecosystem allocations (from cross-package audit):

| Range      | Package                        |
|------------|--------------------------------|
| 1000–1999  | KaririCode\AsyncHelper         |
| 2000–2999  | KaririCode\EventLoop           |
| 3000–3999  | KaririCode\Async               |
| 4000–4099  | KaririCode\Queue               |
| 8300–8399  | KaririCode\RateLimiter         |

## Decision

Console claims range **9000–9099**:

| Code | Factory                                  |
|------|------------------------------------------|
| 9001 | `commandNotFound(string $name)`          |
| 9002 | `executionFailed(string, string, ?Throwable)` |
| 9003 | `invalidArgument(string, string)`        |
| 9004 | `invalidOption(string, string)`          |
| 9005 | `missingRequiredArgument(string)`        |
| 9006 | `loaderFailure(string, string, ?Throwable)` |
| 9007 | `outputWriteFailure(string)`             |

Codes 9008–9099 are reserved for future Console features.

## Consequences

- All Console exceptions carry unique numeric codes for log correlation.
- No overlap with any existing KaririCode component.

---

## Amendment — 4.3.0 (2026-09-28)

- Codes are unchanged (9001–9007). `tests/Conformance` now asserts that every
  `ConsoleException` factory yields a code inside 9000–9099 and that no two
  factories share one; both assertions were proven to fail on planted
  violations.
- Neighbouring ranges recorded in sibling components' own documents — Router
  5100–5159, CircuitBreaker 7100–7199, Authorization 7300–7499, EventDispatcher
  8100–8199, RateLimiter 8300–8399, Runtime 9100–9199 — do not overlap
  9000–9099. The ecosystem-wide registry remains the reference of record.

## Amendment — 4.5.0 (2026-10-02)

- Cross-chat review: `kariricode/cli` declares 9200–9399 (no overlap), while
  `kariricode/parser` declares `PS-9000-9999`, which covers Console
  (9000–9099), Runtime (9100–9199) and CLI. Contract 2.11.0 identifies an
  exception by family plus numeric `getCode()`, so the ranges must be
  disjoint: the Parser range has to be reallocated at ecosystem level. Console
  keeps 9000–9099.

## Amendment — 4.6.0 (2026-11-27)

- The ecosystem registry of record is now `CODE-BLOCKS.md` in
  kariricode/exception 2.0.0. It lists Console at 9000–9099 and moves Parser
  to 9500–9999 and Framework to 9300–9399, which closes the collision noted
  in 4.5.0.
- Codes are unchanged. 9006 and 9007 now have their own classes
  (`CommandLoaderException`, `OutputWriteException`), and every class
  implements one kariricode/contract family (ADR-014).
