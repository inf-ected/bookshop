<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @stack('head')

    @if(config('services.google_analytics_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics_id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('services.google_analytics_id') }}');
    </script>
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

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
