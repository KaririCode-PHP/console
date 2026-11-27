# Changelog

All notable changes to KaririCode\Console are documented here.

## [4.6.0] - 2026-11-27

First import into KaririCode-PHP/console. It completes the contract items
4.5.0 deferred and moves the toolchain to KaririCode Devkit.

### Added
- `kariricode/contract` ^2.12 as a runtime dependency.
- `CommandLoaderException` (9006, `Configuration`) and `OutputWriteException`
  (9007, `Runtime`).
- ADR-014 (contract exception families), ADR-015 (toolchain through kcode),
  ADR-003 amendment (registry of record in kariricode/exception).

### Changed
- `ConsoleException` is abstract and implements
  `KaririCode\Contract\Exception\ExceptionInterface`; each factory returns a
  final class with exactly one contract family: 9001 `NotFound`, 9002 and
  9007 `Runtime`, 9003 to 9005 `Validation`, 9006 `Configuration`. Codes and
  messages are unchanged.
- The toolchain comes from KaririCode Devkit, pinned in `devkit.php`, and
  runs the root configs (ADR-015): `require-dev` is gone, `composer.lock`
  holds the runtime only, and `composer security` also audits the
  toolchain. CI on Linux installs kcode 1.2.1 by version and SHA-256; the
  Windows job runs the suite with the same PHPUnit.

### Still open
- `DI\ServiceProvider` and `Configuration\ConfigProvider` from the contract:
  they need a decision on what Console binds and configures (ADR-014).

### Measured
- 360 tests, 790 assertions; PHPStan max, Psalm level 1, PER-CS and §13
  complexity: 0 findings; 1,338 mutants killed, covered MSI 81%.

## [4.5.0] - 2026-10-02

Cross-chat alignment with Spec v4.3.3 and with what the rest of the ecosystem
expects of Console. Items that need `kariricode/contract` 2.11.0 (exception
families, `DI\ServiceProvider`, `Configuration\ConfigProvider`) wait for that
release: Packagist has only v2.8.6.

### Breaking (unpublished line)
- `LoggingBridge` and `EventDispatcherBridge` removed: `Instrumentation` takes a
  PSR-3 `LoggerInterface` and a PSR-14 `EventDispatcherInterface` (§31,
  principle 16; ADR-013). Events are objects: `CommandStarted`,
  `CommandFinished`, `CommandFailed`.
- `CommandContext` gains `$traceId`; `CommandSpan` gains `traceId` and `spanId`.

### Added
- `TraceId` (W3C trace-id from `random_bytes()`): every log record, event and
  span of a run shares it (E-021).
- `tools/named-arguments.php` and the §30 conformance rule; 171 call sites
  converted, each name validated by PHPStan.
- §17 documentation: usage examples (C4) on every class, "Side effects:" notes
  (C3) on the 19 side-effecting types, both enforced by conformance rules.
- ADR-013; ADR-003 records the Parser range collision (`PS-9000-9999`).

### Changed
- Spec declared as `4.3.3`; citations updated.
- CI actions pinned by full commit SHA (§32): `actions/checkout` v4.4.0,
  `shivammathur/setup-php` 2.37.2.
- Runtime dependencies: `psr/log` ^3.0 and `psr/event-dispatcher` ^1.0; the
  conformance rule now allows PHP, extensions, PSR contracts and
  `kariricode/contract`.

### Measured
- 351 tests, 781 assertions; PHPStan max, Psalm level 1, PER-CS and §13
  complexity: 0 findings; 1,657 mutants, covered MSI 82%; conformance 26 checks,
  28/28 planted violations caught.

## [4.4.0] - 2026-09-30

Aligns Console with **KaririCode Spec v4.3.2** — the current revision, whose
section numbers differ from V4.0 — and closes the items left open by the
cross-chat review of 4.3.2.

### Decided with evidence
- **ARFA key.** `extra.kariricode.arfa` is the form the current spec defines
  (§4). ORM, Authorization, Template, I18n, CLI and Tenancy still use the older
  `extra.arfa` (V4.0 §14) and would migrate per §33. Added `asvs` (L1, see
  `docs/security/SECURITY.md`) and `"spec": "4.3.2"`.
