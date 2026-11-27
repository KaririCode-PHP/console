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
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Formatter\AnsiFormatter;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\CommandDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Displays usage information for the application or a specific command.
 *
 * Without an argument: application name, global options and the visible
 * commands. With a command name: its description, usage synopsis,
 * arguments, options (with defaults) and aliases.
 *
 *   $application->dispatch(new ArgvInput(['help', 'deploy']), $output);
 *
 * Side effects: writes to the output.
 *
 * @package KaririCode\Console\Command
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
#[AsCommand('help', description: 'Display usage information for a command')]
final class HelpCommand implements Command
{
    /** Global options handled by Application::dispatch(), shown in help. */
    private const array GLOBAL_OPTIONS = [
        ['-h, --help', 'Display help for the given command (or the application)'],
        ['-q, --quiet', 'Do not output any message'],
        ['-v|vv|vvv, --verbose', 'Increase verbosity: 1 normal, 2 more, 3 debug (stack traces)'],
        ['-V, --version', 'Display the application version'],
        ['-n, --no-interaction', 'Do not ask interactive questions; prompts use their defaults'],
    ];

    public string $name {
        get => 'help';
    }

    public string $description {
        get => 'Display usage information for a command';
    }

    public function __construct(private readonly Application $application) {}

    // ── Command lifecycle ────────────────────────────────────────────────

    #[\Override]
    public function configure(InputDefinition $definition): void
    {
        $definition->addArgument(new ArgumentDescriptor(
            name: 'command',
            description: 'The command to describe',
            required: false,
        ));
    }

    #[\Override]
    public function execute(Input $input, Output $output): ExitCode
    {
        /** @var mixed $commandName Raw CLI value (Input returns mixed by contract); narrowed below. */
        $commandName = $input->getArgument('command');

        return is_string($commandName)
            ? $this->describeCommand($commandName, $output)
            : $this->describeApplication($output);
    }

    // ── Application help ─────────────────────────────────────────────────

    private function describeApplication(Output $output): ExitCode
    {
        $output->writeln(sprintf(
            '<info>%s</info> <comment>%s</comment>',
            AnsiFormatter::escape($this->application->name),
            AnsiFormatter::escape($this->application->version),
        ));
        $output->newLine();

        $output->writeln('<comment>Usage:</comment>');
        $output->writeln('  command [options] [--] [arguments]');
        $output->newLine();

        $output->writeln('<comment>Global options:</comment>');
        $this->writeTwoColumns($output, self::GLOBAL_OPTIONS);
        $output->newLine();

        $output->writeln('<comment>Available commands:</comment>');
        $this->writeTwoColumns($output, array_map(
            static fn(CommandDescriptor $d): array => [$d->name, $d->description],
            $this->application->registry->all(),
        ));

        return ExitCode::Success;
    }

    // ── Command help ─────────────────────────────────────────────────────

    private function describeCommand(string $name, Output $output): ExitCode
    {
        $registry = $this->application->registry;

        if (!$registry->has($name)) {
            $output->errorOutput->writeln('<e>' . AnsiFormatter::escape(ConsoleException::commandNotFound($name)->getMessage()) . '</e>');

            return ExitCode::Failure;
        }

        $descriptor = $registry->get($name);
        $definition = $this->application->buildDefinitionFor($name);

        $output->writeln('<comment>Description:</comment>');
        $output->writeln('  ' . $descriptor->description);
        $output->newLine();

        $output->writeln('<comment>Usage:</comment>');
        $output->writeln('  ' . AnsiFormatter::escape($this->synopsis($descriptor->name, $definition)));

        if ($definition !== null && $definition->arguments !== []) {
            $output->newLine();
            $output->writeln('<comment>Arguments:</comment>');
            $this->writeTwoColumns($output, array_map(
                fn(ArgumentDescriptor $a): array => [AnsiFormatter::escape($a->name), $a->description . $this->describeDefault($a->default)],
                $definition->arguments,
            ));
        }

        if ($definition !== null && $definition->options !== []) {
            $output->newLine();
            $output->writeln('<comment>Options:</comment>');
            $this->writeTwoColumns($output, array_map(
                fn(OptionDescriptor $o): array => [
                    AnsiFormatter::escape($this->optionLabel($o)),
                    $o->description . $this->describeDefault($o->default) . ($o->isArray ? ' (multiple values allowed)' : ''),
                ],
                $definition->options,
            ));
        }

        if ($descriptor->aliases !== []) {
            $output->newLine();
            $output->writeln('<comment>Aliases:</comment>');
            $output->writeln('  ' . AnsiFormatter::escape(implode(', ', $descriptor->aliases)));
        }

        return ExitCode::Success;
    }

    /** e.g. `greet [options] [--] <name> [<greeting>] [<tags>...]` */
    private function synopsis(string $name, ?InputDefinition $definition): string
    {
        if ($definition === null) {
            return $name;
        }

        $parts = [$name];

        if ($definition->options !== []) {
            $parts[] = '[options]';
        }

        if ($definition->arguments !== []) {
            $parts[] = '[--]';
        }

        foreach ($definition->arguments as $argument) {
            $token   = "<{$argument->name}>" . ($argument->isArray ? '...' : '');
            $parts[] = $argument->required ? $token : "[{$token}]";
        }

        return implode(' ', $parts);
    }

    private function optionLabel(OptionDescriptor $option): string
    {
        $label = ($option->shortcut !== null ? "-{$option->shortcut}, " : '    ') . "--{$option->name}";

        return $option->isFlag() ? $label : "{$label}=" . strtoupper($option->name);
    }

    private function describeDefault(mixed $default): string
    {
        if ($default === null || $default === false || $default === []) {
            return '';
        }

        $encoded = json_encode($default, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $encoded === false ? '' : ' [default: ' . AnsiFormatter::escape($encoded) . ']';
    }

    /**
     * Left column padded by display width, so CJK names still align.
     *
     * @param list<array{0: string, 1: string}> $rows Left column already escaped.
     */
    private function writeTwoColumns(Output $output, array $rows): void
    {
        $width = max(0, ...array_map(static fn(array $row): int => mb_strwidth(str_replace('\\<', replace: '<', subject: $row[0])), $rows));

        foreach ($rows as [$left, $right]) {
            $padding = str_repeat(' ', $width - mb_strwidth(str_replace('\\<', replace: '<', subject: $left)));
            $output->writeln("  <info>{$left}</info>{$padding}  {$right}");
        }
    }
}
