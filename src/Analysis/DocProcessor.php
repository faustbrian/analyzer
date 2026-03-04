<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Analyzer\Analysis;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tags\BaseTag;
use phpDocumentor\Reflection\Fqsen;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\AbstractList;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Nullable;
use phpDocumentor\Reflection\Types\Object_;

use function array_filter;
use function array_map;
use function array_merge;
use function is_array;
use function is_object;
use function iterator_to_array;
use function mb_ltrim;
use function method_exists;

/**
 * Extracts fully qualified class names from PHPDoc blocks.
 *
 * Processes phpDocumentor reflection objects to extract all type references from
 * PHPDoc annotations including @param, @return, @var, and other typed tags. Recursively
 * traverses complex type structures (arrays, unions, intersections, nullable types) to
 * identify all referenced classes for dependency analysis and static analysis tooling.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DocProcessor
{
    /**
     * Extract all class references from an array of PHPDoc blocks.
     *
     * Processes multiple DocBlock instances to extract fully qualified class names from
     * all type annotations. Flattens nested type structures and removes duplicates to
     * provide a comprehensive list of class dependencies declared in documentation.
     *
     * @param  array<DocBlock> $docs Collection of parsed PHPDoc blocks to analyze
     * @return array<string>   List of fully qualified class names referenced in the documentation
     */
    public static function process(array $docs): array
    {
        /** @var array<string> */
        return self::flatmap(
            fn (DocBlock $doc): array => self::flatmap(
                fn (object $tag): array => self::flatmap(
                    fn (Type $type): array => self::processType($type),
                    self::processTag($tag),
                ),
                $doc->getTags(),
            ),
            $docs,
        );
    }

    /**
     * Apply a transformation function to each array element and flatten the results.
     *
     * Functional programming utility that maps a callable over an array and merges all
     * resulting arrays into a single flat array. Similar to JavaScript's flatMap or
     * array_reduce with array_merge. Filters out non-array results before merging.
     *
     * @param  callable     $fn    Transformation function to apply to each element
     * @param  array<mixed> $array Input array to transform and flatten
     * @return array<mixed> Flattened array containing all results from the transformation
     */
    private static function flatmap(callable $fn, array $array): array
    {
        if ($array === []) {
            return [];
        }

        $mapped = array_map($fn, $array);
        $filtered = array_filter($mapped, is_array(...));

        return array_merge(...$filtered);
    }

    /**
     * Extract type information from a PHPDoc tag.
     *
     * Analyzes a PHPDoc tag object to extract Type instances from supported tags.
     * Handles tags with getType() method (@param, @return, @var) and tags with
     * getParameters() method (@method). Uses reflection-based method_exists checks
     * to maintain compatibility across different phpDocumentor versions.
     *
     * @param  object      $tag PHPDoc tag object to analyze for type information
     * @return array<Type> Collection of Type instances found in the tag
     */
    private static function processTag(object $tag): array
    {
        if (!$tag instanceof BaseTag) {
            return []; // @codeCoverageIgnore
        }

        /** @var array<Type> $types */
        $types = [];

        // Extract type from tags like @param, @return, @var
        if (method_exists($tag, 'getType')) {
            $type = $tag->getType();

            if ($type instanceof Type) {
                $types[] = $type;
            }
        }

        // Extract types from @method tag parameters
        if (method_exists($tag, 'getParameters')) {
            $parameters = $tag->getParameters();

            if (is_array($parameters)) {
                foreach ($parameters as $param) {
                    if (!is_object($param)) {
                        continue;
                    }

                    if (!method_exists($param, 'getType')) {
                        continue;
                    }

                    $type = $param->getType();

                    if (!$type instanceof Type) {
                        continue;
                    }

                    $types[] = $type;
                }
            }
        }

        return $types;
    }

    /**
     * Extract fully qualified class names from a type definition.
     *
     * Recursively processes phpDocumentor Type instances to extract class references.
     * Handles complex type structures including arrays/collections (AbstractList),
     * union/intersection types (Compound), nullable types, and object types. Strips
     * leading backslashes from FQCN to normalize class name format.
     *
     * @param  Type          $type Type definition to analyze for class references
     * @return array<string> List of fully qualified class names referenced by the type
     */
    private static function processType(Type $type): array
    {
        // Process array/collection types by extracting key and value type references
        if ($type instanceof AbstractList) {
            /** @var array<string> */
            return self::flatmap(fn (Type $t): array => self::processType($t), [$type->getKeyType(), $type->getValueType()]);
        }

        // Process union/intersection types by recursively extracting from each constituent type
        if ($type instanceof Compound) {
            /** @var array<string> */
            return self::flatmap(fn (Type $t): array => self::processType($t), iterator_to_array($type));
        }

        // Process nullable types by unwrapping and analyzing the actual type
        if ($type instanceof Nullable) {
            return self::processType($type->getActualType());
        }

        // Extract FQCN from object types, stripping leading backslash for consistency
        if ($type instanceof Object_ && ($fq = $type->getFqsen()) instanceof Fqsen) {
            return [mb_ltrim((string) $fq, '\\')];
        }

        return [];
    }
}
