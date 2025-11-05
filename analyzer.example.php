<?php

declare(strict_types=1);

/**
 * Analyzer Configuration
 *
 * This file is published to your Laravel project root.
 * Configure analyzer behavior for your application.
 *
 * Usage:
 *   php artisan analyzer:analyze
 *   php artisan analyzer:analyze --workers=auto    # Auto-detect CPU cores
 *   php artisan analyzer:analyze --workers=8       # Specify worker count
 *   php artisan analyzer:analyze --agent           # AI agent mode
 */

use Cline\Analyzer\Config\AnalyzerConfig;

return AnalyzerConfig::make()
    ->paths([
        'app',
        'tests',
    ])
    ->workers(0)  // 0 = auto-detect CPU cores, or specify a number (e.g., 4, 8)
    ->ignore([
        'Illuminate\\*',
        'Laravel\\*',
        'Symfony\\*',
    ]);