- **Version.** `kariricode/console` is not published on Packagist (checked
  2026-09-30), so no one consumes 4.x: no major bump is owed, and the provider
  move below lands before the first release.

### Breaking (unpublished line)
- `ConsoleServiceProvider` moved to `KaririCode\Console\Provider` (§31 structure).

### Added — §29 quality gate
- Scripts named as §29.1 defines them: `phpstan`, `cs-check`, `complexity`,
  `test`, `test-conformance`, `infection`, `psalm`, `security` (+ `sbom`, `check`).
- PER Coding Style (PHP-CS-Fixer); Psalm at error level 1; complexity budgets
  (`tools/complexity.php` + cognitive-complexity rule); Infection (covered MSI 82%);
  secret scan; CycloneDX SBOM; `security` test group; `composer.lock` committed.
- `docs/security/SECURITY.md` (threat model, ASVS L1 scope), ADR-012,
  `.github/workflows/ci.yml` (Linux + Windows; not yet run).
- Conformance: no static properties; ADR citations and relative links resolve;
  ASVS level and spec version declared.

### Security
- **Passwords were trimmed**, so a secret with surrounding spaces silently became
  a different secret. Only the line terminator is removed now (ASVS V6.2.8).
- ASVS 5.0.0 Level 1 assessment (`docs/security/ASVS-5.0-L1-assessment.md`):
  all 70 Level 1 requirements — 14 applicable and met, 56 not applicable — with
  every ID checked against OWASP's official CSV; `SECURITY.md` policy at the root.

### Fixed
- Complexity budgets: `Application::dispatch()` (cyclomatic 18, cognitive 19)
  now delegates to the new `GlobalOptions` Value Object; `ArrayInput::parse()`,
  `InputMapper::resolveParameter()`, `OptionDescriptor::__construct()` and
  `AttributeCommandLoader::extractClassName()` split into named steps.
- Untyped comparator closures in `CommandRegistry::all()`; `MiddlewarePipeline`
  now carries `\Closure(CommandContext): CommandResult`.
- Documentation claims that contradicted the spec: "§E3 — no backed enums"
  (E-003 permits backing for external contracts), ARFA P2/P3 on the pipeline,
  registry, loaders and attributes, and citations of nonexistent sections.
  All citations now use Spec v4.3.2 numbering.

### Tests
- Golden-output tests (`tests/Fixture/golden`) and `GlobalOptionsTest` (gaps found by
  mutation testing): covered MSI 74% → 82%.

### Measured
- 346 tests, 758 assertions; PHPStan max, Psalm level 1, PER-CS: 0 findings;
  1,662 mutants, covered MSI 82%.

## [4.3.2] - 2026-09-28

Completion behaviour verified in the real shells (4.3.x only checked that the
scripts parse and cannot execute embedded code), plus two fail-open spots.

### Fixed
- **zsh: subcommand options never completed.** The top-level
  `_arguments '*: :->args'` consumed words starting with `-`, so
  `app deploy --<Tab>` offered nothing. Now the canonical subcommand idiom
  `'*:: :->args'` + `$line[1]`, and the function localises the variables
  `_arguments -C` assigns (`curcontext`, `state`, `state_descr`, `line`,
  `opt_args`), as the zsh completion system expects of callers.
- **bash: names containing `:` did not complete.** `COMP_WORDS` is split on
  `COMP_WORDBREAKS`, which contains `:`, so `app cache:w<Tab>` saw three words.
  The script now splits the line on whitespace and trims the colon prefix
  readline will not replace (the approach of bash-completion's
  `__ltrim_colon_completions`).
- **`PromptResult::asInt()` / `asBoolean()` failed open:** `"1.9"` became 1,
  `"abc"` the default, and typos like `"treu"` became `false`. Both now reject
  malformed answers (9003); an empty answer still yields the default/`false`.
  `asBoolean()` now accepts exactly `InputMapper`'s vocabulary (it lacked
  `on`/`off`, although the 4.2.2 changelog said they matched).
