# Security — KaririCode\Console

Spec v4.3.3 §22 (ASVS alignment) and §24 (Security Conformance Gate).
Declared in `composer.json`: `extra.kariricode.asvs = { version: "5.0", level: 1 }`.

## Threat model

Console runs inside the operator's own process. It reads `argv`, standard
input (prompts), environment variables (`NO_COLOR`, `TERM`), configuration
closures and command classes from configured discovery paths. It writes to a
terminal (stdout/stderr), to shell scripts the operator will `source`, to CI
logs and to telemetry closures.

| # | Threat | Control | Evidence (tests) |
|---|---|---|---|
| T1 | Command injection through generated completion scripts (CWE-78) | Identifiers allow-listed (program, command and option names); free text quoted per shell and collapsed to one line | `CompletionScriptsTest` (canary sourced in bash, zsh, fish), `BuiltinCommandsTest`, `CompletionBehaviourTest` |
| T2 | Terminal escape injection via echoed input (CWE-150) | `AnsiFormatter::escape()` on every untrusted string: `<` escaped, C0/C1 controls in caret notation, invalid UTF-8 scrubbed | `AnsiFormatterTest`, `ApplicationGrammarTest`, `DiagnosticOutputTest` |
| T3 | CI workflow-command injection through diagnostics | GitHub mode percent-encodes data and properties (`@actions/toolkit` rules) | `DiagnosticOutputTest` |
| T4 | Secret disclosure or alteration at a password prompt | Echo disabled with `stty` only on a TTY, always restored (`finally`); **fails closed** when a TTY cannot hide input; the secret is used exactly as typed (ASVS V6.2.8) | `PasswordPromptTest` (real pseudo-terminal), `PrompterTest` |
| T5 | Loading unintended code through discovery | Only autoloadable classes implementing `Command` under configured paths; files are never `include`d | `AttributeCommandLoaderTest`, `LoaderInstantiationTest` |
| T6 | Personal data in telemetry (A09) | `process.command_args` never collected; logs carry command name, trace id, exit code, duration and error message | `TelemetryMiddlewareTest`, `LoggingMiddlewareTest` |
| T7 | A process that cannot be stopped (availability) | Asynchronous signal delivery; declined signals get their previous disposition | `SignalMiddlewareTest` (real `posix_kill`) |
| T8 | Supply chain (A03) | Runtime dependencies limited to two PSR interface packages; CI actions pinned by commit SHA; checklist below | `SpecConformanceTest`, `composer security` |

**Residual risk.** Exception messages are printed to stderr and passed to
the logging closure unchanged; if a command throws with secrets in the
message (e.g. a DSN), the host must redact them (§23.7 redaction processor).

## ASVS 5.0 — Level 1 scope

ASVS verifies applications; for this library the applicable area is output
encoding and injection prevention (ASVS chapter 1, *Encoding and
Sanitization*, including the 1.2.x injection-prevention requirements, which
Spec v4.3.3 maps explicitly), plus input validation at the CLI boundary and
security-relevant logging. Authentication, sessions, access control,
cryptography, web front-end and API chapters do not apply: Console has no such
surface.

The requirement-by-requirement mapping is in
[ASVS-5.0-L1-assessment.md](ASVS-5.0-L1-assessment.md): all 70 Level 1
requirements of ASVS 5.0.0, 14 applicable (met, with evidence) and 56 not
applicable, each ID checked against OWASP's official CSV.

## Fail-closed suite

`composer security` runs `composer audit`, `tools/secret-scan.php` and the
PHPUnit group `security` (the tests marked in the table above).

## Supply-chain checklist (§24)

| Item | Status |
|---|---|
| `composer.lock` committed | Yes |
| SBOM | `composer sbom` writes `sbom.cdx.json` (CycloneDX 1.5): 2 runtime components (`psr/log`, `psr/event-dispatcher`) |
| Dependencies pinned | Locked in `composer.lock`; CI actions pinned by full commit SHA |
| Release signed | Release-time step: sign the tag (`git tag -s`) — not verifiable before a release |
| Secret scanning | `tools/secret-scan.php` — clean |

## Reporting

Report vulnerabilities privately to walmir.silva@kariricode.org.
