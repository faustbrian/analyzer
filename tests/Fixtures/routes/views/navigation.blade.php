<nav class="navbar">
    <div class="nav-links">
        <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">
            Home
        </a>

        <a href="{{ route('posts.index') }}" class="{{ request()->routeIs('posts.*') ? 'active' : '' }}">
            Posts
        </a>

        @auth
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a href="{{ route('users.profile') }}">Profile</a>

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit">Logout</button>
            </form>
        @else
            <a href="{{ route('login') }}">Login</a>
            <a href="{{ route('register') }}">Register</a>
        @endauth
    </div>

    @if(Route::has('admin.dashboard') && Auth::user()->isAdmin())
        <a href="{{ route('admin.dashboard') }}">Admin</a>
    @endif
</nav>
