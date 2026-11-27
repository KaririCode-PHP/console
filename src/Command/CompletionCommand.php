<?php

declare(strict_types=1);

namespace KaririCode\Console\Command;

use KaririCode\Console\Attribute\AsCommand;
use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Core\Application;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Enum\Shell;
use KaririCode\Console\Formatter\AnsiFormatter;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Generates shell tab-completion scripts for bash, zsh, and fish.
 *
 * V4.2 enhancement: scripts now complete per-command options and
 * arguments, not just command names. Each shell script embeds a
 * case statement keyed by the active sub-command so the completion
 * engine can offer --fields, --namespace, --format etc. at the right
 * position without a round-trip to the process.
 *
 * Run once and source the output from your shell profile:
 *
 *   bash: eval "$(./app completion bash)"
 *   zsh:  eval "$(./app completion zsh)"
 *   fish: ./app completion fish | source
 *
 * Side effects: writes the generated script to the output.
 *
 * @package KaririCode\Console\Command
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
#[AsCommand('completion', description: 'Generate shell tab-completion scripts')]
final class CompletionCommand implements Command
{
    /**
     * Allow-list for the program name, which is spliced into shell function
     * names and `complete` invocations: anything else could inject code into
     * the generated script (CWE-78), so it is rejected, never escaped.
     */
    private const string PROGRAM_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/';

    public string $name {
        get => 'completion';
    }

    public string $description {
        get => 'Generate shell tab-completion scripts';
    }

    public function __construct(private readonly Application $application) {}

    // ── Command lifecycle ────────────────────────────────────────────────

    #[\Override]
    public function configure(InputDefinition $definition): void
    {
        $definition->addArgument(new ArgumentDescriptor(
            name: 'shell',
            description: 'Target shell: bash, zsh, or fish',
            required: false,
            default: 'bash',
        ));

        $definition->addOption(new OptionDescriptor(
            name: 'program',
            description: 'Executable name the script completes (letters, digits, _ . -)',
            default: 'app',
        ));
    }

    #[\Override]
    public function execute(Input $input, Output $output): ExitCode
    {
        /** @var mixed $shellName Raw CLI value (Input returns mixed by contract); narrowed below. */
        $shellName = $input->getArgument('shell');
        /** @var mixed $program Raw CLI value (Input returns mixed by contract); narrowed below. */
        $program   = $input->getOption('program', 'app');
        $shellName = is_string($shellName) ? $shellName : 'bash';
        $program   = is_string($program) ? $program : 'app';
        $shell     = Shell::parse($shellName);

        if ($shell === null) {
            $output->errorOutput->writeln('<e>' . AnsiFormatter::escape("Unsupported shell \"{$shellName}\". Supported: bash, zsh, fish.") . '</e>');

            return ExitCode::Invalid;
        }

        if (preg_match(self::PROGRAM_PATTERN, $program) !== 1) {
            $output->errorOutput->writeln('<e>' . AnsiFormatter::escape("Invalid program name \"{$program}\": use letters, digits, _ . -") . '</e>');

            return ExitCode::Invalid;
        }

        $script = match ($shell) {
            Shell::Bash => $this->generateBash($program),
            Shell::Zsh  => $this->generateZsh($program),
            Shell::Fish => $this->generateFish($program),
        };

        // The script is data, not markup: escape so no `<tag>` in a command
        // description is rendered by the console formatter.
        $output->write(AnsiFormatter::escape($script));

        return ExitCode::Success;
    }

    // ── Shell generators ─────────────────────────────────────────────────

