# ADR-011 — Output Encoding and Escaping

**Status:** Accepted
**Date:** 2026-09-28
**Component:** KaririCode\Console 4.3
**ARFA:** 1.3 P4 (Protocol agnosticism)

## Context

Spec v4.3.3 §20: output is encoded for its sink. The first execution of the
code on PHP 8.4 showed that no sink was:

- the formatter deleted every unknown `<…>` (`a < b > c` → `a  c`) and exception
  messages that echo user input were interpolated into markup;
- ESC and CR sequences from arguments reached the terminal (CWE-150);
- `completion --program 'x;touch /tmp/pwned;#'` produced a script that ran the
  command when sourced (CWE-78); fish output could also be broken by a newline
  (argument descriptions were `#` comments) or a trailing backslash;
- GitHub annotations could emit extra workflow commands via a newline;
- `list --format=json` went through the markup formatter, and errors were
  written to stdout.

## Decision

| Sink | Rule |
|---|---|
| Console markup | Only `<info> <comment> <question> <e> <b> <u>` are markup; other `<…>` is literal; `\<` escapes; styles nest |
| Untrusted text | `AnsiFormatter::escape()`: escapes `<`, scrubs invalid UTF-8, shows C0/C1 controls (except tab and newline) in caret notation |
| Errors | `Output::$errorOutput` — stderr under `ConsoleOutput` |
| Machine output (JSON, scripts) | Written escaped, so the formatter passes it through verbatim |
| Shell code | Identifiers are allow-listed (program, command and option names); free text is quoted per shell — POSIX `'\''` for bash/zsh, `\\` and `\'` inside fish single quotes, zsh spec metacharacters escaped — and collapsed to one line |
| GitHub workflow commands | Percent-encode per `@actions/toolkit` `core/command.ts` |
| Colour | Off when `NO_COLOR` is non-empty, `TERM=dumb`, or the stream is not a TTY (Windows: VT100 required) |

Identifiers are rejected, never escaped: an allow-list cannot be bypassed by
an encoding the escaper did not anticipate.

## Alternatives Considered

- **Strip unknown tags (4.2 behaviour).** Rejected: silent data loss.
- **Escape `--program` instead of allow-listing.** Rejected: it lands in
  function names and `complete` invocations, where no quoting is valid.

## Consequences

- Help output shows `<name>` literally; argument names that collide with
  style tags (`e`, `info`) cannot change the output style.
- Verified by execution: generated scripts are parsed and sourced by bash, zsh
  and fish with a canary payload; injection cases are unit tests; colour
  detection runs on a real pseudo-terminal.

## References

- KaririCode Spec v4.3.3 §20 (security philosophy), §23 (controls)
- CWE-78, CWE-150; CVE-2020-15228 (GitHub Actions workflow commands)
- no-color.org; fish shell manual, "Quotes"; zsh `_arguments` documentation
