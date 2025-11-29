<?php

declare(strict_types=1);

/**
 * Analyzer Configuration File
 *
 * This configuration file defines the default settings for the analyzer package,
 * which identifies missing class references and unresolved dependencies in PHP
 * projects. All settings can be overridden at runtime through the AnalyzerConfig
 * fluent builder or command-line options.
 *
 * @see \Cline\Analyzer\Config\AnalyzerConfig
 * @see \Cline\Analyzer\Console\Commands\AnalyzeCommand
 */

use Cline\Analyzer\Processors\ParallelProcessor;
use Cline\Analyzer\Processors\SerialProcessor;
use Cline\Analyzer\Reporters\PromptsReporter;
use Cline\Analyzer\Resolvers\AnalysisResolver;
use Cline\Analyzer\Resolvers\FileResolver;
use Cline\Analyzer\Resolvers\PathResolver;
use Cline\Analyzer\Resolvers\RouteAnalysisResolver;
use Cline\Analyzer\Resolvers\TranslationAnalysisResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Analysis Paths
    |--------------------------------------------------------------------------
    |
    | This option controls which paths will be analyzed for missing class
    | references and unresolved dependencies. You may specify individual
    | files or directories. When a directory is provided, the analyzer
    | will recursively scan for all PHP files within that directory.
    |
    | Relative paths are resolved from your application's base path.
    |
    */

    'paths' => [
        'app',
        'config',
        'database',
        'operations',
        'src',
        'tests',
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Count
    |--------------------------------------------------------------------------
    |
    | This value determines how many worker processes will be spawned to
    | analyze files concurrently when using ParallelProcessor. Set to 'auto'
    | to automatically detect CPU core count, or specify a number manually.
    | Higher values may improve throughput but will increase memory usage.
    |
    | Values: 'auto' (detect cores), or integer (1, 4, 8, etc.)
    |
    */

    'workers' => env('ANALYZER_WORKERS', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Ignore Patterns
    |--------------------------------------------------------------------------
    |
    | This option allows you to specify glob patterns for class names that
    | should be excluded from analysis. This is particularly useful for
    | ignoring framework classes, vendor packages, or dynamically loaded
    | classes that cannot be resolved through static analysis but are
    | available at runtime. Wildcards (*) are supported.
    |
    */

    'ignore' => [
        'Illuminate\\*',
        'Laravel\\*',
        'Symfony\\*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclude Patterns
    |--------------------------------------------------------------------------
    |
    | This option allows you to specify glob patterns for files and
    | directories that should be excluded from analysis. This is useful
    | for skipping vendor directories, build artifacts, cache folders, or
    | any other files that should not be scanned. Both file and directory
    | patterns are supported. Wildcards (*) are supported.
    |
    */

    'exclude' => [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
    ],

    /*
    |--------------------------------------------------------------------------
    | Path Resolver
    |--------------------------------------------------------------------------
    |
    | This option controls the implementation used for resolving and
    | normalizing file system paths. The resolver handles converting
    | relative paths to absolute paths and ensuring consistent path
    | formatting across different operating systems.
    |
    | Your custom resolver must implement PathResolverInterface.
    |
    */

    'path_resolver' => PathResolver::class,

    /*
    |--------------------------------------------------------------------------
    | File Resolver
    |--------------------------------------------------------------------------
    |
    | This option controls the implementation used for discovering PHP
    | files within the configured paths. The resolver is responsible
    | for recursively scanning directories, filtering file types, and
    | returning a collection of files to be analyzed.
    |
    | Your custom resolver must implement FileResolverInterface.
    |
    */

    'file_resolver' => FileResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Analysis Resolver
    |--------------------------------------------------------------------------
    |
    | This option controls the implementation used for the core analysis
    | logic. The resolver examines PHP files to identify class imports,
    | validate their existence, and detect missing or unresolved class
    | references. This is the heart of the analyzer's functionality.
    |
    | Your custom resolver must implement AnalysisResolverInterface.
    |
    */

    'analysis_resolver' => AnalysisResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Translation Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for translation key analysis when using the --lang flag.
    | The resolver examines PHP and Blade files to identify trans(), __(),
    | and Lang::get() calls and validates them against available translation
    | files in the configured locales.
    |
    */

    'translation' => [
        // Resolver class (must implement AnalysisResolverInterface)
        'resolver' => TranslationAnalysisResolver::class,

        // Path to Laravel lang directory containing translation files
        'lang_path' => base_path('lang'),

        // Locale codes to validate against (e.g., ['en', 'es', 'fr'])
        'locales' => ['en'],

        // Report dynamic translation keys as warnings
        'report_dynamic' => true,

        // Path to vendor package translations (null to disable)
        'vendor_path' => null,

        // Translation key patterns to ignore during validation
        'ignore' => [],

        // Only validate keys matching these patterns (null to validate all)
        'include_patterns' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for route name analysis when using the --route flag.
    | The resolver examines PHP and Blade files to identify route() calls
    | and validates them against Laravel's registered routes.
    |
    */

    'route' => [
        // Resolver class (must implement AnalysisResolverInterface)
        'resolver' => RouteAnalysisResolver::class,

        // Path to Laravel routes directory containing route definition files
        'routes_path' => base_path('routes'),

        // Enable route caching for improved performance
        'cache_routes' => true,

        // Cache time-to-live in seconds
        'cache_ttl' => 3600,

        // Report dynamic route names as warnings
        'report_dynamic' => true,

        // Only validate routes matching these patterns (null to validate all)
        'include_patterns' => null,

        // Ignore routes matching these patterns
        'ignore_patterns' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporter
    |--------------------------------------------------------------------------
    |
    | This option controls the implementation used for displaying analysis
    | progress and results to the user. The reporter handles formatting
    | output, showing progress indicators, and presenting any discovered
    | issues in a readable format. The default uses Laravel Prompts for
    | an interactive console experience.
    |
    | Your custom reporter must implement ReporterInterface.
    |
    */

    'reporter' => PromptsReporter::class,

    /*
    |--------------------------------------------------------------------------
    | Processor
    |--------------------------------------------------------------------------
    |
    | This option controls the implementation used for executing file
    | analysis. The default ParallelProcessor distributes work across
    | multiple workers for optimal performance. You may swap in
    | SerialProcessor for deterministic execution order during debugging,
    | or provide your own custom implementation.
    |
    | Your custom processor must implement ProcessorInterface.
    |
    */

    'processor' => ParallelProcessor::class,

];

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// Here endeth thy configuration, noble developer!                            //
// Beyond: code so wretched, even wyrms learned the scribing arts.            //
// Forsooth, they but penned "// TODO: remedy ere long"                       //
// Three realms have fallen since...                                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
//                                                  .~))>>                    //
//                                                 .~)>>                      //
//                                               .~))))>>>                    //
//                                             .~))>>             ___         //
//                                           .~))>>)))>>      .-~))>>         //
//                                         .~)))))>>       .-~))>>)>          //
//                                       .~)))>>))))>>  .-~)>>)>              //
//                   )                 .~))>>))))>>  .-~)))))>>)>             //
//                ( )@@*)             //)>))))))  .-~))))>>)>                 //
//              ).@(@@               //))>>))) .-~))>>)))))>>)>               //
//            (( @.@).              //))))) .-~)>>)))))>>)>                   //
//          ))  )@@*.@@ )          //)>))) //))))))>>))))>>)>                 //
//       ((  ((@@@.@@             |/))))) //)))))>>)))>>)>                    //
//      )) @@*. )@@ )   (\_(\-\b  |))>)) //)))>>)))))))>>)>                   //
//    (( @@@(.@(@ .    _/`-`  ~|b |>))) //)>>)))))))>>)>                      //
//     )* @@@ )@*     (@)  (@) /\b|))) //))))))>>))))>>                       //
//   (( @. )@( @ .   _/  /    /  \b)) //))>>)))))>>>_._                       //
//    )@@ (@@*)@@.  (6///6)- / ^  \b)//))))))>>)))>>   ~~-.                   //
// ( @jgs@@. @@@.*@_ VvvvvV//  ^  \b/)>>))))>>      _.     `bb                //
//  ((@@ @@@*.(@@ . - | o |' \ (  ^   \b)))>>        .'       b`,             //
//   ((@@).*@@ )@ )   \^^^/  ((   ^  ~)_        \  /           b `,           //
//     (@@. (@@ ).     `-'   (((   ^    `\ \ \ \ \|             b  `.         //
//       (*.@*              / ((((        \| | |  \       .       b `.        //
//                         / / (((((  \    \ /  _.-~\     Y,      b  ;        //
//                        / / / (((((( \    \.-~   _.`" _.-~`,    b  ;        //
//                       /   /   `(((((()    )    (((((~      `,  b  ;        //
//                     _/  _/      `"""/   /'                  ; b   ;        //
//                 _.-~_.-~           /  /'                _.'~bb _.'         //
//               ((((~~              / /'              _.'~bb.--~             //
//                                  ((((          __.-~bb.-~                  //
//                                              .'  b .~~                     //
//                                              :bb ,'                        //
//                                              ~~~~                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
