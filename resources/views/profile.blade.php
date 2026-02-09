@php
use App\Services\FrontendService;
use App\Services\AccountService;
use Illuminate\Support\Str;

$appName = FrontendService::getAppName();
$appDesc = FrontendService::getAppDescription();

$profileTitle = $profile ? "{$profile->name} (@{$profile->username}) | {$appName}" : $appName;

$descParts = [];
if ($profile && $profile->bio) {
    $descParts[] = Str::limit($profile->bio, 26);
}
$likes = AccountService::getAccountLikesCount($profile->id);
if ($profile) {
    $stats = [
        number_format($profile->video_count ?? 0) . ' videos',
        number_format($profile->followers ?? 0) . ' followers',
        number_format($likes ?? 0) . ' likes'
    ];
    $descParts[] = implode(' · ', $stats);
}

$profileDesc = !empty($descParts) ? implode(' | ', $descParts) : $appDesc;

$profileUrl = $profile ? url('/@' . $profile->username) : url('/');
$profileAvatar = $profile && $profile->avatar ? $profile->avatar : url('/storage/avatars/default.jpg');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $profileTitle }}</title>
    <link rel="shortcut icon" type="image/png" href="{{ url('/favicon.png') }}"/>

    <meta name="description" content="{{ $profileDesc }}">
    <meta property="og:title" content="{{ $profileTitle }}" />
    <meta property="og:description" content="{{ $profileDesc }}" />
    <meta property="og:type" content="profile" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:logo" content="{{ url('/nav-logo.png') }}" />
    <meta property="og:url" content="{{ $profileUrl }}" />
    <meta property="og:image" content="{{ $profileAvatar }}" />
    @if($profile)<meta property="profile:username" content="{{ $profile->username }}" />@endif

    <meta name="twitter:card" content="summary" />
    <meta name="twitter:title" content="{{ $profileTitle }}" />
    <meta name="twitter:description" content="{{ $profileDesc }}" />
    <meta name="twitter:image" content="{{ $profileAvatar }}" />

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
