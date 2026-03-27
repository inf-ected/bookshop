<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    @stack('head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col bg-surface text-text-primary font-sans">

    <div class="relative">
        @include('partials.header')
    </div>

    <main class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')

</body>
</html>
