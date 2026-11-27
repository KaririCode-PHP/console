# ADR-012 — Quality Gate Tooling (Spec v4.3.3 §29)

**Status:** Accepted
**Date:** 2026-09-30
**Component:** KaririCode\Console 4.4
**ARFA:** 1.3 (process; no principle claimed)

## Context

Spec v4.3.3 §29.1 defines the gate by command: `phpstan`, `cs-check`,
`complexity`, `test`, `infection`, `test-conformance`, `psalm`, `security`.
Up to 4.3.2 Console ran only PHPStan, PHPUnit and Psalm (level 2) under other
script names. §13 sets complexity budgets; §24 adds supply-chain and
secret-scanning requirements; G4 requires `arfa.level` and `asvs.level`.

## Decision

| §29.1 command | Implementation | Pass criterion |
|---|---|---|
| `composer phpstan` | PHPStan 2 level max over `src` + `tests`, plus `tomasvotruba/cognitive-complexity` | 0 errors; cognitive ≤ 15 per method |
| `composer cs-check` | PHP-CS-Fixer `@PER-CS` | clean |
| `composer complexity` | `tools/complexity.php` (nikic/php-parser) | cyclomatic ≤ 10, nesting ≤ 3, parameters ≤ 4; public methods ≤ 10 reported (soft) |
| `composer test` / `test-conformance` | PHPUnit 11 (Unit, Integration, Conformance) | 100% green |
| `composer infection` | Infection 0.35 (needs PCOV/Xdebug) | covered MSI ≥ 80% |
| `composer psalm` | Psalm 6 error level 1 ("strict") | 0 errors |
| `composer security` | `composer audit`, `tools/secret-scan.php`, PHPUnit group `security` | all clean |

Measurement choices, each a reading of the spec rather than an exception to it:

- **Cyclomatic complexity counts a `switch`/`match` as one decision.** §13
  states that "a flat 12-arm `match` is high-cyclomatic but low-cognitive —
  acceptable"; counting each arm would forbid exactly that.
- **Parameters ≤ 4 exempts constructors of readonly classes whose parameters
  are all promoted.** Such a constructor *is* the parameter object; call sites
  use named arguments (§30).
- **Psalm level 1.** Values that are untyped by design (raw CLI input,
  configuration arrays) carry `@var mixed` and are narrowed on the next line;
  where Psalm's stubs lose a known type (`array_find`, PHP 8.4 interface
  properties) the precise type is written with `@var`. Suppressed only:
  Psalm's missing support for interface and virtual hooked properties, and the
  intentional `stty` calls.
- **One analyser disagreement**, on `pcntl_signal_get_handler()`: PHPStan's
  stub and Psalm's are incompatible; that single line carries a PHPStan ignore
  with its identifier and reason.
- **Golden-output tests** (`tests/Fixture/golden`) pin every renderer's exact
  output; fragment assertions let concatenation and line-break mutants escape
  (covered MSI rose from 74% to 82%, with tests for the gaps mutation testing exposed).

## Alternatives Considered

- **Delegate to `kariricode/devkit` (`kcode`).** It is the ecosystem's
  toolchain, but its command mapping to §29.1 was not verified here.
- **PHPMD for complexity.** Its parser does not cover PHP 8.4 property hooks.

## Consequences

- `composer check` runs every gate except mutation testing, which needs a
  coverage driver and runs in CI.
- The gate's Windows leg exists only in `.github/workflows/ci.yml` and has not
  run yet.

## References

- KaririCode Spec v4.3.3 §13, §18, §24, §29, §30
- McCabe (1976); Campbell, *Cognitive Complexity* (SonarSource, 2018)
