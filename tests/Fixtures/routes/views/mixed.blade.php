<div>
    <h1>Mixed Valid and Invalid Routes</h1>
    <nav>
        {{-- Valid routes --}}
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('posts.index') }}">Posts</a>
        <a href="{{ route('users.profile') }}">Profile</a>

        {{-- Invalid routes --}}
        <a href="{{ route('invalid.route1') }}">Invalid 1</a>
        <a href="{{ route('nonexistent.route2') }}">Invalid 2</a>
    </nav>

    @if(Route::has('posts.show'))
        <p>Posts route exists</p>
    @endif

    @if(Route::has('missing.route'))
        <p>This route doesn't exist</p>
    @endif

    <form action="{{ route('posts.store') }}" method="POST">
        @csrf
        <button type="submit">Submit</button>
    </form>
</div>
