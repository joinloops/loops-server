@php($title = \App\Services\FrontendService::getAppName())
@php($desc = \App\Services\FrontendService::getAppDescription())
@php($favicon = \App\Services\FrontendService::getAppFavicon())
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title }}</title>
    <link rel="shortcut icon" type="image/png" href="{{$favicon}}"/>
    <meta name="description" content="{{ $desc }}">
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:description" content="{{ $desc }}" />
    <meta property="og:type" content="website" />
    <meta name="twitter:title" content="{{ $title }}" />
    <meta name="twitter:description" content="{{ $desc }}" />
    @vite(['resources/js/app.js']){!! App\Services\FrontendService::getCustomCss() !!}

    <script type="text/javascript">
    window.appConfig = @json(\App\Services\FrontendService::getAppData());
    window._navi = @json(App\Services\PageService::getActiveSideLinks());
    window.appCaptcha = @json(App\Services\FrontendService::getCaptchaData());
    </script>
</head>

<body class="bg-white dark:bg-slate-950">
    <main id="app">
        <router-view></router-view>
    </main>
</body>
</html>
