<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Fixture\Loader;

use KaririCode\Console\Attribute\AsCommand;

#[AsCommand('not:a-command')]
final class AnnotatedButNotACommand {}
