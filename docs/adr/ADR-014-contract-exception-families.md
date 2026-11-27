# ADR-014 — Contract Exception Families

**Status:** Accepted
**Date:** 2026-11-27
**Component:** KaririCode\Console 4.6
**ARFA:** 1.3 (P4 isolation at the edge)

## Context

Spec v4.3.3 §8.4 asks every component to throw exceptions that implement
one family of `kariricode/contract` (`Validation`, `NotFound`,
`Configuration`, `Protocol`, `ContainerFailure`, `Runtime`) under the root
`ExceptionInterface`, so an edge maps failures without knowing the
component. Console 4.5.0 deferred this until contract 2.11.0 was on
Packagist; 2.12.0 is there now. Until 4.5.0, `ConsoleException` was a
concrete class, and the loader (9006) and output (9007) failures were plain
instances of it, so they belonged to no family.

## Decision

- `kariricode/contract` ^2.12 becomes a runtime dependency (the conformance
  rule already allowed it, §31).
- `ConsoleException` is abstract and implements `ExceptionInterface`; the
  seven factories and codes of ADR-003 stay. Each factory returns a final
  class with exactly one family:

| Code | Class | Family |
|---|---|---|
| 9001 | `CommandNotFoundException` | `NotFound` |
| 9002 | `CommandExecutionException` | `Runtime` |
| 9003, 9005 | `InvalidArgumentException` | `Validation` |
| 9004 | `InvalidOptionException` | `Validation` |
| 9006 | `CommandLoaderException` (new) | `Configuration` |
| 9007 | `OutputWriteException` (new) | `Runtime` |

- Messages keep naming the command, argument or option the operator typed.
  Console's messages are read by the person at the terminal, and naming what
  they typed is the point of a usage error; errata E-047 targets values that
  carry personal data into logs, which a command name is not. The reason
  texts come from Console itself.
- Messages carry no `[CN_90xx]` tag: they are user-facing output, and the
  golden files would change for no reader's benefit. The code stays in
  `getCode()` and in the log context.

## Consequences

- An edge that catches `KaririCode\Contract\Exception\NotFound` or
  `Validation` now handles Console's usage errors without importing Console.
- `catch (ConsoleException $e)` keeps working; `new ConsoleException(...)`
  no longer compiles, which no published code did (the line was never
  released).
- `DI\ServiceProvider` and `Configuration\ConfigProvider`, also deferred in
  4.5.0, stay open: they need a decision on what Console binds and
  configures.
