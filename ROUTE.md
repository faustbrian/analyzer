# Route Analyzer Implementation Plan

## Overview
Analyze PHP and Blade files to detect invalid `route()` calls that reference non-existent named routes.

## Architecture

### 1. RouteCallVisitor (AST Visitor)
**Purpose**: Extract route name references from PHP AST

**Detects**:
- `route('posts.index')`
- `route('user.profile', ['id' => 1])`
- `Route::has('admin.dashboard')`
- `redirect()->route('login')`

**Returns**: Array of route names with file location metadata

### 2. BladeParser (Shared with Translation Analyzer)
**Purpose**: Convert Blade templates to parseable PHP

**Handles**:
- `{{ route('posts.show', $post) }}`
- `<a href="{{ route('home') }}">`
- `@route('api.users')`

### 3. RouteAnalysisResolver
**Purpose**: Validate extracted route names against Laravel's route collection

**Implementation**:
```php
final readonly class RouteAnalysisResolver implements AnalysisResolverInterface
{
    private array $namedRoutes;

    public function __construct(
        private string $routesPath,
        private ?Application $app = null
    ) {
        $this->namedRoutes = $this->loadRoutes();
    }

    private function loadRoutes(): array
    {
        // Option A: Bootstrap minimal Laravel app, load routes
        // Option B: Parse routes/*.php files statically (limited)
    }

    public function analyze(SplFileInfo $file): AnalysisResult
    {
        // Parse file, extract route names, validate against $this->namedRoutes
    }
}
```

## Route Loading Strategies

### Option A: Bootstrap Laravel (Complete but Slow)
```php
$app = new Application(base_path());
$app->make(Kernel::class)->bootstrap();

$routes = Route::getRoutes()->getRoutesByName();
// Returns: ['posts.index' => Route, 'user.profile' => Route, ...]
```

**Pros**:
- Gets all routes including middleware/groups
- Handles route model binding
- Supports route macros

**Cons**:
- Slow startup (~100-500ms)
- Requires valid Laravel app
- May trigger service providers

### Option B: Static Parsing (Fast but Limited)
```php
// Parse AST of routes/*.php files
// Look for Route::*()->name('route.name')
```

**Pros**:
- Fast (no Laravel bootstrap)
- No side effects

**Cons**:
- Can't detect routes from packages
- Misses route groups/prefixes
- Won't handle complex route registration

**Recommendation**: Use Option A with caching

## Validation Logic

### Static Names (Easy)
```php
route('posts.index')              // ✓ Can validate
route('user.profile', $params)    // ✓ Can validate (ignore params)
Route::has('admin.dashboard')     // ✓ Can validate
```

### Dynamic Names (Hard)
```php
route($variable)                  // ⚠️ Warning: can't validate
route('prefix.' . $suffix)        // ⚠️ Warning: dynamic
route(config('routes.dashboard')) // ⚠️ Warning: uses config
```

**Strategy**: Report dynamic calls separately with warning level

## Route Name Extraction Patterns

### From PHP AST
```php
// FuncCall node with name 'route'
$node->name->toString() === 'route'
$node->args[0]->value instanceof String_
```

### From Blade
```php
// After compilation:
{{ route('name') }}
// Becomes:
<?php echo e(route('name')); ?>
```

## Configuration

Add to `config/analyzer.php`:
```php
'route' => [
    'enabled' => true,
    'routes_path' => base_path('routes'),
    'bootstrap_app' => true, // Set false to use static parsing
    'cache_routes' => true,
    'cache_ttl' => 3600,
    'report_dynamic' => true, // Report dynamic route usage as warnings
],
```

## Testing Strategy

### Unit Tests
- `RouteCallVisitor` extracts route names correctly
- `RouteAnalysisResolver` loads routes correctly
- Route validation logic
- Handles route parameters
- Detects `Route::has()` calls

### Integration Tests
- Analyze PHP file with `route()` calls
- Analyze Blade file with route helpers
- Test with route groups/prefixes
- Test dynamic name detection
- Test with non-existent routes

### Fixtures
```
tests/Fixtures/routes/
  routes/web.php (contains named routes)
  routes/api.php
  views/navigation.blade.php
  php/RouteUsage.php
```

## Route Loading Optimization

### Caching Strategy
```php
$cacheKey = md5(serialize(glob($routesPath . '/*.php')));
$cacheFile = storage_path("analyzer/routes.{$cacheKey}.cache");

if (file_exists($cacheFile)) {
    return unserialize(file_get_contents($cacheFile));
}

$routes = $this->bootstrapAndLoadRoutes();
file_put_contents($cacheFile, serialize($routes));
```

### Parallel Processing Considerations
- Load routes ONCE in main process
- Share route data with worker processes via serialization
- Avoid bootstrapping Laravel in each worker

## Edge Cases

- **Route parameters**: `route('posts.show', $post)` - validate name only, ignore params
- **Route groups**: Prefixed routes like `admin.users.index`
- **Subdomain routing**: `route('subdomain.home')`
- **Fallback routes**: Routes without names
- **API vs Web routes**: Both in `routes/` directory
- **Package routes**: Routes registered by service providers
- **Route model binding**: Don't validate model parameters
- **Localized routes**: `route('about', ['locale' => 'fr'])`

## Integration with Translation Analyzer

### Shared Components
- `BladeParser` - used by both analyzers
- `DynamicCallDetector` - detect variables in function arguments
- File type detection logic

### Separate Concerns
- Each has its own Visitor class
- Each has its own Resolver
- Each has its own configuration

## Implementation Order

1. ✅ Create `RouteCallVisitor` (PHP only)
2. ✅ Create `RouteAnalysisResolver` with Laravel bootstrap
3. ✅ Write tests for PHP file analysis
4. ✅ Add route caching mechanism
5. ✅ Add `BladeParser` support (shared with translation analyzer)
6. ✅ Write tests for Blade file analysis
7. ✅ Add dynamic route name detection with warnings
8. ✅ Optimize for parallel processing
9. ✅ Integration with existing Analyzer

## Performance Considerations

- Bootstrap Laravel ONCE, cache route list
- Use file modification time to invalidate cache
- Share route data across parallel workers
- Consider lazy loading (only load routes if files use `route()`)
- Monitor memory usage with large route collections
