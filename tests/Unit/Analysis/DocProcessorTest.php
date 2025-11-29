<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Analysis\DocProcessor;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tags\BaseTag;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\Fqsen;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Nullable;
use phpDocumentor\Reflection\Types\Object_;
use phpDocumentor\Reflection\Types\String_;

// Happy Path: Basic type extraction from @param tag
test('extracts FQCN from param tag with object type', function (): void {
    $fqsen = new Fqsen('\\App\\Models\\User');
    $type = new Object_($fqsen);

    $param = new Param('user', $type);
    $docBlock = new DocBlock('', null, [$param]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe(['App\\Models\\User']);
});

// Happy Path: Multiple types from multiple tags
test('extracts FQCNs from multiple tags', function (): void {
    $fqsen1 = new Fqsen('\\App\\Models\\User');
    $fqsen2 = new Fqsen('\\App\\Models\\Post');

    $param = new Param('user', new Object_($fqsen1));
    $return = new Return_(
        new Object_($fqsen2),
    );

    $docBlock = new DocBlock('', null, [$param, $return]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe(['App\\Models\\User', 'App\\Models\\Post']);
});

// Happy Path: Array types with object values
test('extracts FQCN from array value type', function (): void {
    $fqsen = new Fqsen('\\App\\Models\\User');
    $valueType = new Object_($fqsen);
    $keyType = new String_();

    $arrayType = new Array_($valueType, $keyType);
    $param = new Param('users', $arrayType);
    $docBlock = new DocBlock('', null, [$param]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe(['App\\Models\\User']);
});

// Happy Path: Compound types (union types)
test('extracts FQCNs from compound types', function (): void {
    $fqsen1 = new Fqsen('\\App\\Models\\User');
    $fqsen2 = new Fqsen('\\App\\Models\\Guest');

    $type1 = new Object_($fqsen1);
    $type2 = new Object_($fqsen2);

    $compoundType = new Compound([$type1, $type2]);
    $param = new Param('entity', $compoundType);
    $docBlock = new DocBlock('', null, [$param]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe(['App\\Models\\User', 'App\\Models\\Guest']);
});

// Coverage Gap: Line 166 - Nullable type unwrapping
test('extracts FQCN from nullable type', function (): void {
    $fqsen = new Fqsen('\\App\\Models\\User');
    $objectType = new Object_($fqsen);
    $nullableType = new Nullable($objectType);

    $param = new Param('user', $nullableType);
    $docBlock = new DocBlock('', null, [$param]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe(['App\\Models\\User']);
});

// Coverage Gap: Line 104 - Non-BaseTag object handling
// Note: This edge case is tested by creating DocBlocks with tags that don't extend BaseTag
// In practice, phpDocumentor will only create BaseTag instances, so this is purely defensive code
test('returns empty array for tags without type information', function (): void {
    // Use a built-in tag that doesn't have a getType() method (covers line 104 check)
    $tag = new class('') extends BaseTag implements Stringable
    {
        public function __toString(): string
        {
            return 'custom tag';
        }

        public static function create(string $body): BaseTag
        {
            return new self('');
        }
    };

    $docBlock = new DocBlock('', null, [$tag]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe([]);
});

// Coverage Gap: Lines 121-129 - @method tag parameter type extraction
test('extracts FQCNs from method tag parameters', function (): void {
    // Create a method tag with parameters
    $fqsen = new Fqsen('\\App\\Models\\User');
    $objectType = new Object_($fqsen);

    // Create a parameter object with getType() method
    $parameter = new readonly class($objectType)
    {
        public function __construct(
            private Type $type,
        ) {}

        public function getType(): Type
        {
            return $this->type;
        }
    };

    // Create a method tag with getParameters() method
    $methodTag = new class([$parameter]) extends BaseTag implements Stringable
    {
        public function __construct(
            private readonly array $parameters,
        ) {
            $this->name = 'method';
        }

        public function __toString(): string
        {
            return 'method tag';
        }

        public static function create(string $body): BaseTag
        {
            return new self([]);
        }

        public function getParameters(): array
        {
            return $this->parameters;
        }
    };

    $docBlock = new DocBlock('', null, [$methodTag]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe(['App\\Models\\User']);
});

// Edge Case: Empty DocBlock array
test('returns empty array for empty DocBlock array', function (): void {
    $result = DocProcessor::process([]);

    expect($result)->toBe([]);
});

// Edge Case: DocBlock with no tags
test('returns empty array for DocBlock with no tags', function (): void {
    $docBlock = new DocBlock('');

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe([]);
});

// Edge Case: Tag with getType() returning null
test('handles tag with null type gracefully', function (): void {
    $param = new Param('value');
    $docBlock = new DocBlock('', null, [$param]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe([]);
});

// Edge Case: Object type without FQCN
test('handles object type without FQCN', function (): void {
    $objectType = new Object_();
    $param = new Param('value', $objectType);
    $docBlock = new DocBlock('', null, [$param]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe([]);
});

// Edge Case: Nested nullable in compound type
test('extracts FQCN from nested nullable in compound type', function (): void {
    $fqsen1 = new Fqsen('\\App\\Models\\User');
    $fqsen2 = new Fqsen('\\App\\Models\\Guest');

    $type1 = new Nullable(
        new Object_($fqsen1),
    );
    $type2 = new Object_($fqsen2);

    $compoundType = new Compound([$type1, $type2]);
    $param = new Param('entity', $compoundType);
    $docBlock = new DocBlock('', null, [$param]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe(['App\\Models\\User', 'App\\Models\\Guest']);
});

// Edge Case: Array with nullable object value type
test('extracts FQCN from array with nullable object value type', function (): void {
    $fqsen = new Fqsen('\\App\\Models\\User');
    $valueType = new Nullable(
        new Object_($fqsen),
    );
    $keyType = new String_();

    $arrayType = new Array_($valueType, $keyType);
    $param = new Param('users', $arrayType);
    $docBlock = new DocBlock('', null, [$param]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe(['App\\Models\\User']);
});

// Edge Case: Method tag with non-object parameters
test('handles method tag with parameters without getType method', function (): void {
    $parameter = new class()
    {
        // No getType() method
    };

    $methodTag = new class([$parameter]) extends BaseTag implements Stringable
    {
        public function __construct(
            private readonly array $parameters,
        ) {
            $this->name = 'method';
        }

        public function __toString(): string
        {
            return 'method tag';
        }

        public static function create(string $body): BaseTag
        {
            return new self([]);
        }

        public function getParameters(): array
        {
            return $this->parameters;
        }
    };

    $docBlock = new DocBlock('', null, [$methodTag]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe([]);
});

// Edge Case: Method tag with non-Type return from getType
test('handles method tag parameters with non-Type from getType', function (): void {
    $parameter = new class()
    {
        public function getType(): string
        {
            return 'string';
        }
    };

    $methodTag = new class([$parameter]) extends BaseTag implements Stringable
    {
        public function __construct(
            private readonly array $parameters,
        ) {
            $this->name = 'method';
        }

        public function __toString(): string
        {
            return 'method tag';
        }

        public static function create(string $body): BaseTag
        {
            return new self([]);
        }

        public function getParameters(): array
        {
            return $this->parameters;
        }
    };

    $docBlock = new DocBlock('', null, [$methodTag]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe([]);
});

// Edge Case: Method tag with non-array parameters
test('handles method tag with non-array parameters', function (): void {
    $methodTag = new class() extends BaseTag implements Stringable
    {
        public function __construct()
        {
            $this->name = 'method';
        }

        public function __toString(): string
        {
            return 'method tag';
        }

        public static function create(string $body): BaseTag
        {
            return new self();
        }

        public function getParameters(): string
        {
            return 'not an array';
        }
    };

    $docBlock = new DocBlock('', null, [$methodTag]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe([]);
});

// Edge Case: Method tag with non-object in parameters array
test('handles method tag with non-object items in parameters array', function (): void {
    $methodTag = new class(['string', 123, null]) extends BaseTag implements Stringable
    {
        public function __construct(
            private readonly array $parameters,
        ) {
            $this->name = 'method';
        }

        public function __toString(): string
        {
            return 'method tag';
        }

        public static function create(string $body): BaseTag
        {
            return new self([]);
        }

        public function getParameters(): array
        {
            return $this->parameters;
        }
    };

    $docBlock = new DocBlock('', null, [$methodTag]);

    $result = DocProcessor::process([$docBlock]);

    expect($result)->toBe([]);
});

// Regression: Leading backslash is stripped from FQCN
test('strips leading backslash from FQCN', function (): void {
    $fqsen = new Fqsen('\\App\\Models\\User');
    $type = new Object_($fqsen);

    $param = new Param('user', $type);
    $docBlock = new DocBlock('', null, [$param]);

    $result = DocProcessor::process([$docBlock]);

    // Ensure no leading backslash
    expect($result)->toBe(['App\\Models\\User'])
        ->and($result[0])->not->toStartWith('\\');
});
