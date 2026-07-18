<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Loops') }}</title>
    <meta name="app-version" content="{{ \App\Services\ViteService::get() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white dark:bg-gray-950">
    <div id="app">
        <header class="my-10 flex items-center justify-center gap-3">
            <img src="/nav-logo.png" width="60" />
            <div class="text-3xl tracking-tight font-bold dark:text-white">Loops</div>
        </header>
        <main class="py-8">
            @yield('content')
        </main>
    </div>

</body>
</html>
