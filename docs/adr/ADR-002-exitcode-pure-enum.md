# ADR-002 — ExitCode as Pure Enum

**Status:** Accepted  
**Date:** 2026-03-06  
**Component:** KaririCode\Console V4.0  

## Context

Spec v4.3.3 §10 makes enums pure by default; E-003 permits backing only for an external contract, and a process status is produced by `exitCode()`, so no backing value is needed. V3.1 used integer constants on a
`Command` interface. POSIX requires integer exit codes for `exit()`.

## Decision

`ExitCode` is a pure enum with an `exitCode(): int` method resolved via `match()`.
`ExitCode::fromInt()` provides a round-trip factory.

`Command::execute()` returns `ExitCode` (not `int`), so the type system prevents
invalid codes entirely. The CLI entry-point script calls `exit($result->toExitInt())`.

## Consequences

- No backed enum: pure by default (Spec v4.3.3 §10; E-003 allows backing only for an external contract).
- Adding a new exit code requires updating `exitCode()` match expression only.
- `CommandResult::toExitInt()` is the single exit-to-integer conversion point.

---

## Amendment — 4.3.0 (2026-09-28)

- `ExitCode::fromInt()` is lossy by design and now maps `0 → Success`,
  `2 → Invalid`, any other status → `Failure`. The earlier "round-trip" wording
  only held for 0–2, and every unknown failure (e.g. 130) became "invalid usage".
- `Application::run()` returns `Invalid` (2) for usage errors (codes 9003–9005)
  and `Failure` (1) otherwise — the GNU/bash convention for usage errors.
