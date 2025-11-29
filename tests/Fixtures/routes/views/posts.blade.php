@extends('layouts.app')

@section('content')
    <div class="posts">
        <h1>Posts</h1>

        <a href="{{ route('posts.create') }}" class="btn">Create Post</a>

        @foreach($posts as $post)
            <article>
                <h2>{{ $post->title }}</h2>
                <p>{{ $post->excerpt }}</p>

                <div class="actions">
                    <a href="{{ route('posts.show', $post) }}">Read More</a>
                    <a href="{{ route('posts.edit', $post->id) }}">Edit</a>

                    <form action="{{ route('posts.destroy', $post) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit">Delete</button>
                    </form>
                </div>

                <div class="comments">
                    <a href="{{ route('posts.comments.index', $post) }}">
                        Comments ({{ $post->comments_count }})
                    </a>
                </div>
            </article>
        @endforeach

        {{ $posts->links() }}
    </div>
@endsection