    private function generateBash(string $program): string
    {
        $commandList  = $this->commandList();
        $optionsCases = $this->buildBashOptionsCases();

        // COMP_WORDS is split on COMP_WORDBREAKS, which contains ':', so a
        // name like cache:warm arrives as three words. The script re-splits
        // the line on whitespace and trims the part readline will not replace
        // (the approach of bash-completion's __ltrim_colon_completions).
        return <<<BASH
        _{$program}_completion() {
            local line="\${COMP_LINE:0:COMP_POINT}"
            local -a words
            read -ra words <<< "\$line"
            [[ \$line == *[[:space:]] ]] && words+=("")
            local cword=\$(( \${#words[@]} - 1 ))
            local cur="\${words[cword]}"
            local commands="{$commandList}"

            if (( cword == 1 )); then
                COMPREPLY=(\$(compgen -W "\$commands" -- "\$cur"))
            else
                case "\${words[1]}" in
        {$optionsCases}
                esac
            fi

            if [[ \$cur == *:* && \$COMP_WORDBREAKS == *:* ]]; then
                local prefix="\${cur%"\${cur##*:}"}" i
                for i in "\${!COMPREPLY[@]}"; do
                    COMPREPLY[i]="\${COMPREPLY[i]#"\$prefix"}"
                done
            fi

            return 0
        }
        complete -F _{$program}_completion {$program}
        BASH . "\n";
    }

    private function generateZsh(string $program): string
    {
        $commandEntries = $this->commandEntriesForZsh();
        $optionsCases   = $this->buildZshOptionsCases();

        return <<<ZSH
        _{$program}() {
            # _arguments -C and ->state assign these; callers must localise them.
            local curcontext="\$curcontext" state state_descr line
            typeset -A opt_args
            _arguments -C '1: :->command' '*:: :->args'

            case \$state in
                command)
                    local -a commands
                    commands=(
        {$commandEntries}
                    )
                    _describe 'command' commands
                    ;;
                args)
                    case \$line[1] in
        {$optionsCases}
                    esac
                    ;;
            esac
        }
        compdef _{$program} {$program}
        ZSH . "\n";
    }

    private function generateFish(string $program): string
    {
        $lines = [];

        foreach ($this->application->registry->all() as $descriptor) {
            $name    = $descriptor->name; // allow-listed by CommandDescriptor: inert in shell code
            $lines[] = "complete -c {$program} -f -n '__fish_use_subcommand' -a {$name} -d " . self::fishQuote(self::oneLine($descriptor->description));

            // per-command option completion
            $definition = $this->application->buildDefinitionFor($descriptor->name);

            if ($definition === null) {
                continue;
            }

            foreach ($definition->options as $option) {
                $lines[] = "complete -c {$program} -n '__fish_seen_subcommand_from {$name}' -l {$option->name} -d "
                    . self::fishQuote(self::oneLine($option->description));
            }

            foreach ($definition->arguments as $argument) {
                // oneLine(): a newline here would end the comment and start code.
                $lines[] = "# arg: {$argument->name} — " . self::oneLine($argument->description);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    // ── Per-command option introspection ─────────────────────────────────

    private function buildBashOptionsCases(): string
    {
        $cases = [];

        foreach ($this->application->registry->all() as $descriptor) {
            $definition = $this->application->buildDefinitionFor($descriptor->name);

            if ($definition === null) {
                continue;
            }

            $opts = array_map(
                fn($opt) => "--{$opt->name}",
                $definition->options,
            );

            if ($opts === []) {
                continue;
            }

            $optStr   = implode(' ', $opts);
            $cmdName  = $descriptor->name;
            $cases[] = "        {$cmdName}) COMPREPLY=(\$(compgen -W \"{$optStr}\" -- \"\$cur\"));;";
        }

        return implode("\n", $cases);
    }

    private function buildZshOptionsCases(): string
    {
        $cases = [];

        foreach ($this->application->registry->all() as $descriptor) {
            $definition = $this->application->buildDefinitionFor($descriptor->name);

            if ($definition === null) {
                continue;
            }

            $specs = [];

            foreach ($definition->options as $option) {
                $text    = self::zshSpecText(self::oneLine($option->description));
                $specs[] = self::posixQuote($option->isFlag()
                    ? "--{$option->name}[{$text}]"
                    : "--{$option->name}=[{$text}]:value:( )");
            }

            if ($specs === []) {
                continue;
            }

            $cmdName   = $descriptor->name;
            $specsStr  = implode(' ', $specs);
            $cases[]   = "                    {$cmdName}) _arguments {$specsStr};;";
        }

        return implode("\n", $cases);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function commandList(): string
    {
        return implode(' ', array_map(
            fn($d) => $d->name,
            $this->application->registry->all(),
        ));
    }

    private function commandEntriesForZsh(): string
    {
        $entries = [];

        foreach ($this->application->registry->all() as $descriptor) {
            // _describe splits on the first unescaped colon: `cache:warm` needs `cache\:warm`.
            $entry     = str_replace(':', replace: '\\:', subject: $descriptor->name) . ':' . self::oneLine($descriptor->description);
            $entries[] = '                    ' . self::posixQuote($entry);
        }

        return implode("\n", $entries);
    }

    // ── Quoting (per shell: one escaping scheme does not fit all) ────────

    /** bash/zsh single quotes: nothing is special inside; `'` becomes `'\''`. */
    private static function posixQuote(string $value): string
    {
        return "'" . str_replace("'", replace: "'\\''", subject: $value) . "'";
    }

    /**
     * fish single quotes DO process `\\` and `\'` (fish manual, "Quotes"),
     * so a trailing backslash would escape the closing quote; escape both.
     */
    private static function fishQuote(string $value): string
    {
        return "'" . str_replace(['\\', "'"], replace: ['\\\\', "\\'"], subject: $value) . "'";
    }

    /** zsh _arguments explanation text: brackets, colons and backslashes are syntax. */
    private static function zshSpecText(string $value): string
    {
        return strtr($value, ['\\' => '\\\\', '[' => '\\[', ']' => '\\]', ':' => '\\:']);
    }

    /** Collapses newlines and runs of whitespace: scripts are line-oriented. */
    private static function oneLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', replacement: ' ', subject: $value));
    }
}