- **Registry:** replacing a command (same name) left its old aliases pointing
  at the replaced one, and an alias could silently shadow another command.
  Replacing now drops the old aliases; a shadowing alias is rejected.

### Tests
- `CompletionBehaviourTest`: the same scenarios in real bash (interactive shell
  in a pseudo-terminal, readline word splitting), real zsh (`zpty` + `compinit`,
  candidates captured from `compadd`) and fish (`complete -C`). Reverting the
  generator fixes makes exactly the three regression scenarios fail.
- Boolean vocabulary parity between `PromptResult` and `InputMapper`; registry
  replacement and shadowing; strict `asInt()`.

### Measured
- 309 tests, 656 assertions; coverage 95.0% of lines, 80.4% of methods.

## [4.3.1] - 2026-09-28

Follow-up review driven by tools not used in 4.3.0: code coverage (PHP 8.4.26
+ PCOV) and Psalm 6.19. Coverage showed which paths no test executed; each
fix below comes from one of those paths or from a Psalm finding.

### Fixed
- **Commands discovered by loaders were never run by any test.**
  `Application::instantiate()` (the path for every loader-registered command)
  reported a missing class as `Command "App\X" is not registered`, naming a
  class as a command. Both broken cases now fail against the command name
  (9002, exit 1): "class … does not exist" / "… does not implement Command".
- **Tables misaligned when cells contain markup or ANSI:** widths were measured
  on the raw string (`<b>x</b>`), not on what is printed. `list` passes command
  descriptions straight into the table.
- **ProgressBar on non-terminals:** every `advance()` wrote a `\r` redraw, packing
  hundreds of updates into one line of a CI log. Undecorated outputs now get one
  line per 10% step (or one line on `finish()` without a total). Also:
  `finish()` is idempotent, invalid `total`/`barWidth` are rejected, and the
  spinner index no longer grows without bound.
- `phpunit.xml.dist` set `beStrictAboutCoverageMetadata`, which marked 89 tests
  risky — and failed the run — whenever a coverage driver was installed.
- `InputMapper` reported booleans in errors as `""`/`"1"`; now `false`/`true`.
- `AnsiFormatter::escape()` and `SignalMiddleware` hardened where analysers
  disagree about `mb_ord()` / `pcntl_signal_get_handler()` return types.

### Added
- Psalm 6.19 at error level 2 (`psalm.xml`, `composer psalm`, part of
  `composer check`). Suppressed only: PHP 8.4 interface/virtual hooked
  properties (not yet modelled by Psalm) and the intentional `stty` calls.
- `#[\Override]` on the 49 methods implementing a contract (PHP 8.3); the
  engine verifies each at compile time.
- Tests: loader-instantiated commands; password prompt through a real
  pseudo-terminal (the secret is not echoed; `stty` settings restored — the test
  was shown to fail with echo left on); ProgressBar modes; dedicated tests for
  `Verbosity`, `CommandResult`, `TimingMiddleware`; application-level `help`;
  `list` table alignment; `Prompter::spin()`; float coercion.

### Measured
- 280 tests, 594 assertions; line coverage 85.3% → 94.9%, methods 66% → 79.9%.
  Remaining uncovered lines are trivial predicates, the Windows branch, and
  paths exercised only in child processes, which in-process coverage cannot see.

## [4.3.0] - 2026-09-28

Deep-review round 3 — the first round executed on a real runtime (PHP 8.4.23,
PHPUnit 11.5, PHPStan 2.2, bash/zsh/fish). Several defects below were
introduced by earlier review rounds and are marked as such.

> ⚠ **Versioning.** This release changes public contracts (see *Breaking*).
> If 4.2.x was published to Packagist, publish this as **5.0.0** (SemVer §8).

### Breaking
- Contracts expose PHP 8.4 interface properties instead of accessors (ADR-009):
  `Command::$name`, `$description`; `CommandContext::$commandName`, `$input`,
  `$output`, `$command`; `Input::$arguments`, `$options`, `$tokens`,
  `$interactive`; `InputDefinition::$arguments`, `$options`;
  `Output::$verbosity`, `$decorated`, `$errorOutput`;
  `OutputFormatter::$decorated`; `Signalable::$subscribedSignals`;
  `Application::$name`, `$version`, `$registry`; `ProgressBar::$current`,
  `$total`; `BufferedOutput::$buffer`.
