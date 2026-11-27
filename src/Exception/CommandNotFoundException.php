<?php

declare(strict_types=1);

namespace KaririCode\Console\Exception;

/**
 * Raised when a command name (or alias) has no registration (9001).
 *
 * Application maps this type to its exit status; ADR-003 lists the codes.
 *
 * @package KaririCode\Console\Exception
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class CommandNotFoundException extends ConsoleException {}
