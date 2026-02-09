@php
use App\Services\FrontendService;
use Illuminate\Support\Str;

$appName = FrontendService::getAppName();
$appDesc = FrontendService::getAppDescription();
$isNsfw = data_get($videoData, 'is_sensitive', false);

$authorName = data_get($videoData, 'account.name', '');
$authorUsername = data_get($videoData, 'account.username', '');
$authorAvatar = data_get($videoData, 'account.avatar', url('/storage/avatars/default.jpg'));

if ($isNsfw) {
    $videoTitle = $appName;
    $videoDesc = $appDesc;
} else {
    $videoTitle = $videoData
        ? ($videoData['caption'] ? Str::limit($videoData['caption'], 26) . " - @" . $authorUsername . " | {$appName}" : "@{$authorUsername} | {$appName}")
        : $appName;
    
    $videoDesc = $appDesc;
    if ($videoData) {
        if (!empty($videoData['captionText'])) {
            $videoDesc = $videoData['captionText'];
        } elseif (!empty($videoData['caption'])) {
            $videoLikes = data_get($videoData, 'likes', 0);
            $videoComments = data_get($videoData, 'comments', 0);
            $stats = "{$videoLikes} likes · {$videoComments} comments";
            $videoDesc = "{$videoData['caption']} • {$stats}";
        }
    }
    
    $videoUrl = data_get($videoData, 'url', url('/'));
    $videoThumbnail = data_get($videoData, 'media.thumbnail', url('/storage/avatars/default.jpg'));
    $videoCdnUrl = data_get($videoData, 'media.src_url', null);
    $videoWidth = data_get($videoData, 'media.width', null);
    $videoHeight = data_get($videoData, 'media.height', null);
    $videoDuration = data_get($videoData, 'media.duration', null);
    $videoType = 'video/mp4';
}
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $videoTitle }}</title>
    <link rel="shortcut icon" type="image/png" href="{{ url('/favicon.png') }}"/>

    <meta name="author" content="{{ $authorName }} ({{ '@' . $authorUsername }})">
    <meta property="article:author" content="{{ url('/@' . $authorUsername) }}" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:logo" content="{{ url('/nav-logo.png') }}" />
    
    @unless($isNsfw)
        <meta name="description" content="{{ $videoDesc }}">
        <meta property="og:title" content="{{ $videoTitle }}" />
        <meta property="og:description" content="{{ $videoDesc }}" />
        <meta property="og:type" content="video" />
        <meta property="og:url" content="{{ $videoUrl }}" />
        <meta property="og:image" content="{{ $videoThumbnail }}" />
        <meta property="og:video" content="{{ $videoCdnUrl }}" />
        <meta property="og:video:width" content="{{ $videoWidth }}">
        <meta property="og:video:height" content="{{ $videoHeight }}">
        <meta property="og:video:duration" content="{{ $videoDuration }}">
        <meta property="og:video:type" content="{{ $videoType }}" />
        <meta property="video:release_date" content="{{ $videoData['created_at'] ?? '' }}" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" content="{{ $videoTitle }}" />
        <meta name="twitter:description" content="{{ $videoDesc }}" />
        <meta name="twitter:image" content="{{ $videoThumbnail }}" />
    @endunless

    @vite(['resources/js/app.js'])
    {!! FrontendService::getCustomCss() !!}

    <script type="text/javascript">
    window.appConfig = @json(FrontendService::getAppData());
    window._navi = @json(App\Services\PageService::getActiveSideLinks());
    window.appCaptcha = @json(FrontendService::getCaptchaData());
    </script>
</head>

<body class="bg-white dark:bg-slate-950">
    <main id="app">
        <router-view></router-view>
    </main>
</body>
</html>