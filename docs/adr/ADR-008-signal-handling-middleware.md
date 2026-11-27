# ADR-008 — Signal Handling via Middleware

**Status:** Accepted
**Date:** 2026-03-06
**Component:** KaririCode\Console V4.1
**ARFA:** 1.3 P4 (Protocol agnosticism)

## Context

Long-running commands (queue workers, file watchers, data importers) must handle POSIX
signals (SIGINT, SIGTERM, SIGHUP) for graceful shutdown. Two challenges:

1. **PCNTL dependency** — `pcntl_signal()` is not available on all platforms (Windows,
   some shared hosting, some Docker images without the extension).
2. **Signal-to-command coupling** — Symfony Console's `SignalableCommandInterface` couples
   signal handling to the command class, requiring every signal-aware command to implement
   `getSubscribedSignals(): array` with raw PCNTL integer constants.

## Decision

Three components collaborate:

1. **`Signal` pure enum** — Cases: `Interrupt`, `Terminate`, `Hangup`. The `pcntlConstant()`
   method resolves to `SIGINT`/`SIGTERM`/`SIGHUP` when PCNTL is loaded; returns 0 otherwise.
   `isSupported()` checks `extension_loaded('pcntl')` once.

2. **`Signalable` interface** — Opt-in contract: `getSubscribedSignals(): list<Signal>` and
   `handleSignal(Signal): bool`. Commands that don't implement it are untouched.

3. **`SignalMiddleware`** — Inspects the command via `instanceof Signalable`. When the
   command is Signalable and PCNTL is available, installs handlers before `$next()` and
   restores `SIG_DFL` in a `finally` block. For non-Signalable commands or when PCNTL is
   absent, passes through with zero overhead.

## Alternatives Considered

- **Application-level signal registration**: Global handlers cannot know which command is
  running and cannot be short-circuited per-command.
- **Backed Signal enum with int values**: Unjustified backing: Spec v4.3.3 §10 makes enums pure unless an external contract needs values (E-003).
  `pcntlConstant()` provides the same capability without backing.
- **Always install handlers**: Wasteful for commands that don't need them; breaks
  environments without PCNTL.

## Consequences

- Signal handling is transparent: commands opt in via `implements Signalable`.
- No PCNTL dependency at the contract level — testable on any platform.
- Middleware restores default handlers after command completes (cleanup guarantee).
- The `Signal` enum is the only place that touches PCNTL constants.

## References

- IEEE Std 1003.1-2017 (POSIX). "Signal Concepts." pubs.opengroup.org.
- Symfony. "SignalableCommandInterface." symfony.com.
- PHP Manual. "PCNTL Functions." php.net/manual/en/ref.pcntl.php.

---

## Amendment — 4.3.0 (2026-09-28)

- **The 4.1–4.2 middleware never delivered a signal.** It did not enable
  `pcntl_async_signals()`, so PHP only queued signals: handlers never ran and
  SIGTERM was swallowed for the whole execution (reproduced: process exit 0).
- The middleware now enables async delivery, saves each previous disposition
  (`pcntl_signal_get_handler()`), and restores dispositions and the async flag
  in `finally` — the pattern of Symfony's `SignalRegistry`.
- `handleSignal()` returning `false` gives the signal its previous treatment:
  a previous PHP handler is invoked directly (a signal re-raised inside a PHP
  handler is lost, because pcntl blocks signals while dispatching — measured),
  `SIG_IGN` ignores it, and `SIG_DFL` is restored and the signal re-raised so
  the process ends by it (status 128 + N; GNU C Library manual §24.4.2).
- `Signal::isSupported()` checks the functions (hosts often disable them), and
  `getmypid() === false` is guarded: `posix_kill(0, …)` would signal the whole
  process group.
