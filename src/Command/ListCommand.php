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
use KaririCode\Console\Enum\OutputFormat;
use KaririCode\Console\Formatter\AnsiFormatter;
use KaririCode\Console\Formatter\TableFormatter;
use KaririCode\Console\ValueObject\CommandDescriptor;
use KaririCode\Console\ValueObject\OptionDescriptor;

/**
 * Lists all registered, non-hidden commands.
 *
 * Supports table (default), JSON, and plain text output formats via
 * --format. Filtering by namespace prefix is available via --filter.
 *
 *   $application->dispatch(new ArgvInput(['list', '--format=json']), $output);
 *
 * Side effects: writes to the output.
 *
 * @package KaririCode\Console\Command
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
#[AsCommand('list', description: 'List all available commands', aliases: ['commands'])]
final class ListCommand implements Command
{
    public string $name {
        get => 'list';
    }

    public string $description {
        get => 'List all available commands';
    }

    public function __construct(private readonly Application $application) {}

    // ── Command lifecycle ────────────────────────────────────────────────

    #[\Override]
    public function configure(InputDefinition $definition): void
    {
        $definition->addOption(new OptionDescriptor(name: 'format', description: 'Output format: table, json, plain', default: 'table'));
        $definition->addOption(new OptionDescriptor('filter', 'Only commands whose name starts with this prefix'));
        $definition->addOption(new OptionDescriptor(name: 'all', description: 'Include hidden commands', acceptsValue: false));
    }

    #[\Override]
    public function execute(Input $input, Output $output): ExitCode
    {
        /** @var mixed $formatName Raw CLI value (Input returns mixed by contract); narrowed below. */
        $formatName = $input->getOption('format', 'table');
        $formatName = is_string($formatName) ? $formatName : 'table';
        $format     = OutputFormat::parse($formatName);

        if ($format === null) {
            $output->errorOutput->writeln('<e>' . AnsiFormatter::escape(
                "Unsupported format \"{$formatName}\". Supported: table, json, plain.",
            ) . '</e>');

            return ExitCode::Invalid;
        }

        $descriptors = $this->application->registry->all(includeHidden: (bool) $input->getOption('all', default: false));
        /** @var mixed $prefix Raw CLI value (Input returns mixed by contract); narrowed below. */
        $prefix      = $input->getOption('filter');

        if (is_string($prefix)) {
            $descriptors = array_values(array_filter(
                $descriptors,
                static fn(CommandDescriptor $d): bool => str_starts_with($d->name, $prefix),
            ));
        }

        match ($format) {
            OutputFormat::Table => $this->renderTable($descriptors, $output),
            OutputFormat::Json  => $this->renderJson($descriptors, $output),
            OutputFormat::Plain => $this->renderPlain($descriptors, $output),
        };

        return ExitCode::Success;
    }

    // ── Renderers ────────────────────────────────────────────────────────

    /** @param list<CommandDescriptor> $descriptors */
    private function renderTable(array $descriptors, Output $output): void
    {
        $table = new TableFormatter($output);
        $table->setHeaders(['Command', 'Description']);

        foreach ($descriptors as $descriptor) {
            $table->addRow([$descriptor->name, $descriptor->description]);
        }

        $table->render();
    }

    /**
     * Machine-readable output: escaped so the console formatter can never
     * rewrite a `<tag>` inside a description into ANSI bytes in the JSON.
     *
     * @param list<CommandDescriptor> $descriptors
     */
    private function renderJson(array $descriptors, Output $output): void
    {
        $payload = array_map(
            static fn(CommandDescriptor $d): array => [
                'name'        => $d->name,
                'description' => $d->description,
                'aliases'     => $d->aliases,
                'hidden'      => $d->hidden,
            ],
            $descriptors,
        );

        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );

        $output->writeln(AnsiFormatter::escape($json));
    }

    /** @param list<CommandDescriptor> $descriptors */
    private function renderPlain(array $descriptors, Output $output): void
    {
        foreach ($descriptors as $descriptor) {
            $output->writeln("{$descriptor->name}  {$descriptor->description}");
        }
    }
}
