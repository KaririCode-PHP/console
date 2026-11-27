<?php

declare(strict_types=1);

namespace KaririCode\Console\Exception;

use KaririCode\Contract\Exception\Validation;

/**
 * Raised for invalid, missing or surplus positional arguments (9003, 9005).
 *
 * Application maps this type to its exit status; ADR-003 lists the codes.
 *
 * @package KaririCode\Console\Exception
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.0.0
 */
final class InvalidArgumentException extends ConsoleException implements Validation {}
