<div>
    <h1>Invalid Routes</h1>
    <nav>
        <a href="{{ route('nonexistent.page') }}">Broken Link</a>
        <a href="{{ route('invalid.link') }}">Another Broken Link</a>
        <a href="{{ route('missing.route') }}">Missing Route</a>
    </nav>

    @if(Route::has('fake.route'))
        <p>This route doesn't exist</p>
    @endif
</div>
