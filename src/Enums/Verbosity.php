<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Enums;

/**
 * Output verbosity levels for analyzer command execution.
 *
 * Controls the amount of detail displayed during analysis operations, from minimal
 * output to comprehensive debugging information. Maps directly to Symfony Console
 * output verbosity levels for consistent CLI behavior across analyzer commands.
 *
 * ```php
 * // Usage in analyzer commands
 * $verbosity = Verbosity::from($input->getOption('verbose'));
 * $analyzer->setVerbosity($verbosity);
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see \Symfony\Component\Console\Output\OutputInterface Symfony verbosity constants
 */
enum Verbosity: int
{
    /**
     * Normal output level showing essential information only.
     *
     * Displays standard progress indicators, completion status, and critical
     * errors without additional context. Suitable for production environments
     * and automated processes where minimal output is desired.
     */
    case Normal = 0;

    /**
     * Verbose output level (-v flag) showing additional operational details.
     *
     * Includes informational messages about analysis progress, file processing
     * status, and non-critical warnings. Useful for development and debugging
     * basic analysis workflows without overwhelming detail.
     */
    case Verbose = 1;

    /**
     * Very verbose output level (-vv flag) showing extensive diagnostic information.
     *
     * Provides detailed analysis metrics, configuration values, internal processing
     * steps, and performance timing data. Recommended for troubleshooting analysis
     * issues or understanding analyzer behavior in complex scenarios.
     */
    case VeryVerbose = 2;

    /**
     * Debug output level (-vvv flag) showing complete execution traces.
     *
     * Exposes full stack traces for all errors, internal state changes, raw data
     * structures, and method call sequences. Essential for debugging analyzer
     * internals or investigating unexpected behavior during development.
     */
    case Debug = 3;
}
