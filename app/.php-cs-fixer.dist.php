<?php

declare(strict_types=1);

// Only existing directories: Finder fails on missing ones.
$dirs = array_filter(
    [__DIR__ . '/src', __DIR__ . '/public', __DIR__ . '/tests', __DIR__ . '/bin'],
    'is_dir',
);

$finder = PhpCsFixer\Finder::create()
    ->in($dirs)
    ->exclude(['assets', 'uploads'])
    ->append([__FILE__]);

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS' => true,
        '@PHP84Migration' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
    ])
    ->setFinder($finder);
