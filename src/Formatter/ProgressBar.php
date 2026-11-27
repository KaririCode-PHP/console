<?php

declare(strict_types=1);

namespace KaririCode\Console\Formatter;

use KaririCode\Console\Contract\Output;

/**
 * Single-line progress indicator: a bar when the total is known, a spinner
 * with a counter when it is not.
 *
 * On a decorated output (a terminal) the line is redrawn in place with
 * `\r`. On an undecorated one (pipe, file, CI log) redrawing in place would
 * pack every update into one physical line, so instead a full line is
 * written each time the percentage crosses a new 10% step (determinate) or
 * only on finish() (indeterminate).
 *
 * Stateful by design (it tracks position): a service, not a Value Object.
 *
 *   $bar = new ProgressBar($output, total: count($jobs));
 *   foreach ($jobs as $job) {
 *       $job->run();
 *       $bar->advance();
 *   }
 *   $bar->finish();
 *
 * Side effects: writes to the output on every redraw.
 *
 * @package KaririCode\Console\Formatter
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class ProgressBar
{
    /** Spinner frames for indeterminate mode. */
    private const array SPINNER = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    /** Percentage step between lines on undecorated outputs. */
    private const int LOG_STEP_PERCENT = 10;

    /** Units completed so far, clamped to [0, total] when the total is known. */
    public private(set) int $current = 0;

    private int $spinnerIndex = 0;

    private bool $finished = false;

    /** Last 10% step written on an undecorated output; -1 before the first. */
    private int $lastLoggedStep = -1;

    /**
     * @param int $total    Units of work; 0 means unknown (spinner mode).
     * @param int $barWidth Bar length in columns.
     *
     * @throws \InvalidArgumentException when $total < 0 or $barWidth < 1
     */
    public function __construct(
        private readonly Output $output,
        public readonly int $total = 0,
        private readonly int $barWidth = 40,
    ) {
        if ($total < 0 || $barWidth < 1) {
            throw new \InvalidArgumentException("ProgressBar needs total >= 0 and barWidth >= 1, got {$total} and {$barWidth}.");
        }
    }

    // ── Lifecycle ────────────────────────────────────────────────────────

    public function start(): void
    {
        $this->render();
    }

    /** Negative steps are clamped at 0; progress never exceeds the total. */
    public function advance(int $step = 1): void
    {
        if ($this->finished) {
            return;
        }

        $this->current = max(0, $this->current + $step);

        if ($this->total > 0) {
            $this->current = min($this->current, $this->total);
        }

        $this->render();
    }

    /** Completes the bar and ends the line; further calls are no-ops. */
    public function finish(): void
    {
        if ($this->finished) {
            return;
        }

        if ($this->total > 0) {
            $this->current = $this->total;
        }

        $this->finished = true;

        if ($this->output->decorated) {
            $this->output->write("\r" . $this->line());
            $this->output->newLine();

            return;
        }

        if ($this->total === 0 || $this->lastLoggedStep < 100 / self::LOG_STEP_PERCENT) {
            $this->output->writeln($this->line());
        }
    }

    // ── Rendering ────────────────────────────────────────────────────────

    private function render(): void
    {
        if ($this->output->decorated) {
            $this->output->write("\r" . $this->line());
            $this->spinnerIndex = ($this->spinnerIndex + 1) % count(self::SPINNER);

            return;
        }

        if ($this->total === 0) {
            return; // indeterminate on a log: report once, on finish()
        }

        $step = intdiv($this->current * 100, $this->total * self::LOG_STEP_PERCENT);

        if ($step > $this->lastLoggedStep) {
            $this->lastLoggedStep = $step;
            $this->output->writeln($this->line());
        }
    }

    private function line(): string
    {
        if ($this->total === 0) {
            $frame = $this->finished ? '✓' : self::SPINNER[$this->spinnerIndex];

            return "{$frame} {$this->current} items processed";
        }

        $filled = intdiv($this->barWidth * $this->current, $this->total);
        $bar    = str_repeat('█', $filled) . str_repeat('░', $this->barWidth - $filled);
        $pct    = str_pad((string) intdiv($this->current * 100, $this->total), length: 3, pad_string: ' ', pad_type: STR_PAD_LEFT);

        return "[{$bar}] {$pct}% {$this->current}/{$this->total}";
    }
}
