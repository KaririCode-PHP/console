# ADR-015 — Quality Gate through KaririCode Devkit, with the Root Configs

**Status:** Accepted; amends ADR-012 (where the tools come from)
**Date:** 2026-11-27
**Component:** KaririCode\Console 4.6
**ARFA:** 1.3 (process; no principle claimed)

## Context

ADR-012 put the §29.1 tools in `require-dev`. The ecosystem now installs
its toolchain with KaririCode Devkit, and Devkit 1.2.1 runs a component's
committed configs (`'root_configs' => true`) instead of its generated ones,
so the gates do not weaken on the way: kariricode/contract, exception,
property-inspector, class-discovery and processor-pipeline moved first.

## Decision

- `require-dev` is removed. `devkit.php` pins PHPUnit 11.5.56, PHPStan 2.3.7
  with tomasvotruba/cognitive-complexity 1.2.0, Psalm 6.19.1,
  PHP-CS-Fixer 3.95.27 and Infection 0.35.5; kcode installs them into
  `.kcode/vendor/`.
- The root configs of ADR-012 stay and are what kcode runs; paths into
  `vendor/` now point into `.kcode/vendor/`. `tools/complexity.php` and
  `tools/named-arguments.php` load nikic/php-parser from there, and the
  signal test's child process loads PHPUnit from there.
- The commands of ADR-012 are unchanged: `composer check`, `composer
  infection`, `composer sbom`; `composer security` also audits the
  toolchain.
- `composer.lock` is committed with the runtime only: kariricode/contract
  2.12.0, psr/event-dispatcher 1.0.0, psr/log 3.0.2.
- CI on Linux installs kcode 1.2.1 by version and SHA-256 and runs every
  gate. The Windows job, which exists to execute VT100 detection and the
  prompts on Windows, installs the same PHPUnit with `composer global
  require` and runs the unit and integration suites: kcode has not been
  verified on Windows, and the conformance suite reads the code, which
  Linux already covers.

## Consequences

- One toolchain model across the ecosystem, with Console's gates unchanged.
- The Windows job checks behaviour, not style or static analysis; Linux
  covers those.
- Contributors install kcode once (README, Quality).
