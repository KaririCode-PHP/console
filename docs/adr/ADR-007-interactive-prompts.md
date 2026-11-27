# ADR-007 — Interactive Prompt System

**Status:** Accepted
**Date:** 2026-03-06
**Component:** KaririCode\Console V4.1
**ARFA:** 1.3 P1 (Immutable state), P4 (Protocol agnosticism)

## Context

Commands that require user interaction (confirmations, selections, text input) need
a consistent prompt API. The ecosystem has two reference points:

- **Laravel Prompts** — beautiful TUI with cursor control, but tightly coupled to
  STDIN and requires ext-pcntl for non-blocking reads.
- **Symfony QuestionHelper** — flexible but tied to the `Command` base class and
  relies on internal state mutation.

Neither can be tested without mocking global I/O.

## Decision

`InteractiveHelper` is a standalone class that accepts `Input`, `Output`, and an
optional `$inputStream` resource (defaults to `STDIN`). All reads use `fgets($this->inputStream)`.

Prompt methods (`text()`, `password()`, `confirm()`, `select()`, `multiselect()`, `spin()`)
return typed values or `PromptResult` value objects.

When `Input::$interactive` returns false, every prompt returns its default value
immediately — zero STDIN blocking, zero terminal interaction.

`PromptResult` is a `final readonly class` carrying `value` (string) and `isDefault` (bool).
The distinction matters: a command may behave differently when a user actively confirms
versus silently accepting the default.

## Alternatives Considered

- **Function-based API (Laravel Prompts)**: Clean DX but global functions are not
  injectable and cannot carry state across a prompt sequence.
- **Trait on Command**: Couples prompts to the command class hierarchy.
- **Full TUI with cursor control**: Deferred to V5.0 — requires ext-pcntl and a
  terminal abstraction layer that is out of scope for the core Console component.

## Consequences

- Fully testable: inject `php://memory` stream with pre-written lines.
- Non-interactive mode works in CI, piped input, and cron environments.
- `PromptResult` enables commands to distinguish user input from defaults.
- `spin()` is honest about synchronous PHP limitations (no background thread).

## References

- Laravel. "Prompts." laravel.com/docs/12.x/prompts.
- Symfony. "QuestionHelper." symfony.com/doc/current/components/console/helpers/questionhelper.html.

---

## Amendment — 4.3.0 (2026-09-28)

- Renamed `InteractiveHelper` → `Prompter` (Spec v4.3.3 §18: no Helper/Manager/Util
  names). `Input::isInteractive()` became the property `Input::$interactive`.
- **Answers are validated.** `select()` with `99` silently picked the last
  choice and `multiselect()` read `a,b` as `0`. Invalid answers are now re-asked
  up to three times, then rejected (9003). `multiselect()` without defaults
  selects nothing.
- **Passwords fail closed.** `stty` is used only when the input stream is a
  TTY, and echo is restored in `finally`. When a TTY cannot hide input the
  prompt refuses rather than display the secret (Symfony `QuestionHelper`
  policy; Spec v4.3.3 §20, fail closed). Piped or in-memory input is read directly.
- Choices, defaults and suggestions are escaped before display.
