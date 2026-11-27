<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Unit;

use KaririCode\Console\Command\CompletionCommand;
use KaririCode\Console\Command\HelpCommand;
use KaririCode\Console\Command\ListCommand;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Core\Application;
use KaririCode\Console\Core\ConcreteCommandContext;
use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Formatter\ProgressBar;
use KaririCode\Console\Formatter\TableFormatter;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\Input\ArrayInput;
use KaririCode\Console\Integration\ObservabilityBridge;
use KaririCode\Console\Middleware\LoggingMiddleware;
use KaririCode\Console\Middleware\ObservabilityMiddleware;
use KaririCode\Console\Output\BufferedOutput;
use KaririCode\Console\Output\NullOutput;
use KaririCode\Console\Prompt\Prompter;
use KaririCode\Console\Provider\ConsoleServiceProvider;
use KaririCode\Console\Tests\Fixture\AliasedHiddenCommand;
use KaririCode\Console\Tests\Fixture\RecordingLogger;
use KaririCode\Console\Tests\Fixture\StubCommand;
use KaririCode\Console\ValueObject\ArgumentDescriptor;
use KaririCode\Console\ValueObject\CommandResult;
use KaririCode\Console\ValueObject\CommandSpan;
use KaririCode\Console\ValueObject\OptionDescriptor;
use KaririCode\Console\ValueObject\TraceId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exact output of every renderer, compared with fixtures in
 * tests/Fixture/golden. The fixtures freeze behaviour verified elsewhere
 * (completion scripts in real shells, layouts by inspection); fragment
 * assertions let dropped concatenations and line breaks slip through
 * (mutation testing showed it). Regenerate deliberately: UPDATE_GOLDEN=1.
 */
#[CoversClass(HelpCommand::class)]
#[CoversClass(ListCommand::class)]
#[CoversClass(CompletionCommand::class)]
#[CoversClass(TableFormatter::class)]
#[CoversClass(ProgressBar::class)]
#[CoversClass(Prompter::class)]
#[CoversClass(LoggingMiddleware::class)]
#[CoversClass(Verbosity::class)]
#[CoversClass(ObservabilityMiddleware::class)]
final class GoldenOutputTest extends TestCase
{
    private const string GOLDEN = __DIR__ . '/../Fixture/golden';

    /** @return iterable<string, array{list<string>}> */
    public static function commandLines(): iterable
    {
        yield 'help-command'    => [['help', 'deploy']];
        yield 'help-aliases'    => [['help', 'cache:warm']];
        yield 'help-app'        => [['help']];
        yield 'list-table'      => [['list']];
        yield 'list-plain'      => [['list', '--format=plain', '--all']];
        yield 'list-json'       => [['list', '--format=json']];
        yield 'completion-bash' => [['completion', 'bash', '--program', 'app']];
        yield 'completion-zsh'  => [['completion', 'zsh', '--program', 'app']];
        yield 'completion-fish' => [['completion', 'fish', '--program', 'app']];
        yield 'version'         => [['-V']];
    }

    /** @param list<string> $tokens */
    #[DataProvider('commandLines')]
    public function testBuiltInCommandOutput(array $tokens): void
    {
        $output = new BufferedOutput();

        self::assertSame(0, self::app()->dispatch(new ArgvInput($tokens), $output));
        self::assertGolden((string) $this->dataName(), $output->buffer);
    }

    public function testTableLayout(): void
    {
        $output = new BufferedOutput();
        (new TableFormatter($output))
            ->setHeaders(['Name', 'Notes'])
            ->addRow(['東京', 'wide <b>glyphs</b>'])
            ->addRow(['x', '<file>'])
            ->addRow([42, null])
            ->render();

        self::assertGolden('table', $output->buffer);
    }

    public function testProgressBarOnTerminalAndLog(): void
    {
        $terminal = new BufferedOutput(decorated: true);
        $bar      = new ProgressBar($terminal, total: 3, barWidth: 6);
        $bar->start();
        $bar->advance();
        $bar->advance(2);
        $bar->finish();

        $log     = new BufferedOutput();
        $bar     = new ProgressBar($log, total: 20, barWidth: 5);
        $spinner = new ProgressBar($log);
        $bar->start();
        for ($i = 0; $i < 20; $i++) {
            $bar->advance();
        }
        $bar->finish();
        $spinner->advance(3);
        $spinner->finish();

        self::assertGolden('progress-terminal', $terminal->buffer);
        self::assertGolden('progress-log', $log->buffer);
    }

