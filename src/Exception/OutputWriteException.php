<?php

declare(strict_types=1);

namespace KaririCode\Console\Exception;

use KaririCode\Contract\Exception\Runtime;

/**
 * Raised when the output stream refuses a write (9007): a closed pipe or a full disk, a
 * fault of the environment that the same call may survive later.
 *
 * Built only through ConsoleException's factory; ADR-003 lists the codes,
 * ADR-014 the families.
 *
 * @package KaririCode\Console\Exception
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.6.0
 */
final class OutputWriteException extends ConsoleException implements Runtime {}
