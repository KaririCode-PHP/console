<?php

declare(strict_types=1);

namespace KaririCode\Console\Exception;

/**
 * Raised when a command cannot be built or fails while running (9002).
 *
 * Application maps this type to its exit status; ADR-003 lists the codes.
 *
 * @package KaririCode\Console\Exception
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class CommandExecutionException extends ConsoleException {}
