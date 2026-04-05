@extends('layouts.app')

@section('content')
<div>
    @foreach ($posts as $post)
        <article>
            <h2><a href="{{ route('blog.show', $post) }}">{{ $post->title }}</a></h2>
            @if ($post->excerpt)
                <p>{{ $post->excerpt }}</p>
            @endif
            <time>{{ $post->published_at->format('d.m.Y') }}</time>
        </article>
    @endforeach

    {{ $posts->links() }}
</div>
@endsection
