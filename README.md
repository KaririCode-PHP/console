# KaririCode\Console

**PHP 8.4 console framework: interface-based commands, POSIX argument parsing and a middleware pipeline; its only runtime dependencies are two PSR interface packages.**

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-8892be)](https://php.net)
[![License MIT](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![ARFA 1.3 L1](https://img.shields.io/badge/ARFA-1.3%20Level%201-blue)](docs/spec/SPEC-001-console-architecture.md)
[![ASVS 5.0 L1](https://img.shields.io/badge/ASVS-5.0%20Level%201-blue)](docs/security/SECURITY.md)
[![Dependencies: PSR only](https://img.shields.io/badge/dependencies-PSR%20only-brightgreen)](composer.json)

---

## Design at a glance

| Concern | Choice |
|---|---|
| Commands | Plain objects implementing a four-member `Command` interface — no base class |
| Identity | PHP 8.4 interface properties (`public string $name { get; }`), not accessors (ADR-009) |
| Parsing | POSIX utility syntax: grouped flags, `--`, `-`; unknown or surplus input is rejected |
| Testing | `ArrayInput` + `BufferedOutput` apply exactly the rules of `ArgvInput` + `ConsoleOutput` |
| Errors | Written to stderr; usage errors exit 2, failures exit 1 |
| Output safety | Untrusted text is escaped (markup, ESC/CR sequences); completion scripts are injection-tested |
| Telemetry | PSR-3 logs, PSR-14 events, a duration histogram and one span per command, correlated by a W3C trace id |
| Dependencies | Runtime: `php ^8.4`, `ext-mbstring` and two PSR interface packages (`psr/log`, `psr/event-dispatcher`) |

Symfony Console 7.4 was the reference this design was checked against
(signals, formatter, prompts). This README makes no claims about other libraries.

---

## Installation

```bash
composer require kariricode/console
```

Requires PHP 8.4 and `ext-mbstring`. Optional: `ext-pcntl` (signals),
`ext-posix` (re-raising declined signals), `ext-tokenizer` (`#[AsCommand]` discovery).

---

## Quickstart

```php
use KaririCode\Console\Contract\{Command, Input, InputDefinition, Output};
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Formatter\AnsiFormatter;
use KaririCode\Console\ValueObject\{ArgumentDescriptor, OptionDescriptor};

final class GreetCommand implements Command
{
    public string $name { get => 'greet'; }
    public string $description { get => 'Greet someone'; }

    public function configure(InputDefinition $definition): void
    {
        $definition->addArgument(new ArgumentDescriptor('name', 'Who to greet'));
        $definition->addOption(new OptionDescriptor('shout', 'Uppercase the greeting', shortcut: 's', acceptsValue: false));
    }

    public function execute(Input $input, Output $output): ExitCode
    {
        $greeting = 'Hello, ' . $input->getArgument('name') . '!';
        $greeting = $input->getOption('shout') ? strtoupper($greeting) : $greeting;

        // User input is untrusted: escape it before embedding it in markup.
        $output->writeln('<info>' . AnsiFormatter::escape($greeting) . '</info>');

        return ExitCode::Success;
    }
}
```

```php
#!/usr/bin/env php
<?php // bin/app

use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Provider\ConsoleServiceProvider;
use KaririCode\Console\Output\ConsoleOutput;

require __DIR__ . '/../vendor/autoload.php';

exit(ConsoleServiceProvider::buildApplication('My App', '1.0.0')
    ->add(new GreetCommand())
    ->dispatch(new ArgvInput(), new ConsoleOutput()));
```

```bash
$ bin/app greet Walmir -s
HELLO, WALMIR!
$ bin/app greet --help        # usage, arguments, options
$ bin/app list --format=json  # machine-readable, never mixed with errors
```

---

## Command-line grammar

```
app [global-flags] [command] [command-options/arguments]
```

| Global flag | Effect |
|---|---|
| `-h`, `--help` | Help for the command (or the application) |
| `-V`, `--version` | Prints `name version` |
| `-q`, `--quiet` / `-v`, `-vv`, `-vvv` | Verbosity; `-vvv` adds stack traces to errors |
| `-n`, `--no-interaction` | Prompts return their defaults |
| `--` | Ends option parsing: later tokens are operands, even `-v` |

Commands may not declare these names or shortcuts; a collision is reported
(exit 2) instead of silently losing the command's option.

| Exit status | Meaning |
|---|---|
| 0 | Success |
| 1 | Failure (unknown command, exception in the command) |
| 2 | Invalid usage (unknown option, missing or surplus argument, invalid value) |

---

## Readonly input DTOs

```php
use KaririCode\Console\Attribute\{Argument, MapInput, Option};
use KaririCode\Console\Input\InputMapper;

#[MapInput]
final readonly class DeployInput
{
    public function __construct(
        #[Argument] public string $environment,
        #[Option(acceptsValue: false)] public bool $force = false,
        #[Option(isArray: true)] public array $tag = [],
        #[Option] public int $retries = 3,
    ) {}
}

// configure():
InputMapper::registerInto(DeployInput::class, $definition);

// execute():
$deploy = InputMapper::map(DeployInput::class, $input);
```

Conversion is strict: `--retries=abc` or `--retries=1.5` is a usage error (exit 2),
never a silent default.

---

## Middleware

```php
use KaririCode\Console\Contract\{CommandContext, CommandMiddleware};
use KaririCode\Console\ValueObject\CommandResult;

final readonly class AuditMiddleware implements CommandMiddleware
{
    public function handle(CommandContext $context, callable $next): CommandResult
    {
        $result = $next($context);
        audit($context->commandName, $result->exitCode);

        return $result;
    }
}

$app->addMiddleware(new AuditMiddleware()); // first added = outermost
```

---

## Container and configuration

Console registers itself like any other KaririCode component, through the
providers of kariricode/contract (ADR-016):

```php
use KaririCode\Console\Core\Application;
use KaririCode\Console\Integration\ConfiguratorBridge;
use KaririCode\Console\Provider\{ConsoleConfigProvider, ConsoleServiceProvider};

$builder->withProvider(new ConsoleConfigProvider());   // key 'console': name, version, scan_paths, debug

$container->register(new ConsoleServiceProvider(
    configurator: new ConfiguratorBridge(static fn (): array => $config->get('console')),
));

$app = $container->get(Application::class);            // built once, on first use
```

`register()` only binds; the configuration is read and the command
directories are scanned when the container first builds the application.

---

## Telemetry

```php
use KaririCode\Console\Integration\{Instrumentation, ObservabilityBridge};
use KaririCode\Console\Provider\ConsoleServiceProvider;

$app = (new ConsoleServiceProvider(instrumentation: new Instrumentation(
    logger: $psrLogger,              // PSR-3
    events: $psr14Dispatcher,        // PSR-14: CommandStarted, CommandFinished, CommandFailed
    observability: new ObservabilityBridge(
        histogram: $meter->record(...),     // ('console.command.duration', seconds, attributes)
        spanExporter: $tracer->export(...), // CommandSpan
    ),
)))->build();
```

Every log record, event and span of a run carries the same W3C trace id
(`TraceId`), so they correlate. Spans follow the OTel CLI conventions;
command arguments are never collected (PII). See ADR-010 and ADR-013.

---

## Signals

```php
use KaririCode\Console\Contract\Signalable;
use KaririCode\Console\Enum\Signal;

final class WorkerCommand implements Command, Signalable
{
    public array $subscribedSignals { get => [Signal::Terminate, Signal::Interrupt]; }
    private bool $stopping = false;

    public function handleSignal(Signal $signal): bool
    {
        $this->stopping = true;

        return true; // handled: finish the current job, then exit
    }
    // … while (!$this->stopping) { process(); }
}
```

Returning `false` gives the signal its previous treatment: the earlier PHP
handler runs, or with the default disposition the process ends by the signal.

---

## Prompts

```php
use KaririCode\Console\Prompt\Prompter;

$prompt = new Prompter($input, $output);

$name   = $prompt->text('Project name', default: 'app')->value;
$db     = $prompt->select('Database', ['MySQL', 'PostgreSQL', 'SQLite']);
$extras = $prompt->multiselect('Extras', ['Redis', 'Queue', 'Mail'], defaults: [0]);
$secret = $prompt->password('API token')->value;
```

With `-n` (or `ArrayInput` in tests) every prompt returns its default without
reading. Invalid choices are re-asked (three attempts); a password is refused
rather than echoed when the terminal cannot hide input — which includes Windows
consoles (no `stty`): there, pipe the secret or read it from the environment.

---

## Output and escaping

- Markup: `<info>`, `<comment>`, `<question>`, `<e>`, `<b>`, `<u>`; other `<…>` is literal text.
- `AnsiFormatter::escape($untrusted)` before embedding user data in markup.
- Colour follows [NO_COLOR](https://no-color.org), `TERM=dumb` and TTY detection.
- `$output->errorOutput` is stderr under `ConsoleOutput`.

---

## Shell completion

```bash
bin/app completion bash --program app > /etc/bash_completion.d/app
bin/app completion zsh  --program app > ~/.zsh/completions/_app
bin/app completion fish --program app > ~/.config/fish/completions/app.fish
```

The test suite runs the real completion machinery of each shell (bash readline
in a pseudo-terminal, zsh `compinit`, fish `complete -C`), including names with
colons such as `cache:warm`, and sources every script with hostile descriptions
to prove nothing embedded executes.

---

## Testing commands

```php
$output = new BufferedOutput();
$result = $app->run('greet', new ArrayInput(['name' => 'Walmir'], ['shout' => true]), $output);

self::assertTrue($result->isSuccess());
self::assertSame("HELLO, WALMIR!\n", $output->buffer);
```

---

## Documentation

| Document | Topic |
|---|---|
| [SPEC-001](docs/spec/SPEC-001-console-architecture.md) | Architecture, grammar, conformance, verification |
| [ADR-001](docs/adr/ADR-001-command-discovery-strategy.md) | Command discovery |
| [ADR-002](docs/adr/ADR-002-exitcode-pure-enum.md) | `ExitCode` pure enum |
| [ADR-003](docs/adr/ADR-003-error-code-allocation.md) | Error codes 9000–9099 |
| [ADR-004](docs/adr/ADR-004-inputmapper-readonly-dto.md) | Readonly input DTOs |
| [ADR-005](docs/adr/ADR-005-middleware-pipeline.md) | Middleware pipeline |
| [ADR-006](docs/adr/ADR-006-diagnostic-output.md) | Diagnostic output |
| [ADR-007](docs/adr/ADR-007-interactive-prompts.md) | Interactive prompts |
| [ADR-008](docs/adr/ADR-008-signal-handling-middleware.md) | Signal handling |
| [ADR-009](docs/adr/ADR-009-interface-properties-over-accessors.md) | Interface properties over accessors |
| [ADR-010](docs/adr/ADR-010-telemetry-instrumentation.md) | Telemetry (ARFA P5) |
| [ADR-011](docs/adr/ADR-011-output-encoding-and-escaping.md) | Output encoding and escaping |
| [ADR-012](docs/adr/ADR-012-quality-gate-tooling.md) | Quality gate tooling (§29) |
| [ADR-013](docs/adr/ADR-013-psr-telemetry-and-correlation.md) | PSR-3/PSR-14 telemetry and trace correlation |
| [ADR-014](docs/adr/ADR-014-contract-exception-families.md) | Exceptions on the contract families |
| [ADR-015](docs/adr/ADR-015-toolchain-through-kcode.md) | Toolchain through KaririCode Devkit |
| [ADR-016](docs/adr/ADR-016-contract-di-and-config-providers.md) | Contract DI and configuration providers |
| [CHANGELOG](CHANGELOG.md) | Release history |

---

## Quality

The Spec v4.3.3 §29 gate, by command (`composer check` runs all but mutation
testing, which needs PCOV or Xdebug). The tools come from
[KaririCode Devkit](https://github.com/KaririCode-PHP/devkit) (`kcode`), pinned in
`devkit.php`, and run this repository's configs (ADR-015):

```bash
wget https://github.com/KaririCode-PHP/devkit/releases/latest/download/kcode.phar
chmod +x kcode.phar && sudo mv kcode.phar /usr/local/bin/kcode   # once
composer install && kcode init                                   # runtime, then the toolchain
composer check && composer infection
```

| Command | Result |
|---|---|
| `composer cs-check` | PER Coding Style clean |
| `composer phpstan` | level max, 0 errors (cognitive complexity ≤ 15) |
| `composer psalm` | error level 1, 0 errors |
| `composer complexity` | within the §13 budgets |
| `composer test` | 360 tests, 790 assertions, 0 failures |
| `composer infection` | covered MSI 81% |
| `composer security` | audit of the runtime and the toolchain, secret scan and fail-closed tests clean |

Security scope and threat model: [SECURITY.md](docs/security/SECURITY.md).

---

## License

MIT © Walmir Silva — see [LICENSE](LICENSE).