- `InteractiveHelper` renamed to `Prompter` (Spec §17: no Helper names).
- `DiagnosticOutput(Output, DiagnosticFormat)` and
  `diagnostic(Severity, string, ?SourceLocation)` replace the string format
  flag and separate file/line/column parameters.
- `ConsoleServiceProvider(?ConfiguratorBridge, ?CommandLoader, Instrumentation)`;
  bridges take `\Closure` (pass `$service->method(...)`).
- `ConsoleOutput` composes stdout/stderr `StreamOutput`s; `StreamOutput` is final.
- `OutputFormat::fromString()` → `OutputFormat::parse(): ?self` (fails closed).
- `ExitCode::fromInt()` maps unknown non-zero statuses to `Failure`, not `Invalid`.
- Usage errors (9003–9005) exit **2**; `--debug` is no longer a global flag.
- `#[MapInput(autoRegister)]` and `ConfiguratorBridge::getLoaderCacheTtl()` removed
  (neither was used by anything).
- `ArrayInput` exposes values only after `parse()`, like `ArgvInput`.
- Default application version is `UNKNOWN` (it leaked the library version).

### Security
- **Command injection in `completion` (CWE-78):** `--program` was spliced raw into
  the generated script; `x;cmd;#` ran `cmd` when the script was sourced. Now
  allow-listed. fish output also escaped backslashes and collapses newlines (a
  newline broke out of a `#` comment into code). Command names are allow-listed.
- **GitHub workflow-command injection:** `DiagnosticOutput` did not percent-encode
  messages, so a newline could emit a second command. Encoded per `@actions/toolkit`.
- **Terminal escape injection (CWE-150):** user input echoed in errors reached the
  terminal with ESC/CR intact and could forge markup. `AnsiFormatter::escape()`.
- Passwords fail closed when a terminal cannot hide input; echo restored in `finally`.

### Fixed
- **Signals never worked:** no `pcntl_async_signals()`, so handlers never ran and
  SIGTERM was swallowed. Previous handlers are now saved and restored; a declined
  signal gets its previous treatment (ADR-008).
- **CJK tables misaligned:** `mb_str_pad()` counts characters, not columns — the
  round-1 fix was wrong. Cells are padded by `mb_strwidth()`.
- **Global flags after `--` were consumed** and `--debug` hijacked commands' own
  option (both introduced in 4.2.1). `--` now ends global-flag scanning; reserved
  names/shortcuts are validated.
- Formatter deleted unknown `<…>` and did not nest styles.
- Errors went to stdout; they now go to `Output::$errorOutput` (stderr).
- `help <command>` showed no arguments or options.
- `list --format=json` went through the formatter; `json_encode()` failure → `TypeError`;
  unknown formats silently fell back to a table.
- Loader skipped files with `Foo::class` before the declaration and listed aliased
  commands twice; `ClassDiscoveryBridge` accepted non-`Command` classes.
- `ArgvInput`: short array options overwrote instead of appending; `-xyz` dropped
  `yz` (grouped flags now follow POSIX Guideline 5); `--flag=value` and surplus
  operands were silently ignored.
- `ArrayInput`: no argument defaults, unknown names accepted, `(bool) 'false'` was true.
- `InputMapper`: `abc`/`1.5`/`nope` silently became defaults; `#[Option(isArray)]` ignored.
- `Prompter::select()` picked the last choice for out-of-range answers;
  `multiselect()` read `a,b` as the first choice.
- `BufferedOutput` never formatted and printed newlines in quiet mode;
  `ProgressBar` threw on negative steps and right-padded the percentage.
- Colour ignored `NO_COLOR`/`TERM=dumb` and was always off on Windows.
- `posix_kill(getmypid())` could become `posix_kill(0)` (whole process group).
- Built-in command descriptions had three sources of truth; `add()` ignored
  `#[AsCommand]` aliases and `hidden`.

