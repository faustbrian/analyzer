<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Analysis;

use Cline\Analyzer\Exceptions\EmptyClassNameException;
use ReflectionClass;

use function class_exists;
use function interface_exists;
use function trait_exists;

/**
 * Inspects PHP classes, interfaces, and traits to determine their type and extract metadata.
 *
 * Provides a fluent interface for type checking and reflection-based analysis of PHP constructs.
 * Validates class names, determines construct type (class/interface/trait), and extracts
 * fully qualified class name references from source files. Immutable by design to ensure
 * thread-safety and predictable behavior during static analysis operations.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ClassInspector
{
    /**
     * Create a new class inspector instance.
     *
     * @param string $class Fully qualified class name to inspect. Must be a non-empty string
     *                      representing a valid PHP class, interface, or trait name. The class
     *                      does not need to be loaded or exist in the runtime environment at
     *                      construction time, allowing inspection of source code references.
     */
    private function __construct(
        private string $class,
    ) {}

    /**
     * Create a new inspector for the specified class name.
     *
     * Factory method that validates the provided class name is non-empty before creating
     * an inspector instance. Prevents invalid inspection attempts with empty or zero strings.
     *
     * @param string $class Fully qualified class name to inspect
     *
     * @throws EmptyClassNameException When the class name is an empty string or '0'
     *
     * @return self New inspector instance configured for the specified class
     */
    public static function inspect(string $class): self
    {
        if ($class === '' || $class === '0') {
            throw EmptyClassNameException::create();
        }

        return new self($class);
    }

    /**
     * Check if the construct is a class.
     *
     * Uses PHP's native class_exists() to determine if the construct represents a loadable
     * class definition, excluding interfaces and traits.
     *
     * @return bool True if the construct is a class, false otherwise
     */
    public function isClass(): bool
    {
        return class_exists($this->class);
    }

    /**
     * Check if the construct is an interface.
     *
     * Uses PHP's native interface_exists() to determine if the construct represents an
     * interface definition, excluding classes and traits.
     *
     * @return bool True if the construct is an interface, false otherwise
     */
    public function isInterface(): bool
    {
        return interface_exists($this->class);
    }

    /**
     * Check if the construct is a trait.
     *
     * Uses PHP's native trait_exists() to determine if the construct represents a trait
     * definition, excluding classes and interfaces.
     *
     * @return bool True if the construct is a trait, false otherwise
     */
    public function isTrait(): bool
    {
        return trait_exists($this->class);
    }

    /**
     * Check if the construct exists in the PHP runtime.
     *
     * Determines whether the construct is a loaded or autoloadable class, interface, or trait.
     * Returns true if the construct is any valid PHP type definition.
     *
     * @return bool True if the construct exists as a class, interface, or trait
     */
    public function exists(): bool
    {
        if ($this->isClass()) {
            return true;
        }

        if ($this->isInterface()) {
            return true;
        }

        return $this->isTrait();
    }

    /**
     * Get a reflection instance for the construct.
     *
     * Creates a ReflectionClass instance when the construct exists in the runtime,
     * enabling detailed introspection of the class structure, methods, properties,
     * and metadata. Returns null if the construct cannot be loaded or does not exist.
     *
     * @return null|ReflectionClass<object> Reflection instance for introspection, or null if not found
     */
    public function refector(): ?ReflectionClass
    {
        if (!$this->exists()) {
            return null;
        }

        /** @var class-string $class */
        $class = $this->class;

        return new ReflectionClass($class);
    }

    /**
     * Extract all fully qualified class name references from the construct's source file.
     *
     * Analyzes the source file containing the construct definition to identify all
     * referenced class names, including use statements, type hints, annotations, and
     * inline references. Useful for dependency analysis, autoload optimization, and
     * static analysis tooling.
     *
     * @return array<string> List of fully qualified class names referenced in the source file
     */
    public function references(): array
    {
        if (($refector = $this->refector()) instanceof ReflectionClass) {
            $fileName = $refector->getFileName();

            if ($fileName === false) {
                return [];
            }

            return new ReferenceAnalyzer()->analyze($fileName);
        }

        return [];
    }
}
