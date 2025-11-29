<div>
    <h1>Dynamic Routes (Should trigger warnings)</h1>

    {{-- Dynamic route from variable --}}
    <a href="{{ route($routeName) }}">Dynamic Link</a>

    {{-- Concatenated route --}}
    <a href="{{ route('posts.' . $action) }}">Dynamic Action</a>

    {{-- Config-based route --}}
    <a href="{{ route(config('app.home_route')) }}">Home</a>

    {{-- Ternary operator --}}
    <a href="{{ route($isAdmin ? 'admin.dashboard' : 'dashboard') }}">Dashboard</a>
</div>
