<?php

declare(strict_types=1);

namespace KaririCode\Console\Core;

use KaririCode\Console\Contract\Command;
use KaririCode\Console\Contract\CommandContext;
use KaririCode\Console\Contract\CommandMiddleware;
use KaririCode\Console\Contract\Input;
use KaririCode\Console\Contract\InputDefinition;
use KaririCode\Console\Contract\Output;
use KaririCode\Console\Enum\ExitCode;
use KaririCode\Console\Enum\Verbosity;
use KaririCode\Console\Exception\ConsoleException;
use KaririCode\Console\Exception\InvalidArgumentException;
use KaririCode\Console\Exception\InvalidOptionException;
use KaririCode\Console\Formatter\AnsiFormatter;
use KaririCode\Console\Input\ArgvInput;
use KaririCode\Console\ValueObject\CommandDescriptor;
use KaririCode\Console\ValueObject\CommandResult;
use KaririCode\Console\ValueObject\GlobalOptions;
use KaririCode\Console\ValueObject\OptionDescriptor;
use KaririCode\Console\ValueObject\TraceId;

/**
 * Console application entry point.
 *
 * Dispatches an Input to the matching command through a MiddlewarePipeline,
 * then returns an immutable CommandResult. All I/O is delegated to the
 * provided Input and Output so the Application is testable without a real
 * terminal (ARFA 1.3 P4 — protocol agnosticism).
 *
 * Dispatch sequence (SPEC-001 §3):
 *   1. Resolve CommandDescriptor from CommandRegistry
 *   2. Instantiate command via descriptor factory or new $className()
 *   3. Build ConcreteInputDefinition via configure()
 *   4. Parse Input against the definition
 *   5. Build ConcreteCommandContext (immutable)
 *   6. Run context through MiddlewarePipeline → terminal executor
 *   7. Catch ConsoleException; propagate unknown exceptions
 *
 * Side effects: run() and dispatch() execute commands and write to the output
 *               and its error output.
 *
 * @package KaririCode\Console\Core
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class Application
{
    /** Long options owned by dispatch(); commands may not declare them. */
    private const array RESERVED_OPTIONS = ['help', 'quiet', 'verbose', 'version', 'no-interaction'];

    /** Shortcuts owned by dispatch(); commands may not declare them. */
    private const array RESERVED_SHORTCUTS = ['h', 'q', 'v', 'V', 'n'];

    private MiddlewarePipeline $pipeline;

    public function __construct(
        public readonly CommandRegistry $registry,
        public readonly string $name = 'KaririCode Console',
        public readonly string $version = 'UNKNOWN',
    ) {
        $this->pipeline = new MiddlewarePipeline();
    }

    // ── Configuration ────────────────────────────────────────────────────

    /** Appends a middleware; the first added is the outermost layer. */
    public function addMiddleware(CommandMiddleware $middleware): self
    {
        $this->pipeline = $this->pipeline->pipe($middleware);

        return $this;
    }

    /**
     * Registers a pre-built command (constructor injection, tests). Its
     * #[AsCommand] aliases and hidden flag are honoured, as for loaders.
     */
    public function add(Command $command): self
    {
        $this->registry->registerCommand($command);

        return $this;
    }

    // ── Entry points ─────────────────────────────────────────────────────

    /**
     * Resolves the command and global flags from raw tokens, then runs it.
     * Returns the process exit status for the bin/ script's exit().
     *
     * Grammar (global flags may appear anywhere before `--`):
     *
     *   app [global-flags] [command] [command-options/arguments]
     *
     *   -h, --help             help for the command (or the application)
     *   -V, --version          print "name version" and exit
     *   -q, --quiet            Verbosity::Quiet
     *   -v | -vv | -vvv        Verbose | VeryVerbose | Debug (also --verbose)
     *   -n, --no-interaction   prompts return their defaults
     *   --                     end of global flags (POSIX Guideline 10): later
     *                          tokens reach the command verbatim, and the
     *                          terminator itself is forwarded so the command's
     *                          parser treats them as operands too
     *
     * The first non-flag token names the command (default: "list").
     */
    public function dispatch(Input $input, Output $output): int
    {
        $options = GlobalOptions::parse($input->tokens, $input->interactive);

        if ($options->verbosity !== null) {
            $output->verbosity = $options->verbosity;
        }

        if ($options->wantsVersion) {
            $output->writeln(AnsiFormatter::escape($this->name) . ' <info>' . AnsiFormatter::escape($this->version) . '</info>');

            return ExitCode::Success->exitCode();
        }

        if ($options->wantsHelp) {
            $helpInput = new ArgvInput($options->commandName === null ? [] : [$options->commandName], interactive: $options->interactive);

            return $this->run(commandName: 'help', input: $helpInput, output: $output)->toExitInt();
        }

        $commandInput = new ArgvInput($options->passthrough, interactive: $options->interactive);

        return $this->run(commandName: $options->commandName ?? 'list', input: $commandInput, output: $output)->toExitInt();
    }

    /**
     * Runs a command through the middleware pipeline and never throws:
     * every failure becomes a CommandResult and a message on the error
     * output (stderr under ConsoleOutput).
     *
     * Exit semantics: invalid usage (unknown option, missing or surplus
     * argument) → ExitCode::Invalid (2, the GNU/bash usage-error status);
     * anything else that fails → ExitCode::Failure (1).
     */
    public function run(string $commandName, Input $input, Output $output): CommandResult
    {
        $startNs = hrtime(as_number: true);

        try {
            $command = $this->instantiate($this->registry->get($commandName));
            $input->parse($this->defineInputFor($command));

            return $this->pipeline->run(
                new ConcreteCommandContext(
                    commandName: $commandName,
                    input: $input,
                    output: $output,
                    command: $command,
                    traceId: TraceId::generate(),
                ),
                $this->terminal($startNs),
            );
        } catch (InvalidArgumentException|InvalidOptionException $e) {
            $this->reportError($e, $output);

            return CommandResult::invalid(commandName: $commandName, reason: $e->getMessage(), durationMs: $this->elapsedMs($startNs));
        } catch (ConsoleException $e) {
            $this->reportError($e, $output);

            return CommandResult::failure(commandName: $commandName, errorMessage: $e->getMessage(), durationMs: $this->elapsedMs($startNs));
        } catch (\Throwable $e) {
            // Foreign throwables become 9002 so the CLI exits 1 instead of a
            // PHP fatal; the original stays reachable via getPrevious().
            $wrapped = ConsoleException::executionFailed(name: $commandName, reason: $e->getMessage(), previous: $e);
            $this->reportError($wrapped, $output);

            return CommandResult::failure(commandName: $commandName, errorMessage: $wrapped->getMessage(), durationMs: $this->elapsedMs($startNs));
        }
    }

    /**
     * Instantiates a command and returns its populated definition, without
     * executing it. Used by help and completion; null when the command
     * cannot be built or is misconfigured, so callers degrade gracefully.
     */
    public function buildDefinitionFor(string $commandName): ?InputDefinition
    {
        try {
            return $this->defineInputFor($this->instantiate($this->registry->get($commandName)));
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Internals ────────────────────────────────────────────────────────

    /** @throws ConsoleException 9004 when the command claims a global option */
    private function defineInputFor(Command $command): InputDefinition
    {
        $definition = new ConcreteInputDefinition();
        $command->configure($definition);

        /** @var OptionDescriptor|null $collision */

        $collision = array_find(
            $definition->options,
            static fn(OptionDescriptor $option): bool => in_array($option->name, haystack: self::RESERVED_OPTIONS, strict: true)
                || ($option->shortcut !== null && in_array($option->shortcut, haystack: self::RESERVED_SHORTCUTS, strict: true)),
        );

        if ($collision !== null) {
            throw ConsoleException::invalidOption($collision->name, 'collides with a global option (--help, --quiet, --verbose, --version, --no-interaction, -h, -q, -v, -V, -n)');
        }

        return $definition;
    }

    /**
     * Error summary always; origin at -v; stack trace at -vvv. All text is
     * escaped: exception messages routinely echo user input.
     */
    private function reportError(ConsoleException $error, Output $output): void
    {
        $errors = $output->errorOutput;
        $origin = $error->getPrevious() ?? $error;

        $errors->writeln('<e>' . AnsiFormatter::escape($error->getMessage()) . '</e>');
        $errors->writeln(
            AnsiFormatter::escape(sprintf('%s at %s:%d', $origin::class, $origin->getFile(), $origin->getLine())),
            Verbosity::Verbose,
        );
        $errors->writeln(AnsiFormatter::escape($origin->getTraceAsString()), Verbosity::Debug);
    }

    /**
     * Innermost pipeline callable: executes the command.
     *
     * @param int $startNs hrtime(true) captured at run() entry
     * @return \Closure(CommandContext): CommandResult
     */
    private function terminal(int $startNs): \Closure
    {
        return fn(CommandContext $context): CommandResult => new CommandResult(
            exitCode: $context->command->execute($context->input, $context->output),
            commandName: $context->commandName,
            durationMs: $this->elapsedMs($startNs),
        );
    }

    /**
     * Uses the descriptor's factory when present (built-ins needing the
     * Application, pre-built instances), else `new $className()`.
     */
    private function instantiate(CommandDescriptor $descriptor): Command
    {
        if ($descriptor->factory !== null) {
            return ($descriptor->factory)();
        }

        $className = $descriptor->className;

        // The command *is* registered; what is broken is its class, so report
        // it against the command name the user typed (9002, exit 1).
        if (!class_exists($className)) {
            throw ConsoleException::executionFailed($descriptor->name, "class {$className} does not exist");
        }

        if (!is_a($className, class: Command::class, allow_string: true)) {
            throw ConsoleException::executionFailed($descriptor->name, "{$className} does not implement " . Command::class);
        }

        return new $className();
    }

    private function elapsedMs(int $startNs): float
    {
        return (hrtime(as_number: true) - $startNs) / 1_000_000;
    }
}
