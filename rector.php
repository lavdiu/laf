<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

/**
 * Rector is used as a dry-run advisor only (`vendor/bin/rector process --dry-run`): the framework's
 * composer floor is PHP 8.0, so only the 8.0 language set is enabled, and the suggestions are applied by
 * hand, review by review. PhpGrid / SimpleTable are left out until they are reviewed; declare(strict_types=1)
 * is only added to new files (existing files keep coercive mode for the apps' callers).
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/src/Laf/UI/Grid',
        __DIR__ . '/tests/Fixtures',
        SafeDeclareStrictTypesRector::class,
    ])
    ->withPhpSets(php80: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withCache(__DIR__ . '/var/rector');
