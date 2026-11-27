<?php

declare(strict_types=1);

namespace KaririCode\Console\Enum;

/**
 * POSIX signals relevant to long-running console commands.
 *
 * Pure enum — integer values are resolved via pcntlConstant() to
 * insulate call sites from the PCNTL extension requirement
 * (pure by default, Spec v4.3.3 §10; E-003 allows backing only for an external contract).
 *
 * When the PCNTL extension is absent (e.g. Windows), pcntlConstant()
 * returns 0 so callers can guard with isSupported().
 *
 * @package KaririCode\Console\Enum
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
enum Signal
{
    /** Interrupt from keyboard (Ctrl+C). */
    case Interrupt;

    /** Termination request from OS or process manager. */
    case Terminate;

    /** Hangup — terminal closed. */
    case Hangup;

    // ── Accessors ────────────────────────────────────────────────────────

    public function pcntlConstant(): int
    {
        if (!self::isSupported()) {
            return 0;
        }

        return match ($this) {
            self::Interrupt  => SIGINT,
            self::Terminate  => SIGTERM,
            self::Hangup     => SIGHUP,
        };
    }

    public static function isSupported(): bool
    {
        // function_exists, not extension_loaded: hosts often keep the
        // extension but list its functions in disable_functions.
        return \function_exists('pcntl_signal')
            && \function_exists('pcntl_async_signals')
            && \function_exists('pcntl_signal_get_handler');
    }

    public static function fromPcntlInt(int $signo): ?self
    {
        if (!self::isSupported()) {
            return null;
        }

        return match ($signo) {
            SIGINT  => self::Interrupt,
            SIGTERM => self::Terminate,
            SIGHUP  => self::Hangup,
            default => null,
        };
    }

    /** Human-readable name for logging. */
    public function label(): string
    {
        return match ($this) {
            self::Interrupt  => 'SIGINT',
            self::Terminate  => 'SIGTERM',
            self::Hangup     => 'SIGHUP',
        };
    }
}
