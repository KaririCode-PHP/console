<?php

declare(strict_types=1);

namespace KaririCode\Console\Exception;

use KaririCode\Contract\Exception\Configuration;

/**
 * Raised when a command loader cannot read or register the commands under a path (9006): a
 * configuration problem the operator fixes, so the edge answers it as one.
 *
 * Built only through ConsoleException's factory; ADR-003 lists the codes,
 * ADR-014 the families.
 *
 * @package KaririCode\Console\Exception
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.6.0
 */
final class CommandLoaderException extends ConsoleException implements Configuration {}
