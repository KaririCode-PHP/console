<?php

declare(strict_types=1);

namespace KaririCode\Console\Attribute;

use Attribute;

/**
 * Marks a final readonly class as an Input DTO target for InputMapper.
 *
 * Apply to the DTO class, then use #[Argument] and #[Option] on its
 * constructor parameters. InputMapper resolves the constructor via
 * reflection and injects parsed input values without mutating state.
 *
 * Values reach the DTO through its constructor in a single call, so the
 * DTO can be a final readonly class (a DPO, Spec v4.3.3 §5.1). Declare the
 * matching arguments/options by calling InputMapper::registerInto() from
 * the command's configure() — nothing is registered automatically.
 *
 * Example:
 *
 *   #[MapInput]
 *   final readonly class DeployInput
 *   {
 *       public function __construct(
 *           #[Argument('environment')]
 *           public string $environment,
 *
 *           #[Option('force', acceptsValue: false)]
 *           public bool $force = false,
 *
 *           #[Option('tag', default: 'latest')]
 *           public string $tag = 'latest',
 *       ) {}
 *   }
 *
 * @package KaririCode\Console\Attribute
 * @author  Walmir Silva <walmir.silva@kariricode.org>
 * @license MIT
 * @since   4.1.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class MapInput {}
