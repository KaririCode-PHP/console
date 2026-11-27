# ADR-001 — Command Discovery Strategy

**Status:** Accepted  
**Date:** 2026-03-06  
**Component:** KaririCode\Console V4.0  

## Context

V3.1 used a custom `CommandDiscovery` class that duplicated ClassDiscovery internals.
The ecosystem already has `KaririCode\ClassDiscovery` with a mature attribute scanner.

## Decision

Discovery is split into two layers:

1. **`AttributeCommandLoader`** — zero-dependency fallback using `token_get_all()` and
   `ReflectionClass`. Works in any environment.
2. **`ClassDiscoveryBridge`** — delegates to KaririCode\ClassDiscovery when present,
   accepting a callable scanner to avoid a hard package dependency (ARFA 1.3 P4).

The `CommandRegistry` accepts a `list<CommandLoader>` via `addLoader()` and evaluates
loaders lazily on first `has()` / `get()` call (no I/O until the first lookup).

## Consequences

- Console works standalone with zero KaririCode deps.
- In full-ecosystem deployments, ClassDiscoveryBridge provides optimised scanning.
- Tests use `ExplicitCommandLoader` for determinism without filesystem access.

---

## Amendment — 4.3.0 (2026-09-28)

Found by executing the suite on PHP 8.4 for the first time:

- **Canonical names only.** Loaders returned alias keys *and* the registry
  expanded `#[AsCommand]` aliases, so an aliased command was listed twice.
  Loaders now return `name => class`; only the registry expands aliases.
- **`::class` before the declaration.** `AttributeCommandLoader` took the first
  `T_CLASS` token, so `#[AsCommand(description: Foo::class)]` hid the file. A
  `T_CLASS` preceded by `::` or `new` is now skipped, as Composer's class-map
  generator does.
- `ClassDiscoveryBridge` keeps only `Command` implementations; its scanner is a
  `\Closure` (a non-callable fails at construction).
- `Application::add()` goes through `CommandRegistry::registerCommand()`, which
  honours `#[AsCommand]` aliases and `hidden` exactly like the loaders.
- `ConfiguratorBridge` `scan_paths` now feeds an `AttributeCommandLoader`; it was
  read by nothing.
- Command names and aliases must match `^[A-Za-z0-9][A-Za-z0-9:._-]*$`: they are
  spliced into completion scripts (ADR-011).
- Classification corrected: lazy loading is a performance property, not ARFA P3
  (P3 is a MAPE-K adaptive loop and requires Level 2).

## Amendment — 4.3.2 (2026-09-28)

- `CommandRegistry::register()`: registering an existing name replaces that
  command and drops its old aliases (an overridden `list` was still reachable
  as `commands`); an alias that belongs to another command is rejected with
  `\InvalidArgumentException` instead of silently shadowing it.