    public function testPromptTranscripts(): void
    {
        $output   = new BufferedOutput();
        $prompter = new Prompter(new ArrayInput(interactive: true), $output, self::answers("\n7\n0\n0, 2\ny\nn\nhunter2\n"));

        $prompter->text('Project', placeholder: 'kebab-case', default: 'demo', suggestions: ['api', 'cli', 'web']);
        $prompter->select('Database', ['MySQL', 'PostgreSQL'], 1);
        $prompter->multiselect('Extras', ['Redis', 'Queue', 'Mail'], [1]);
        $prompter->confirm('Proceed?');
        $prompter->confirm('Overwrite?', default: false);
        $prompter->password('Token');

        self::assertGolden('prompts', $output->buffer);
    }

    public function testLoggingRecords(): void
    {
        $logger     = new RecordingLogger();
        $middleware = new LoggingMiddleware($logger);
        $context    = new ConcreteCommandContext(commandName: 'deploy', input: new ArrayInput(), output: new NullOutput(), command: new StubCommand('deploy'), traceId: TraceId::generate());

        $middleware->handle($context, static fn(): CommandResult => CommandResult::failure('deploy', 'boom', 12.5));
        $middleware->handle($context, static fn(): CommandResult => CommandResult::success('deploy', 1.0));

        try {
            $middleware->handle($context, static fn(): CommandResult => throw new \RuntimeException('kaput'));
        } catch (\RuntimeException) {
        }

        // Fixture independent of the random trace id; the throwable is kept by identity.
        $records = array_map(static function (array $record) use ($context): array {
            self::assertSame($context->traceId->value, $record[2]['trace_id'] ?? null);
            $record[2]['trace_id'] = '<trace-id>';

            if (isset($record[2]['exception'])) {
                self::assertInstanceOf(\RuntimeException::class, $record[2]['exception']);
                $record[2]['exception'] = '<RuntimeException>';
            }

            return $record;
        }, $logger->records);

        self::assertGolden('logging-records', json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }

    /** level() is public API: pin the values, not only their order. */
    public function testVerbosityLevels(): void
    {
        self::assertSame([0, 1, 2, 3, 4], array_map(static fn(Verbosity $v): int => $v->level(), Verbosity::cases()));
    }

    /** Spans are stamped in Unix nanoseconds (OTel), not micro- or milliseconds. */
    public function testSpanTimestampsAreUnixNanoseconds(): void
    {
        $spans      = [];
        $middleware = new ObservabilityMiddleware(new ObservabilityBridge(spanExporter: static function (CommandSpan $span) use (&$spans): void {
            $spans[] = $span;
        }));

        $before = (int) (microtime(true) * 1e9);
        $middleware->handle(
            new ConcreteCommandContext(commandName: 'x', input: new ArrayInput(), output: new NullOutput(), command: new StubCommand('x'), traceId: TraceId::generate()),
            static fn(): CommandResult => CommandResult::success('x'),
        );
        $after = (int) (microtime(true) * 1e9);

        self::assertCount(1, $spans);
        self::assertGreaterThanOrEqual($before - 1_000_000, $spans[0]->startUnixNano);
        self::assertLessThanOrEqual($after + 1_000_000, $spans[0]->endUnixNano);
    }

    private static function assertGolden(string $name, string $actual): void
    {
        $file = self::GOLDEN . "/{$name}.txt";

        if (getenv('UPDATE_GOLDEN') === '1') {
            file_put_contents($file, $actual);
        }

        self::assertFileExists($file, 'run once with UPDATE_GOLDEN=1 to create the fixture');
        self::assertSame((string) file_get_contents($file), $actual, "golden output '{$name}' changed");
    }

    /** @return resource */
    private static function answers(string $text)
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $text);
        rewind($stream);

        return $stream;
    }

    private static function app(): Application
    {
        return ConsoleServiceProvider::buildApplication('demo', '1.0')
            ->add(new StubCommand('deploy', 'Deploys the application', static function (InputDefinition $d): void {
                $d->addArgument(new ArgumentDescriptor('env', 'Target environment'));
                $d->addArgument(new ArgumentDescriptor('regions', 'Regions', required: false, default: ['eu'], isArray: true));
                $d->addOption(new OptionDescriptor('tag', 'Release tags', shortcut: 't', isArray: true));
                $d->addOption(new OptionDescriptor('force', 'Skip checks', acceptsValue: false));
                $d->addOption(new OptionDescriptor('retries', 'Attempts', default: 3));
            }))
            ->add(new AliasedHiddenCommand());
    }
}
