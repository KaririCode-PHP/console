<?php

declare(strict_types=1);

// Spec v4.3.3 §29.1 "composer cs-check": PER Coding Style clean.
return (new PhpCsFixer\Config())
    ->setRules(['@PER-CS' => true])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__ . '/src', __DIR__ . '/tests'])
            ->append([__FILE__]),
    );
