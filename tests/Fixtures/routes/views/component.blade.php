<div>
    <h2>Users</h2>

    <div>
        <a href="{{ route('users.index') }}">All Users</a>

        @foreach($users as $user)
            <div>
                <a href="{{ route('users.profile', $user) }}">{{ $user->name }}</a>
                <a href="{{ route('users.edit', $user->id) }}">Edit</a>
            </div>
        @endforeach
    </div>

    <footer>
        <a href="{{ route('home') }}">Back to Home</a>
    </footer>
</div>
