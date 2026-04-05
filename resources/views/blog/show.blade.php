@extends('layouts.app')

@section('content')
<article>
    <h1>{{ $post->title }}</h1>
    <time>{{ $post->published_at->format('d.m.Y') }}</time>
    <div>{!! $post->body !!}</div>
</article>
@endsection
