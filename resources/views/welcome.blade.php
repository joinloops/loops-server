@php($title = \App\Services\FrontendService::getAppName())
@php($desc = \App\Services\FrontendService::getAppDescription())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title }}</title>
    <link rel="icon" href="{{ url('/favicon.ico') }}" sizes="32x32">
    <link rel="icon shortcut" href="{{ url('/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ url('/apple-touch-icon.png') }}">
    @preloadFont('boxicons')

    <link rel="preload" href="{{ url('/fonts/dm-sans-300-normal-latin-b9df6239.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ url('/fonts/syne-400-normal-latin-c37e2fa5.woff2') }}" as="font" type="font/woff2" crossorigin>
    <meta name="description" content="{{ $desc }}">
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:description" content="{{ $desc }}" />
    <meta property="og:type" content="website" />
    <meta name="twitter:title" content="{{ $title }}" />
    <meta name="twitter:description" content="{{ $desc }}" />
    <meta name="apple-itunes-app" content="app-id=6499375182">
    @vite(['resources/js/app.js']){!! App\Services\FrontendService::getCustomCss() !!}

    <script type="text/javascript">
    window.appConfig = @json(\App\Services\FrontendService::getAppData());
    window._navi = @json(App\Services\PageService::getActiveSideLinks());
    window.appCaptcha = @json(App\Services\FrontendService::getCaptchaData());
    </script>
    <script src="/device-id.js" defer></script>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js');
    }
    </script>
</head>

<body class="bg-white dark:bg-slate-950">
    <main id="app">
        <router-view></router-view>
    </main>
</body>
</html>
