<div>
    <h1>Welcome</h1>
    <nav>
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('posts.index') }}">Posts</a>
        <a href="{{ route('about') }}">About</a>
        <a href="{{ route('contact') }}">Contact</a>
    </nav>

    @if(Auth::check())
        <a href="{{ route('users.profile') }}">Profile</a>
        <a href="{{ route('dashboard') }}">Dashboard</a>
    @else
        <a href="{{ route('login') }}">Login</a>
        <a href="{{ route('register') }}">Register</a>
    @endif
</div>
