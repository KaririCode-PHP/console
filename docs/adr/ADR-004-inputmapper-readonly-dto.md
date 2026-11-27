# ADR-004 — InputMapper: PHP 8.4 Readonly DTO Hydration

**Status:** Accepted  
**Date:** 2026-03-06  
**ARFA:** 1.3 P1 (Immutable state)

## Context

Symfony 7.2 introduced `#[MapInput]` which maps CLI input to a DTO. However, the implementation assigns values to public properties after calling the constructor without arguments. This requires the DTO to either have uninitialized properties (unsound) or be mutable during hydration.

PHP 8.4 `readonly` classes cannot be assigned after construction. Any library that wants to produce genuinely immutable DTOs must use constructor injection.

## Decision

`InputMapper::map()` resolves the DTO constructor signature via `ReflectionClass::getConstructor()`, reads `#[Argument]` / `#[Option]` attributes on each parameter, and calls `$reflection->newInstanceArgs($args)` — a single constructor call.

The result is a `final readonly class` sealed by the PHP 8.4 runtime. No mutation is possible, and static analysers (PHPStan/Psalm) can verify it as exhaustively typed.

## Consequences

- DTOs are genuinely immutable — no field can be changed after `map()` returns
- `InputMapper::registerInto()` auto-wires `InputDefinition` from DTO attributes, so `configure()` can be empty for complex input shapes
- The `autoRegister: true` flag on `#[MapInput]` is a signal to Application (future: auto-call `registerInto` before `parse()`)
- Incompatible with PHP < 8.2 (readonly classes)

## Rejected alternatives

- Property injection (Symfony approach): requires mutable class, cannot be `final readonly`
- Separate hydrator class: unnecessary abstraction — reflection is already used for attribute reading

---

## Amendment — 4.3.0 (2026-09-28)

- **`#[MapInput(autoRegister)]` removed.** Nothing read it, yet the docs told
  users to rely on "Application auto-wiring", which never existed. Input is
  declared explicitly with `InputMapper::registerInto()` from `configure()`.
- **Strict coercion.** `int` via `FILTER_VALIDATE_INT`, `float` via
  `FILTER_VALIDATE_FLOAT`, `bool` from `1/0, true/false, yes/no, y/n, on/off`.
  Previously `--count=abc` silently became the default and `1.5` became `1`.
  Malformed values now raise 9003/9004 (exit 2).
- `registerInto()` forwards `#[Option(isArray: true)]` (it was dropped, so a
  repeatable option kept only its last value) and falls back to the
  attribute's `default` when the parameter has none.
- `ArrayInput`, like `ArgvInput`, exposes values only after `parse()`.
- Correction: Symfony's `#[MapInput]` arrived in Symfony 7.4, not 7.2, and
  populates public non-static properties. The earlier claim that readonly DTOs
  are "impossible" there was not verified and is withdrawn.
