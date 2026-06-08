<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withCache(__DIR__ . '/var/cache/rector')
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/migrations',
    ])
    ->withRules([
        SortCallLikeNamedArgsRector::class,
    ]);
