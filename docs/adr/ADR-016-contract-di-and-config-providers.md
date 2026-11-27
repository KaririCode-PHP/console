# ADR-016 — Console as a kariricode/contract Service and Config Provider

**Status:** Accepted; closes the item ADR-014 left open
**Date:** 2026-11-27
**Component:** KaririCode\Console 4.6
**ARFA:** 1.3 P4 (Protocol Agnosticism: a host wires Console through contract types only)

## Context

Spec V4.3.3 §31 gives every component a `Provider/` directory whose service
provider registers it in the application's container, and kariricode/contract
2.11 defines that provider: `DI\ServiceProvider`, with `register()` that only
binds and `boot()` that runs after every provider registered. The same
release defines `Configuration\ConfigProvider`, through which a component
declares its configuration key, defaults and types to a configuration
builder. Cache 4.1.0 already ships both shapes.

Console had a `Provider\ConsoleServiceProvider` that builds an
`Application`, but it implemented neither contract, so a host had to know
Console's own factory. ADR-014 deferred the decision on what Console binds
and configures. The defaults were also written three times
(`Application`, `ConfiguratorBridge`, `ConsoleServiceProvider`).

## Decision

- `ConsoleServiceProvider` implements `DI\ServiceProvider`. `register()`
  binds `Core\Application` as a singleton whose factory is the existing
  `build()`; it binds nothing else, because the registry, the loaders and
  the middleware belong to the `Application` the factory returns.
  `boot()` does nothing: Console needs no other service at boot time, and
  commands are discovered when the application is first resolved.
- `ConsoleConfigProvider` implements `Configuration\ConfigProvider` under
  the key `console`, with the four keys `ConfiguratorBridge` already reads:
  `name` (string), `version` (string), `scan_paths` (array) and `debug`
  (bool).
- The defaults live once, as `Application::DEFAULT_NAME` and
  `Application::DEFAULT_VERSION`; the config provider, the bridge and the
  service provider read them from there.
- The builder API (`build()`, `buildApplication()`) stays as it was, so
  scripts and tests that do not use a container are unaffected.

## Consequences

- A host registers Console the same way as any other component:
  `$container->register(new ConsoleServiceProvider(configurator: ...))` and
  `$builder->withProvider(new ConsoleConfigProvider())`.
- `register()` neither reads configuration nor scans for commands; the
  configuration reader runs once, when the container first builds
  `Application` (`ConsoleProvidersTest`).
- `Provider\` depends on `Core\`, never the other way round.
- A command registered through the container itself (a tagged service) is
  not supported: the contract's `BindableContainer` has no tags. Commands
  keep coming from loaders and `registerCommand()`.

## References

- kariricode/contract SPEC-001 §2.1 and §2.3 (ConfigProvider, ServiceProvider)
- KaririCode Spec V4.3.3 §31
- ADR-014 (the deferred item), cache ADR-013 (the same provider shape)
