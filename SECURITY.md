# Security Policy

## Reporting a vulnerability

Report vulnerabilities privately to **walmir.silva@kariricode.org** — please do
not open a public issue. Include the affected version, a reproduction and the
impact you observed.

## Supported versions

Security fixes are released for the latest minor version (currently 4.5.x) as
a patch release.

## Dependency remediation (OWASP ASVS 5.0 V15.1.1)

- **Runtime:** two PSR interface packages (`psr/log`, `psr/event-dispatcher`)
  besides PHP and `ext-mbstring`; enforced by the conformance suite.
- **Development tooling** (PHPUnit, PHPStan, Psalm, Infection, php-cs-fixer) is
  never shipped. `composer security` runs `composer audit`; a release is
  blocked while any advisory is reported, so affected tooling is remediated
  before the next release.

## Verification

`composer security` = `composer audit` + secret scan (`tools/secret-scan.php`)
+ the PHPUnit `security` group. The threat model is in
[docs/security/SECURITY.md](docs/security/SECURITY.md) and the Level 1 assessment in
[docs/security/ASVS-5.0-L1-assessment.md](docs/security/ASVS-5.0-L1-assessment.md).