### Added
- `-h/--help` and `-V/--version` (GNU Coding Standards §4.8).
- `EventMiddleware` (wires the previously unused `EventDispatcherBridge`),
  `ObservabilityMiddleware`, `ObservabilityBridge`, `CommandSpan`, `Instrumentation`
  — ARFA P5 metrics and spans per OTel CLI conventions (ADR-010).
- `DiagnosticFormat`, `Shell`, `SourceLocation`; descriptor validation.
- `phpunit.xml.dist` (strict), `phpstan.neon` (level max), `.gitattributes`;
  `composer.json`: `ext-mbstring`, suggestions, `extra.kariricode.arfa` (level 1).
- ADR-009, ADR-010, ADR-011; amendments to ADR-001–008.

### Tests (17 → 37 files)
- Unit, Integration (real processes, pseudo-terminal, three shells) and
  Conformance (20 Spec V4.0 rules, each proven by a planted violation).
- `@covers` doc-comments → `#[CoversClass]` (removed in PHPUnit 12).

### Documentation
- ARFA conformance corrected to **Level 1** (P1, P4, P5). Earlier docs claimed P2/P3,
  which require Level 2. No ASVS level is declared (no assessment performed).

## [4.2.2] - 2026-03-10

Deep-review round 2 — parser correctness and error observability.

### Fixed
- **Array positional arguments now capture every token.** The parser advanced the
  argument index after each positional, so an array argument (`files*`) kept only its
  first token and silently dropped the rest. Array arguments are now greedy — subsequent
  positionals feed the same slot.
- **Optional argument defaults are now applied.** Options honored their `default`, but
  arguments did not: an optional argument left off the command line returned null instead
  of its declared default. Arguments and options are now symmetric.

### Changed
- `LoggingMiddleware` now logs command errors: a command that throws is recorded via
  `logCommandError()` (previously dead code) before the exception is re-thrown, so a
  crashing command no longer leaves only a "start" record with no terminal entry.
- `InputMapper` bool coercion accepts `y`/`on` in addition to `1`/`true`/`yes`, matching
  `PromptResult::asBoolean()`.

### Tests (16 → 17 files)
- ArgvInputTest: array-argument greedy capture, array-after-scalar, optional-argument default.
- LoggingMiddlewareTest: success path logs start+finish; error path logs start+error and
  re-throws without a finish record.

## [4.2.1] - 2026-03-09

Deep-review pass — functional gaps, consistency, and test coverage.

### Added
- Global CLI flags wired into `Application::dispatch()`: `-q`/`--quiet`, `-v`/`--verbose`,
  `-vv`, `-vvv`/`--debug` set output verbosity; `-n`/`--no-interaction` disables prompts.
  These were previously parsed by `Verbosity::fromFlag()` but never consumed, so passing
  them to a command raised "unknown shortcut".
- Six new test suites: ApplicationDispatchTest, ConcreteInputDefinitionTest,
  TableFormatterTest, SeverityTest, DiagnosticOutputTest, AnsiFormatterTest (10 → 16 files).

### Changed
- `Application::run()` now catches any `Throwable` from a command (or middleware), wraps it
  via `executionFailed()` (original preserved as `$previous`), and returns a clean failure —
  a misbehaving command can no longer emit a PHP fatal.
- `ConsoleServiceProvider::build()` accepts optional name/version overrides; `buildApplication()`
  now passes its `$name`/`$version` through instead of silently dropping them.
- `Verbosity::fromFlag()` returns `?self` (null when the token is not a verbosity flag)
  instead of silently defaulting to `Normal`.
- `CommandResult::invalid()` accepts `durationMs` for parity with `success()`/`failure()`.
- `CompletionCommand` promoted to `final readonly class` (consistency with Help/List).

### Removed
- `CommandDescriptor::matchesName()` — dead code; alias resolution lives solely in
  `CommandRegistry` key registration (single source of truth, YAGNI).

## [4.2.0] - 2026-03-08

