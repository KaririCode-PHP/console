# SPEC-001 — KaririCode Console Architecture

| Field | Value |
|---|---|
| Version | 4.5.0 |
| Status | Accepted |
| ARFA | 1.3 — **Level 1 (Structural)**: P1, P4, P5 |
| Standard | KaririCode Spec v4.3.3 · ARFA 1.3 |
| Runtime | PHP ^8.4, `ext-mbstring`; optional `ext-pcntl`, `ext-posix`, `ext-tokenizer` |
| Error codes | 9000–9099 (ADR-003) |

---

## §1 Scope

A dependency-free terminal command framework: command registration and
discovery, POSIX-style argument parsing, a middleware pipeline, styled and
structured output, interactive prompts, signal delivery, shell completion,
and telemetry hooks. It targets short-lived CLI processes (ARFA Level 1).

## §2 Architecture Overview

```
bin/app ──► Application::dispatch(Input tokens, Output)
              │  global flags, `--`, --help/--version (§3)
              ▼
            Application::run(name, Input, Output) ── never throws
              │
              ├─ CommandRegistry ◄── loaders (explicit, #[AsCommand], ClassDiscovery)
              ├─ Command::configure(ConcreteInputDefinition) + reserved-option check
              ├─ Input::parse(definition)            (ArgvInput / ArrayInput, §5)
              ▼
            MiddlewarePipeline (outermost first, §7)
              Observability → Logging → Events → Signal → Timing → Command::execute()
              ▼
            CommandResult ──► exit status (0 / 1 / 2)

Output: stdout for results; Output::$errorOutput (stderr) for diagnostics (§6)
```

## §3 Dispatch

### §3.1 Grammar — `Application::dispatch(Input, Output): int`

```
app [global-flags] [command] [command-options/arguments]
```

| Token | Effect |
|---|---|
| `-h`, `--help` | Runs `help` for the command (or the application) instead of executing it |
| `-V`, `--version` | Prints `name version`, exits 0 |
| `-q`, `--quiet` | `Verbosity::Quiet` |
| `-v`, `-vv`, `-vvv`, `--verbose` | `Verbose`, `VeryVerbose`, `Debug`, `Verbose` |
| `-n`, `--no-interaction` | Prompts return their defaults |
| `--` | Ends global-flag scanning (POSIX Guideline 10). Later tokens reach the command verbatim; the terminator is forwarded so the command parser treats them as operands too. A command name may follow it. |

