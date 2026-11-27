<?php

declare(strict_types=1);

namespace KaririCode\Console\Exception;

/**
 * Raised for unknown options, missing values or invalid option declarations (9004).
 *
 * Application maps this type to its exit status; ADR-003 lists the codes.
 *
 * @package KaririCode\Console\Exception
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class InvalidOptionException extends ConsoleException {}