### Added
- `DiagnosticOutput` — structured diagnostic emission (human/github/json) via decorator pattern (ADR-006)
- `Severity` pure enum — Error, Warning, Info, Hint with ANSI colour, GitHub annotation, and ordinal comparison
- `CompletionCommand` V4.2 — per-command option and argument completion for bash, zsh, and fish shells
- Proper POSIX shell escaping in completion scripts (`shellEscape()` replaces `addslashes()`)
- Multi-byte safe table rendering via `mb_strwidth()` / `mb_str_pad()` (PHP 8.3+)
- ADR-006, ADR-007, ADR-008 documentation

### Changed
- `Application::dispatch()` — command name token is now stripped before parse(), preventing it from consuming the first positional argument slot
- `Application::dispatch()` — removed dead `method_exists()` check (getTokens is on the Input interface)
- `CommandRegistry::ensureLoaded()` — now propagates aliases and hidden from `#[AsCommand]` metadata
- `ConfiguratorBridge` — lazy config resolution (ARFA 1.3 P3); was eager in constructor
- `InteractiveHelper` — injectable `$inputStream` for testability; was hardcoded to STDIN
- `ProgressBar::buildIndeterminate()` — spinner index mutation moved to `redraw()` (build methods are now pure)
- `BufferedOutput` / `StreamOutput` — `PHP_EOL` → `"\n"` for cross-platform console consistency
- `ConsoleServiceProvider` — default version updated to 4.2.0
- Section markers normalized to 72 display columns across all files

### Fixed
- `AnsiFormatter` docblock — removed false claim of RGB/256-color support
- `DiagnosticOutput::countBuffered()` docblock — removed misleading "for a given severity" text
- `CommandDescriptor` docblock — stale `Application::instantiate()` reference → `instantiateFromDescriptor()`

### Spec V4.0 Compliance
- `CommandDescriptor` promoted to `final readonly class` (value object rule)
- `MapInput` attribute — `final class` → `final readonly class`; `\Attribute` → `use Attribute`
- `InputDefinition` contract — docblock corrected from "Read-only view" to "mutable builder / read-only schema"
- Documentation reorganized: `docs/architecture/` → `docs/adr/`, `docs/specs/` → `docs/spec/`

## [4.1.0] - 2026-03-06

### Added
- `MiddlewarePipeline` — immutable, composable command middleware (ADR-005)
- `CommandMiddleware` contract — `handle(CommandContext, callable $next): CommandResult`
- `ConcreteCommandContext` — immutable dispatch context
- `TimingMiddleware`, `LoggingMiddleware`, `SignalMiddleware` — default middleware stack
- `Signalable` contract and `Signal` pure enum — opt-in POSIX signal handling (ADR-008)
- `InteractiveHelper` — text, password, confirm, select, multiselect, spin prompts (ADR-007)
- `PromptResult` — immutable value object distinguishing user input from defaults
- `InputMapper` — readonly DTO hydration via constructor reflection (ADR-004)
- `MapInput` attribute — marks DTO classes for InputMapper
- `CompletionCommand` — shell tab-completion for bash, zsh, fish
- `ConsoleServiceProvider` — wires Application with default middleware stack

## [4.0.0] - 2026-03-06

### Added
- Initial V4.0 release — ARFA 1.3 / Spec V4.0 compliance
- Interface-based command architecture (`Command` contract, no base class)
- `CommandRegistry` with lazy-loading `CommandLoader` chain (ADR-001)
- `ExitCode` pure enum with `exitCode(): int` accessor (ADR-002)
- Semantic exception factories — error codes 9000–9099 (ADR-003)
- `AnsiFormatter` — XML-style markup to ANSI escape sequences
- `TableFormatter`, `ProgressBar` — terminal formatters
- `ArgvInput`, `ArrayInput` — token-based and array-based input parsing
- Integration bridges: ClassDiscovery, Configurator, EventDispatcher, Logging
- `HelpCommand`, `ListCommand` built-in commands

### Changed (from V3.1)
- Minimum PHP version: 8.4 (was 8.3)
- Architecture: ARFA 1.3 (was 1.2)
- Specification: Quality Directive V4.0 (was V3.2)
- All value objects: `final readonly class`
- All enums: pure (no backed values)
- Ecosystem bridges: callable duck-typing (no hard deps)