The first non-flag token names the command; with none, `list` runs. Global
flags are recognised anywhere before `--`. `--debug` is **not** global (it
was briefly in 4.2.1–4.2.2 and hijacked commands' own `--debug` options).
`-h/--help` and `-V/--version` follow GNU Coding Standards §4.8.

### §3.2 Reserved option names

Commands may not declare the long options `help`, `quiet`, `verbose`,
`version`, `no-interaction` or the shortcuts `h`, `q`, `v`, `V`, `n`. A
collision is reported when the command is run (9004, exit 2) rather than
letting `dispatch()` silently swallow the command's option.

### §3.3 `Application::run(string, Input, Output): CommandResult`

`run()` resolves, configures, parses and executes through the pipeline and
never throws. Every failure becomes a `CommandResult` plus a message on
`$output->errorOutput`:

| Condition | Exit | Code |
|---|---|---|
| Unknown option, value given to a flag, missing value, surplus operand, missing required argument, invalid typed value | 2 (`Invalid`) | 9003 / 9004 / 9005 |
| Unknown command | 1 (`Failure`) | 9001 |
| Any other `Throwable` from a command or middleware | 1 (`Failure`) | 9002 (original kept as `getPrevious()`) |

Exit status 2 for usage errors follows the GNU/bash convention. Error text
is always escaped (§6.3); the origin (`Class at file:line`) is added at `-v`
and the stack trace at `-vvv`.

## §4 Command Contract

```php
interface Command
{
    public string $name { get; }
    public string $description { get; }
    public function configure(InputDefinition $definition): void;
    public function execute(Input $input, Output $output): ExitCode;
}
```

Identity is exposed through PHP 8.4 interface properties, not accessors
(ADR-009). Implementations are services (`final class`), typically with
virtual get hooks: `public string $name { get => 'greet'; }`.
`Application::add()` honours `#[AsCommand]` aliases and `hidden`, exactly
as loaders do. Registering an existing name replaces that command and drops
its old aliases; an alias may not shadow another command's name or alias. Command names and aliases must match
`^[A-Za-z0-9][A-Za-z0-9:._-]*$` because they are spliced into generated
shell code (§10).

## §5 Input

### §5.1 `ArgvInput` (IEEE Std 1003.1-2017 XBD §12.2 + GNU long options)

| Form | Meaning |
|---|---|
| `--opt=value`, `--opt value` | long option with a value |
| `--flag` | long flag (`--flag=x` is rejected) |
| `-o value`, `-ovalue` | short option with a value |
| `-abc` | grouped flags; the last may take the rest (`-abofile`) or the next token (Guideline 5) |
| `--` | end of options |
| `-` | an operand (conventionally stdin) |

Array options accumulate across long and short forms; an array argument
consumes all remaining operands. Unknown options, values given to flags,
missing values and surplus operands are rejected, never ignored.

### §5.2 `ArrayInput`

Enforces exactly the rules above (unknown names 9003/9004, required 9005,
defaults, array normalisation, strict flag booleans), so a command tested
with `ArrayInput` behaves as it will from a shell. Like `ArgvInput`, values
exist only after `parse()`. It has no tokens, so use `run()`, not
`dispatch()`.

### §5.3 Definition invariants

`ConcreteInputDefinition` rejects duplicate argument names, duplicate
option shortcuts, a required argument after an optional one, and anything
after an array argument. Descriptors validate themselves (Value Objects,
Spec §5.2): option shortcut is one letter or digit; a flag is never an
array and has no truthy default; a required argument has no default; array
defaults are arrays.

### §5.4 `InputMapper`

`registerInto(Dto::class, $definition)` from `configure()`, then
`map(Dto::class, $input)` in `execute()`. Values are passed to the
constructor in one call, so the DTO is a `final readonly` DPO (ADR-004).
Coercion is strict: `int` via `FILTER_VALIDATE_INT`, `float` via
`FILTER_VALIDATE_FLOAT`, `bool` from `1/0, true/false, yes/no, y/n,
on/off`; anything else raises 9003/9004 instead of falling back to a
default.

## §6 Output

### §6.1 Contract

`Output` exposes `$verbosity { get; set; }`, `$decorated { get; set; }`,
`$errorOutput { get; }` and `write()` / `writeln()` / `newLine()`.
`newLine(n)` writes nothing for `n <= 0` and obeys verbosity.

### §6.2 Implementations

| Class | Behaviour |
|---|---|
| `ConsoleOutput` | Composes two `StreamOutput`s: stdout, and stderr as `$errorOutput`; settings kept in sync |
| `StreamOutput` | One stream; fails with 9007 on a non-stream or a failed write |
| `BufferedOutput` | Same formatter and verbosity rules; `$buffer` holds what a user would see |
| `NullOutput` | Discards; reads as `Quiet`, undecorated |
| `DiagnosticOutput` | Decorator adding structured diagnostics (§6.5) |

`TableFormatter` measures cells as printed (markup rendered, ANSI ignored,
wide glyphs as two columns). `ProgressBar` redraws in place only on a
decorated output; elsewhere it writes one line per 10% step, or one line on
`finish()` when the total is unknown.

### §6.3 Markup and escaping (ADR-011)

Styles: `<info>`, `<comment>`, `<question>`, `<e>`, `<b>`, `<u>`. Only
these are markup; any other `<…>` is literal text. Styles nest (closing one
restores the enclosing styles) and unclosed styles are reset at the end of
the message. `\<` renders `<`.

`AnsiFormatter::escape()` must wrap every untrusted string embedded in
markup: it escapes `<`, scrubs invalid UTF-8, and shows C0/C1 control
characters (ESC, CR, …) in caret notation (CWE-150).

### §6.4 Colour detection

In order: `NO_COLOR` set and non-empty → off (no-color.org); `TERM=dumb`
→ off; Windows → only with VT100 processing; otherwise only when the stream
is a TTY.

### §6.5 Diagnostics (ADR-006)

`diagnostic(Severity, string, ?SourceLocation)` in `DiagnosticFormat::Human`
(`file:line:col: ERROR message`), `GitHub` (workflow commands, with `%`, CR,
LF, and in properties `:` and `,`, percent-encoded so a finding cannot
inject commands) or `Json` (buffered, flushed as one array).

## §7 Middleware Pipeline (ADR-005)

The first middleware added is the outermost. `ConsoleServiceProvider`
wires: `ObservabilityMiddleware` (span covers everything) →
`LoggingMiddleware` → `EventMiddleware` → `SignalMiddleware` →
`TimingMiddleware` (innermost, so every outer layer sees the duration of
`execute()` alone). Logging, events and observability are present only
when their bridge is configured (`Instrumentation`, §14). Middleware that
observe failures re-throw; `run()` converts them (§3.3).

## §8 Signals (ADR-008)

For a `Signalable` command, `SignalMiddleware` enables
`pcntl_async_signals()`, saves each previous disposition, installs
handlers, and restores dispositions and the async flag in `finally`.
`handleSignal()` returning `true` means handled; `false` gives the signal
the treatment it would have had without the command: a previous PHP handler
is called directly, `SIG_IGN` ignores it, `SIG_DFL` terminates the process
by that signal (status 128 + N). Without PCNTL the middleware is a no-op.

## §9 Prompts (ADR-007)

`Prompter` (renamed from `InteractiveHelper`): `text`, `password`,
`confirm`, `select`, `multiselect`, `spin`. Non-interactive input returns
defaults without reading. Choices are validated: malformed or out-of-range
answers are re-asked up to three times, then rejected (9003). A password on
a terminal whose echo cannot be disabled is refused (fail closed); echo is
restored in `finally`. On Windows consoles there is no `stty`, so `password()`
refuses on a terminal: pipe the secret or read it from the environment.
`PromptResult::asInt()`/`asBoolean()` reject malformed answers (9003) and
share `InputMapper`'s boolean vocabulary.

## §10 Built-in Commands

| Command | Behaviour |
|---|---|
| `help [command]` | Application: global options and commands. Command: description, usage synopsis, arguments, options with defaults, aliases |
| `list [--format=table\|json\|plain] [--filter=prefix] [--all]` | Unknown format → exit 2; JSON is emitted verbatim (escaped from markup) |
| `completion [bash\|zsh\|fish] [--program=name]` | Program name allow-listed; free text quoted per shell (POSIX `'\''` for bash/zsh, `\\`/`\'` for fish, zsh spec metacharacters escaped) and collapsed to one line. bash re-splits the line on whitespace and trims the colon prefix (names like `cache:warm`); zsh uses the `'*:: :->args'` subcommand idiom with the `_arguments` variables localised |

## §11 ARFA 1.3 Conformance — Level 1

| Principle | Status | Evidence |
|---|---|---|
| P1 Immutable state | ✅ | Value Objects and DPOs are `final readonly`; mutable services expose state via `private(set)` or contract-declared setters (conformance tests) |
| P4 Protocol agnosticism | ✅ | Contracts carry no STDIN/STDOUT; `ArrayInput`/`BufferedOutput` run commands with no terminal; parity test with `ArgvInput` |
| P5 Continuous observability | ✅ | Structured logs (`LoggingBridge`), lifecycle events (`EventDispatcherBridge`), duration histogram and one span per command (`ObservabilityBridge`, OTel CLI conventions, ADR-010) |
| P2 Reactive composition | — not claimed | Requires `Result` composition on a persistent runtime (Level 2) |
| P3 Adaptive context | — not claimed | Requires a MAPE-K loop (Level 2); lazy loading is not P3 |

Declared in `composer.json` → `extra.kariricode.arfa = {version: 1.3, level: 1}`.
No OWASP ASVS level is declared: no ASVS assessment has been performed.

## §12 Spec v4.3.3 Compliance

`tests/Conformance/SpecConformanceTest.php` turns these rules into checks
over `src/`, each reporting every offender by name. Each rule was proven to
fail on a planted violation (21 mutations, 21 caught).

| Rule | Spec |
|---|---|
| `declare(strict_types=1)` in every file; `@package` matches the namespace | §30, ecosystem |
| Pure enums (backing only for an external contract, E-003); typed class constants | §10, §30 |
| Value Objects and attributes are `final readonly` | §5 |
| Public mutable state only through `private(set)`, a hook, or a contract-declared `{ set; }` | §18 |
| No zero-argument `get*()` (exception: `ConfiguratorBridge`, lazy I/O cannot live in hooks, §11); no trivial `get/is/has/set` accessors | §18 |
| No `toArray`/`fromArray`/`jsonSerialize`, no `…Interface`, no `Manager/Helper/Util`, no `__get/__set/__call` | §18 |
| Inheritance ≤ 2 levels in the package; classes `final` unless extended in-package | §18 |
| `Contract\` holds only interfaces; summaries describe roles, not "Represents a" | §17 |
| Error codes unique and inside 9000–9099 | ADR-003 |
| Runtime requires only `php`, `ext-*`, PSR contracts and `kariricode/contract`; ARFA and ASVS levels and spec version declared | §28, §31, §4 |
| Usage example per class (C4); "Side effects:" on side-effecting types (C3) | §17 |
| Named arguments at call sites with 3+ arguments or ambiguous booleans (`tools/named-arguments.php`) | §30 |
| Section markers exactly 72 columns | ecosystem |

Gates: G1 automated checks (PHPUnit, PHPStan max, conformance) pass;
G4 conformance level declared; G5 zero known antipatterns (enforced above).

## §13 Error Codes (9000–9099)

| Code | Factory | Exception |
|---|---|---|
| 9001 | `commandNotFound()` | `CommandNotFoundException` |
| 9002 | `executionFailed()` | `CommandExecutionException` |
| 9003 | `invalidArgument()` | `InvalidArgumentException` |
| 9004 | `invalidOption()` | `InvalidOptionException` |
| 9005 | `missingRequiredArgument()` | `InvalidArgumentException` |
| 9006 | `loaderFailure()` | `ConsoleException` |
| 9007 | `outputWriteFailure()` | `ConsoleException` |

## §14 Integration Bridges

All bridges take `\Closure`s (pass `$service->method(...)`), so Console has
no hard dependency on sibling packages while non-callables fail at
construction.

| Bridge | Purpose |
|---|---|
| `ConfiguratorBridge` | Lazy `console` config: `name`, `version`, `scan_paths` (wired to `AttributeCommandLoader`), `debug` |
| `ClassDiscoveryBridge` | `CommandLoader` over an external scanner; keeps only `Command` implementations |
| `LoggingBridge` | PSR-3-shaped `(level, message, context)` |
| `EventDispatcherBridge` | `(event, payload)`; wired by `EventMiddleware` |
| `ObservabilityBridge` | histogram `(name, seconds, attributes)` and span export `(CommandSpan)` |
| `Instrumentation` | Parameter object grouping the three telemetry bridges |

## §15 Verification (4.5.0) — Spec v4.3.3 §29 Quality Gate

Measured with PHP 8.4.23 (static) and PHP 8.4.26 + PCOV; tools and choices in ADR-012.

| §29.1 check | Command | Result |
|---|---|---|
| Static analysis | `composer phpstan` | PHPStan level max over `src` + `tests`: 0 errors; cognitive complexity ≤ 15 |
| Code style | `composer cs-check` | PER Coding Style: clean |
| Complexity | `composer complexity` | cyclomatic ≤ 10, nesting ≤ 3, parameters ≤ 4: within budgets |
| Tests | `composer test` | 351 tests, 781 assertions, 0 failures (1 skip: the "PCNTL absent" path on a host that has PCNTL); stable across random orders and both runtimes |
| Mutation | `composer infection` | 1,657 mutants; covered MSI 82% (threshold 80%) |
| Conformance | `composer test-conformance` | 26 checks, including §17 C3/C4 and §30 named arguments; 28/28 planted violations caught |
| Type coverage | `composer psalm` | Psalm error level 1: 0 errors; 99.5% of the code type-inferred |
| Security | `composer security` | `composer audit` clean, secret scan clean, fail-closed group green |

§24: threat model in `docs/security/SECURITY.md`; ASVS 5.0.0 Level 1 assessment
(70 requirements: 14 applicable and met, 56 not applicable; IDs checked against
OWASP's CSV) in `docs/security/ASVS-5.0-L1-assessment.md`;
`composer.lock` committed; `composer sbom` writes a CycloneDX SBOM (0 runtime
components). Release signing is a release-time step.

Behaviour verified by execution: real `posix_kill` signals (a child ends with
status 143 on the `SIG_DFL` path); colour detection and the password prompt on
a real pseudo-terminal; completion in real bash, zsh and fish; process-level
stdout/stderr separation.

Not verified: Windows — the CI workflow has a Windows job, but it has not run.

## §16 File Inventory (69 files)

| Directory | Files |
|---|---|
| Attribute | Argument, AsCommand, MapInput, Option |
| Command | CompletionCommand, HelpCommand, ListCommand |
| Contract | Command, CommandContext, CommandLoader, CommandMiddleware, Input, InputDefinition, Output, OutputFormatter, Signalable |
| Core | Application, CommandRegistry, ConcreteCommandContext, ConcreteInputDefinition, MiddlewarePipeline |
| Enum | DiagnosticFormat, ExitCode, OutputFormat, Severity, Shell, Signal, Verbosity |
| Exception | CommandExecutionException, CommandNotFoundException, ConsoleException, InvalidArgumentException, InvalidOptionException |
| Formatter | AnsiFormatter, ProgressBar, TableFormatter |
| Input | ArgvInput, ArrayInput, InputMapper |
| Integration | ClassDiscoveryBridge, ConfiguratorBridge, ConsoleServiceProvider, EventDispatcherBridge, Instrumentation, LoggingBridge, ObservabilityBridge |
| Loader | AttributeCommandLoader, ExplicitCommandLoader |
| Middleware | EventMiddleware, LoggingMiddleware, ObservabilityMiddleware, SignalMiddleware, TimingMiddleware |
| Output | BufferedOutput, ConsoleOutput, DiagnosticOutput, NullOutput, StreamOutput |
| Prompt | PromptResult, Prompter |
| ValueObject | ArgumentDescriptor, CommandDescriptor, CommandResult, CommandSpan, OptionDescriptor, SourceLocation |

## §17 References

- KaririCode Specification V4.0 / ARFA 1.3 (§4 conformance levels, §5 type taxonomy, §12 required features, §17 prohibited antipatterns, §18 security)
- IEEE Std 1003.1-2017, XBD §12.2 Utility Syntax Guidelines
- GNU Coding Standards §4.8 (`--help`, `--version`)
- GNU C Library manual §24.4.2, Handlers That Terminate the Process
- OpenTelemetry Semantic Conventions — CLI programs (status: Development)
- no-color.org; fish shell manual, "Quotes"; GitHub `@actions/toolkit` `core/command.ts`
- CWE-78 (OS command injection), CWE-150 (escape/control sequence neutralisation)
- Symfony Console 7.4 (`SignalRegistry`, `OutputFormatter`, `QuestionHelper`) — design reference

## §17 Telemetry correlation (4.5.0)

Logs go to a PSR-3 logger and lifecycle events (`CommandStarted`,
`CommandFinished`, `CommandFailed`) to a PSR-14 dispatcher. Each `run()`
creates a `TraceId` (W3C trace-id, `random_bytes()`), exposed as
`CommandContext::$traceId` and carried by every log record (`trace_id`), event
and span (E-021, ADR-013). Metrics and spans use `ObservabilityBridge` until
the ecosystem settles a metrics port.
