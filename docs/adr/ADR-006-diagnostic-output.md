# ADR-006 — DiagnosticOutput: Structured Diagnostic Emission

**Status:** Accepted
**Date:** 2026-03-06
**Component:** KaririCode\Console V4.2
**ARFA:** 1.3 P4 (Protocol agnosticism)

## Context

CLI tools that perform analysis (linters, migration detectors, conformance checkers)
need to emit structured findings. Three common targets exist:

1. **Human terminals** — ANSI-coloured, compiler-style `file:line: SEVERITY message`.
2. **CI runners** — GitHub Actions annotations `::error file=F,line=L::message`.
3. **Machine consumers** — JSON array for downstream tooling.

Embedding format-switching logic into every command violates SRP and makes commands
untestable against a specific output format.

## Decision

`DiagnosticOutput` is a **decorator** wrapping any `Output` implementation. It adds a
`diagnostic(Severity, message, ?file, ?line, ?column)` method that routes to one of
three emission strategies based on a `format` constructor parameter:

- `human` — writes immediately via the inner Output with ANSI colour codes.
- `github` — writes immediately using `::error`/`::warning`/`::notice` syntax.
- `json` — buffers entries and flushes as a JSON array via `flushDiagnostics()`.

All standard `write()`/`writeln()` calls pass through to the inner Output unchanged,
so existing commands are unaffected by wrapping.

The `Severity` pure enum carries `label()`, `ansiColor()`, and `githubAnnotation()`
methods, encapsulating format knowledge within the enum itself.

## Alternatives Considered

- **Separate Formatter classes per target**: More extensible but over-engineered for
  three well-known targets. The match expression in `diagnostic()` is simpler.
- **PSR-3 LoggerInterface**: Wrong abstraction — diagnostics are findings (with file/line),
  not log records (with context arrays).

## Consequences

- Commands emit diagnostics without knowing the output target.
- `DiagnosticOutput` can wrap `ConsoleOutput`, `BufferedOutput`, or `NullOutput`.
- JSON mode enables machine-readable output for IDE integrations.
- The decorator pattern preserves the Open/Closed principle.

## References

- Gamma, E. et al. (1994). *Design Patterns*, Ch. 4 — Decorator.
- GitHub Actions. "Workflow Commands." docs.github.com.
- LSP Specification. "DiagnosticSeverity." microsoft.github.io.

---

## Amendment — 4.3.0 (2026-09-28)

- `DiagnosticFormat` (pure enum, `parse()` fails closed) replaces the string
  flag (Spec v4.3.3 §18); `SourceLocation` (Value Object) replaces the separate
  file/line/column parameters (Spec v4.3.3 §13: at most 4 parameters).
- **GitHub mode was injectable.** A message containing a newline emitted a
  second, attacker-controlled workflow command (the CVE-2020-15228 class).
  Data now percent-encodes `%`, CR and LF, and properties also `:` and `,`,
  following `@actions/toolkit` `core/command.ts`.
- Every emitted text is escaped for the console formatter (ADR-011); JSON uses
  `JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE`.
