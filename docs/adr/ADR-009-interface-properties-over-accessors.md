# ADR-009 — Interface Properties over Accessors

**Status:** Accepted
**Date:** 2026-09-28
**Component:** KaririCode\Console 4.3
**ARFA:** 1.3 P1 (Immutable state)

## Context

Spec v4.3.3 §18 lists "getters/setters without hooks" as a zero-tolerance
antipattern ("ceremony without value" → property hooks or public readonly).
Up to 4.2 every contract used accessors: `Command::getName()`,
`CommandContext::getInput()`, `Input::getArguments()`,
`Output::getVerbosity()/setVerbosity()`, `Signalable::getSubscribedSignals()`,
`Application::getRegistry()`, and so on.

## Decision

Contracts declare PHP 8.4 interface properties:

- `{ get; }` for data a caller only reads — `Command::$name`,
  `CommandContext::$input`, `Input::$arguments`, `Input::$interactive`,
  `Output::$errorOutput`, `Signalable::$subscribedSignals`.
- `{ get; set; }` for settings a caller changes — `Output::$verbosity`,
  `Output::$decorated`, `OutputFormatter::$decorated`.

Implementations choose the mechanism that states their intent: readonly
promoted properties (Value Objects), virtual get hooks (commands:
`public string $name { get => 'greet'; }`), `public private(set)` (parsed
state), and delegating hooks (decorators and composites such as
`ConsoleOutput`). Keyed lookups (`getArgument($name)`) and behaviour stay
methods.

Two constraints were verified on the engine before committing:

- a readonly property satisfies an interface `{ get; }`;
- hooks cannot be readonly ("Hooked properties cannot be readonly"), so
  commands with hooked identity are `final class` — which matches the Spec's
  taxonomy anyway: readonly is for DPOs and Value Objects, not services.

`ConfiguratorBridge` keeps `getApplicationName()` and friends: they resolve
configuration lazily, and Spec v4.3.3 §11 forbids I/O and lazy initialisation inside
hooks.

## Alternatives Considered

- **Keep accessors** (familiar from Symfony). Rejected: violates §17.
- **Plain public mutable properties.** Rejected: §17 also prohibits mutable
  state without `private(set)`; settable data exists only where a contract
  declares `{ set; }`.

## Consequences

- Breaking change for every command and custom Output (4.3.0 CHANGELOG).
- `tests/Conformance` enforces the rule: no zero-argument `get*()` outside the
  documented exception, no trivial accessors, no uncontrolled public mutable
  property — each check proven to fail on a planted violation.

## References

- KaririCode Spec v4.3.3 §5 (type taxonomy), §11 (property hooks), §18 (antipatterns)
- PHP RFC: Property hooks (PHP 8.4); PHP RFC: Asymmetric visibility v2
