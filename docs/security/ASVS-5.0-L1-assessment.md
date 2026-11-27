# OWASP ASVS 5.0.0 — Level 1 Assessment

| | |
|---|---|
| Component | `kariricode/console` 4.5.0 |
| Standard | OWASP ASVS 5.0.0 (May 2025), Level 1 — 70 requirements |
| Result | **14 applicable, all met; 56 not applicable** |
| Date | 2026-09-30 |
| Verification | Every ID checked against the official CSV at tag v5.0.0: 345 requirements, 70 at Level 1; all 70 accounted for below, none duplicated or mis-levelled |

Scope: the library's own behaviour. Console is a toolkit for command-line applications; authentication, authorization, cryptography and network I/O are the host application's responsibility and are assessed there.

Requirement IDs and levels come from the official CSV release (`OWASP_Application_Security_Verification_Standard_5.0.0_en.csv`). Summaries are paraphrased; see the standard for the normative text (ASVS is licensed CC BY-SA 4.0).

## Applicable requirements

| ID | Requirement (paraphrased) | How Console meets it | Evidence |
|---|---|---|---|
| V1.2.3 | JSON is built with an encoder, never by concatenation | `json_encode` with `JSON_THROW_ON_ERROR`/`INVALID_UTF8_SUBSTITUTE`, then escaped for the console formatter (ADR-011) | `BuiltinCommandsTest::testListJsonIsValidAndKeepsDescriptionsVerbatim`, `DiagnosticOutputTest::testJsonFormatSurvivesInvalidUtf8AndMarkup` |
| V1.2.5 | No OS command injection | Completion scripts: identifiers allow-listed, free text quoted per shell; `stty` arguments `escapeshellarg()`'d; no other OS calls | `BuiltinCommandsTest::testCompletionRejectsProgramNamesThatCouldInjectShellCode`, `CompletionScriptsTest` (canary in bash/zsh/fish), conformance `testNoDynamicCodeExecutionOrUnexpectedShellCalls` |
| V1.3.2 | No dynamic code execution | No `eval`, backticks, `assert`/`create_function`; commands are built from registered class names checked against the `Command` contract | conformance `testNoDynamicCodeExecutionOrUnexpectedShellCalls`, `LoaderInstantiationTest` |
| V2.1.1 | Validation rules are documented | SPEC-001 §3 (grammar, exit codes), §4 (descriptor rules), ADR-004 (strict coercion) | documents |
| V2.2.1 | Input is validated against expectations | Unknown options, surplus operands, malformed values and invalid choices are rejected (9003/9004/9005), never defaulted | `ArgvInputPosixTest`, `ArrayInputTest`, `DescriptorValidationTest`, `InputMapperTest`, `PrompterTest` |
| V2.2.2 | Validation happens in the trusted layer | All validation is in the library's parser and mapper; shell completion is advisory only | design; tests above |
| V5.3.2 | File paths are not built from user input | Discovery paths come from configuration (`scan_paths`), never from command-line input | design |
| V6.2.6 | Secret entry is masked (by analogy: terminal prompt) | Echo disabled with `stty`, restored in `finally`; refuses when the terminal cannot hide input | `PasswordPromptTest` (real pseudo-terminal) |
| V6.2.7 | Paste and password managers work (by analogy) | Only echo is disabled; input handling is untouched | design |
| V6.2.8 | Secrets are used exactly as received (by analogy) | Only the line terminator is removed — fixed in 4.4.0 (surrounding spaces were trimmed) | `PrompterTest::testPasswordKeepsSurroundingSpaces` |
| V13.4.1 | No source-control metadata in the artifact | Release tarballs contain no `.git`; `.gitattributes` export-ignores development files | release procedure |
| V15.1.1 | Remediation time frames are documented | `SECURITY.md`: no runtime third-party components; development tooling is remediated before any release (`composer security` blocks it) | `SECURITY.md` |
| V15.2.1 | Components are within those time frames | Runtime components are two PSR interface packages; `composer audit` runs in `composer security` | conformance `testRuntimeRequiresOnlyPhpExtensionsAndContracts` |
| V15.3.1 | Only the needed data is exposed | Telemetry never collects command arguments; stack traces only at `-vvv` | `TelemetryMiddlewareTest`, `ApplicationGrammarTest::testStackTraceIsShownOnlyAtDebugVerbosity` |

## Not applicable

| Chapter | IDs | Reason |
|---|---|---|
| V1 Encoding and Sanitization | V1.2.1, V1.2.2, V1.2.4, V1.3.1, V1.5.1 | no HTTP/HTML/XML output, URL building, database access, rich-text input or XML parsing |
| V2 Validation and Business Logic | V2.3.1 | V2.3.1 concerns multi-step business flows, which belong to the application |
| V3 Web Frontend Security | V3.2.1, V3.2.2, V3.3.1, V3.4.1, V3.4.2, V3.5.1, V3.5.2, V3.5.3 | no web frontend |
| V4 API and Web Service | V4.1.1, V4.4.1 | no HTTP API or WebSocket |
| V5 File Handling | V5.2.1, V5.2.2, V5.3.1 | no file upload or archive handling |
| V6 Authentication | V6.1.1, V6.2.1, V6.2.2, V6.2.3, V6.2.4, V6.2.5, V6.3.1, V6.3.2, V6.4.1, V6.4.2 | no authentication (V6.2.6–V6.2.8 are applied by analogy to the password prompt above) |
| V7 Session Management | V7.2.1, V7.2.2, V7.2.3, V7.2.4, V7.4.1, V7.4.2 | no sessions |
| V8 Authorization | V8.1.1, V8.2.1, V8.2.2, V8.3.1 | no authorization decisions |
| V9 Self-contained Tokens | V9.1.1, V9.1.2, V9.1.3, V9.2.1 | no tokens |
| V10 OAuth and OIDC | V10.4.1, V10.4.2, V10.4.3, V10.4.4, V10.4.5 | no OAuth/OIDC |
| V11 Cryptography | V11.3.1, V11.3.2, V11.4.1 | no cryptography |
| V12 Secure Communication | V12.1.1, V12.2.1, V12.2.2 | no network communication |
| V14 Data Protection | V14.2.1, V14.3.1 | no browser or client storage |

## Re-assessment

Re-run this assessment when Console gains network, file-upload, cryptographic or authentication features, or when a new ASVS version is adopted. The security suite (`composer security`, PHPUnit group `security`) keeps the applicable controls verified on every release.

