@extends('layouts.app')

@section('content')
<div class="min-h-screen p-8 space-y-12">

    {{-- Colors --}}
    <section>
        <h2 class="font-serif text-2xl mb-4">Brand palette</h2>
        <div class="flex flex-wrap gap-2">
            @foreach([50,100,200,300,400,500,600,700,800,900,950] as $shade)
                <div class="w-16 h-16 rounded flex items-end justify-center pb-1 text-xs"
                     style="background: var(--color-brand-{{ $shade }}); color: {{ $shade > 400 ? '#fff' : '#1e293b' }}">
                    {{ $shade }}
                </div>
            @endforeach
        </div>
    </section>

    <section>
        <h2 class="font-serif text-2xl mb-4">Accent palette</h2>
        <div class="flex flex-wrap gap-2">
            @foreach([100,200,300,400,500,600,700,800,900] as $shade)
                <div class="w-16 h-16 rounded flex items-end justify-center pb-1 text-xs"
                     style="background: var(--color-accent-{{ $shade }}); color: {{ $shade > 400 ? '#fff' : '#1e293b' }}">
                    {{ $shade }}
                </div>
            @endforeach
        </div>
    </section>

    <section>
        <h2 class="font-serif text-2xl mb-4">Surface tokens</h2>
        <div class="flex gap-4">
            <div class="w-32 h-16 rounded border border-gray-200 flex items-center justify-center text-sm bg-surface">surface</div>
            <div class="w-32 h-16 rounded border border-gray-200 flex items-center justify-center text-sm bg-surface-muted">surface-muted</div>
        </div>
    </section>

    {{-- Typography --}}
    <section class="space-y-3">
        <h2 class="font-serif text-2xl mb-4">Typography</h2>
        <p class="font-serif text-4xl">Serif — заголовок книги</p>
        <p class="font-sans text-xl">Sans — основной текст интерфейса</p>
        <p class="font-sans text-base text-text-muted">Muted — вспомогательный текст</p>
    </section>

    {{-- Buttons --}}
    <section class="space-y-4">
        <h2 class="font-serif text-2xl mb-4">Buttons</h2>
        <div class="flex flex-wrap gap-3">
            <button class="px-6 py-3 bg-brand-700 text-white rounded hover:bg-brand-800 transition">Primary</button>
            <button class="px-6 py-3 bg-accent text-white rounded hover:bg-accent-dark transition">Accent CTA</button>
            <button class="px-6 py-3 border border-brand-700 text-brand-700 rounded hover:bg-brand-50 transition">Outline</button>
        </div>
    </section>

    {{-- No-select --}}
    <section>
        <h2 class="font-serif text-2xl mb-4">.no-select (fragment protection)</h2>
        <p class="no-select p-4 bg-surface-muted rounded text-sm">
            Этот текст нельзя выделить — используется на странице фрагмента книги.
        </p>
    </section>

</div>
@endsection
