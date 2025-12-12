<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Analyzer\Data\AnalysisResult;
use Cline\Analyzer\Resolvers\AnalysisResolver;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Request;

test('it analyzes valid file successfully', function (): void {
    $resolver = new AnalysisResolver();
    $file = new SplFileInfo(__DIR__.'/../../Fixtures/ValidClass.php');
    $result = $resolver->analyze($file);

    expect($result)->toBeInstanceOf(AnalysisResult::class)
        ->and($result->success)->toBeTrue()
        ->and($result->missing)->toBeEmpty();
});

test('it detects missing class references', function (): void {
    $resolver = new AnalysisResolver();
    $file = new SplFileInfo(__DIR__.'/../../Fixtures/InvalidClass.php');
    $result = $resolver->analyze($file);

    expect($result)->toBeInstanceOf(AnalysisResult::class)
        ->and($result->success)->toBeFalse()
        ->and($result->missing)->not->toBeEmpty()
        ->and($result->hasMissing())->toBeTrue();
});

test('it respects ignore patterns', function (): void {
    $resolver = new AnalysisResolver(['NonExistent\\*']);
    $file = new SplFileInfo(__DIR__.'/../../Fixtures/InvalidClass.php');
    $result = $resolver->analyze($file);

    expect($result->missing)->not->toContain('NonExistent\\FakeClass');
});

test('it checks if class exists', function (): void {
    $resolver = new AnalysisResolver();

    expect($resolver->classExists(SplFileInfo::class))->toBeTrue()
        ->and($resolver->classExists('NonExistent\\FakeClass'))->toBeFalse();
});

describe('ignore patterns', function (): void {
    test('ignores classes matching namespace wildcard pattern', function (): void {
        $resolver = new AnalysisResolver(['NonExistent\\*']);
        $file = new SplFileInfo(__DIR__.'/../../Fixtures/InvalidClass.php');
        $result = $resolver->analyze($file);

        expect($result->missing)->not->toContain('NonExistent\\FakeClass')
            ->and($result->missing)->toContain('Another\\MissingClass');
    });

    test('ignores classes matching exact pattern', function (): void {
        $resolver = new AnalysisResolver(['Another\\MissingClass']);
        $file = new SplFileInfo(__DIR__.'/../../Fixtures/InvalidClass.php');
        $result = $resolver->analyze($file);

        expect($result->missing)->not->toContain('Another\\MissingClass')
            ->and($result->missing)->toContain('NonExistent\\FakeClass');
    });

    test('ignores classes matching multiple patterns', function (): void {
        $resolver = new AnalysisResolver(['NonExistent\\*', 'Another\\*']);
        $file = new SplFileInfo(__DIR__.'/../../Fixtures/InvalidClass.php');
        $result = $resolver->analyze($file);

        expect($result->missing)->toBeEmpty()
            ->and($result->success)->toBeTrue();
    });

    test('ignores deeply nested namespace patterns', function (): void {
        $resolver = new AnalysisResolver(['Filament\\Http\\Middleware\\*']);

        // Use reflection to test shouldIgnore directly
        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('shouldIgnore');

        expect($method->invoke($resolver, 'Filament\\Http\\Middleware\\Authenticate'))->toBeTrue()
            ->and($method->invoke($resolver, 'Filament\\Http\\Middleware\\DisableBladeIconComponents'))->toBeTrue()
            ->and($method->invoke($resolver, 'Filament\\Panel'))->toBeFalse();
    });

    test('ignores vendor namespace with single wildcard', function (): void {
        $resolver = new AnalysisResolver(['Filament\\*']);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('shouldIgnore');

        // Single wildcard should match any depth
        expect($method->invoke($resolver, 'Filament\\Panel'))->toBeTrue()
            ->and($method->invoke($resolver, 'Filament\\Http\\Middleware\\Authenticate'))->toBeTrue()
            ->and($method->invoke($resolver, 'Filament\\Support\\Colors\\Color'))->toBeTrue()
            ->and($method->invoke($resolver, 'Laravel\\Sanctum\\Guard'))->toBeFalse();
    });

    test('ignores multiple vendor namespaces', function (): void {
        $resolver = new AnalysisResolver([
            'Filament\\*',
            'Illuminate\\*',
            'Laravel\\*',
            'Symfony\\*',
        ]);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('shouldIgnore');

        expect($method->invoke($resolver, 'Filament\\Panel'))->toBeTrue()
            ->and($method->invoke($resolver, Collection::class))->toBeTrue()
            ->and($method->invoke($resolver, 'Laravel\\Sanctum\\Guard'))->toBeTrue()
            ->and($method->invoke($resolver, Request::class))->toBeTrue()
            ->and($method->invoke($resolver, 'App\\Models\\User'))->toBeFalse();
    });

    test('handles question mark single character wildcard', function (): void {
        $resolver = new AnalysisResolver(['Test?Class']);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('shouldIgnore');

        expect($method->invoke($resolver, 'TestaClass'))->toBeTrue()
            ->and($method->invoke($resolver, 'TestbClass'))->toBeTrue()
            ->and($method->invoke($resolver, 'TestClass'))->toBeFalse()
            ->and($method->invoke($resolver, 'TestabClass'))->toBeFalse();
    });

    test('handles pattern with regex special characters', function (): void {
        // Patterns with characters that are special in regex: . + ^ $ ( ) [ ] { } |
        $resolver = new AnalysisResolver(['App\\Services\\Payment.Gateway']);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('shouldIgnore');

        // The dot should be literal, not regex "any character"
        expect($method->invoke($resolver, 'App\\Services\\Payment.Gateway'))->toBeTrue()
            ->and($method->invoke($resolver, 'App\\Services\\PaymentXGateway'))->toBeFalse();
    });
});
