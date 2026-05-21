<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="{{ $theme }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>Loops Embed</title>

    <link rel="preconnect" href="{{ config('filesystems.disks.s3.url') }}">

    @vite('resources/js/embed.js')
    @vite('resources/css/embed.css')
</head>
<body>
@php
    $username    = $video['account']['username'] ?? '';
    $displayName = $video['account']['display_name'] ?? $username;
    $avatar      = $video['account']['avatar'] ?? null;
    $accountUrl  = $video['account']['username'] ? url('/@' . $video['account']['username'] . '?refer=embed') : $video['url'];
    $caption     = $video['caption'] ?? '';
    $stats       = $video['stats'] ?? [];
    $audio       = $video['audio'] ?? null;

    $fmt = function ($n) {
        $n = (int) $n;
        if ($n >= 1_000_000) return rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.') . 'M';
        if ($n >= 1_000)     return rtrim(rtrim(number_format($n / 1_000, 1), '0'), '.') . 'K';
        return (string) $n;
    };

    $captionHtml = e($caption);
    $captionHtml = preg_replace_callback('/#([\p{L}0-9_]+)/u', function ($m) {
        return '<a href="' . e(url('/tag/' . $m[1])) . '" target="_blank" rel="noopener">#' . e($m[1]) . '</a>';
    }, $captionHtml);
    $captionHtml = preg_replace_callback('/@([A-Za-z0-9_\.]+)/', function ($m) {
        return '<a href="' . e(url('/@' . $m[1])) . '" target="_blank" rel="noopener">@' . e($m[1]) . '</a>';
    }, $captionHtml);
@endphp

<article class="embed-card">
    <div class="embed-player is-pristine"
         data-shortcode="{{ $shortcode }}"
         data-start="{{ $startTime }}"
         data-theme="{{ $theme }}"
         data-url="{{ $video['url'] }}">

        <div class="progress-bar" role="progressbar" aria-label="Video progress">
            <span class="progress-fill"></span>
        </div>

        <video
            id="player"
            playsinline
            preload="metadata"
            poster="{{ $video['media']['thumbnail'] }}"
            crossorigin="anonymous"
        >
            <source src="{{ $video['media']['src_url'] }}" type="video/mp4">
        </video>

        <button type="button" class="start-overlay" aria-label="Play video">
            <svg class="start-overlay__icon" viewBox="0 0 24 24" width="72" height="72" fill="white" aria-hidden="true">
                <path d="M8 5v14l11-7z"/>
            </svg>
        </button>

        <div class="player-controls" role="group" aria-label="Video controls">
            <button type="button" class="control-play" aria-label="Play">
                <svg class="icon-play"  viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                <svg class="icon-pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            </button>

            <button type="button" class="control-mute" aria-label="Mute">
                <svg class="icon-volume-on"  viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                <svg class="icon-volume-off" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.17v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
            </button>

            <button type="button" class="control-fullscreen" aria-label="Enter fullscreen">
                <svg class="icon-fs-enter" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
                <svg class="icon-fs-exit"  viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/></svg>
            </button>
        </div>

        <a href="{{ $video['url'] }}" target="_blank" rel="noopener" class="brand-mark" aria-label="Open on Loops">
            <svg viewBox="20 20 120 120" fill="currentColor" aria-hidden="true"><path d="M134.307,84.391c-1.888,3.034-4.55,5.558-7.64,7.348-5.749,3.327-5.476,3.157-13.304,7.681,0,0-40.363,23.304-40.363,23.304-2.867,1.656-6.471,1.673-9.334-.001-2.922-1.686-4.666-4.708-4.666-8.082V45.978c-2.154-1.066-4.725-.983-6.833.247-2.296,1.326-3.667,3.7-3.667,6.352v61.969c.007,4.628,1.591,9.181,4.598,12.733,6.002,7.339,17.054,9.408,25.235,4.49,0,0,47.334-27.328,47.334-27.328,7.591-4.382,11.317-13.119,9.478-21.512-.26.499-.539.987-.838,1.463Z"/><path d="M56.65,30.773c3.572.118,7.088,1.162,10.183,2.942,5.755,3.315,5.472,3.164,13.304,7.681,0,0,40.363,23.303,40.363,23.303,2.868,1.656,4.685,4.768,4.666,8.084,0,3.374-1.744,6.395-4.666,8.082l-59.465,34.332c.154,2.398,1.511,4.584,3.63,5.794,2.296,1.325,5.038,1.326,7.334,0l53.667-30.984c4.005-2.32,7.156-5.968,8.729-10.348,3.355-8.868-.379-19.473-8.729-24.099,0,0-47.334-27.328-47.334-27.328-7.59-4.383-17.02-3.242-23.369,2.548.562-.024,1.124-.027,1.686-.005Z"/><path d="M49.044,124.835c-1.684-3.152-2.538-6.719-2.544-10.29-.006-6.642.004-6.321,0-15.362,0,0,0-46.607,0-46.607,0-3.311,1.786-6.441,4.668-8.083,2.921-1.687,6.41-1.687,9.332,0l59.465,34.332c2-1.332,3.214-3.601,3.203-6.041,0-2.651-1.371-5.026-3.667-6.351l-53.667-30.984c-4.011-2.308-8.747-3.213-13.326-2.385-9.357,1.529-16.674,10.065-16.506,19.609,0,0,0,54.656,0,54.656,0,8.765,5.702,16.36,13.891,18.964-.302-.475-.585-.96-.848-1.458Z"/></svg>
        </a>

        <aside class="player-actions">
            <a href="{{ $accountUrl }}" target="_blank" rel="noopener" class="action action-avatar" aria-label="{{ '@' . $username }}">
                @if($avatar)
                    <img src="{{ $avatar }}" alt="" loading="lazy">
                @else
                    <span class="avatar-fallback">{{ strtoupper(substr($username, 0, 1) ?: 'L') }}</span>
                @endif
                <span class="follow-badge" aria-hidden="true">+</span>
            </a>

            <a href="{{ $video['url'] }}?referer=embed" target="_blank" rel="noopener" class="action action-like" aria-label="Like">
                <span class="action-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M305 151.1L320 171.8L335 151.1C360 116.5 400.2 96 442.9 96C516.4 96 576 155.6 576 229.1L576 231.7C576 343.9 436.1 474.2 363.1 529.9C350.7 539.3 335.5 544 320 544C304.5 544 289.2 539.4 276.9 529.9C203.9 474.2 64 343.9 64 231.7L64 229.1C64 155.6 123.6 96 197.1 96C239.8 96 280 116.5 305 151.1z"/></svg>
                </span>
                <span class="action-count">{{ $fmt($video['likes'] ?? 0) }}</span>
            </a>

            <a href="{{ $video['url'] }}" target="_blank" rel="noopener" class="action" aria-label="Comments">
                <span class="action-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor"  viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M576 304C576 436.5 461.4 544 320 544C282.9 544 247.7 536.6 215.9 523.3L97.5 574.1C88.1 578.1 77.3 575.8 70.4 568.3C63.5 560.8 62 549.8 66.8 540.8L115.6 448.6C83.2 408.3 64 358.3 64 304C64 171.5 178.6 64 320 64C461.4 64 576 171.5 576 304z"/></svg>
                </span>
                <span class="action-count">{{ $fmt($video['comments'] ?? 0) }}</span>
            </a>

            <a href="{{ $video['url'] }}" target="_blank" rel="noopener" class="action action-share" aria-label="Share">
                <span class="action-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 640 640"><!--!Font Awesome Pro v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2026 Fonticons, Inc.--><path d="M371.8 82.4C359.8 87.4 352 99 352 112L352 192L240 192C142.8 192 64 270.8 64 368C64 481.3 145.5 531.9 164.2 542.1C166.7 543.5 169.5 544 172.3 544C183.2 544 192 535.1 192 524.3C192 516.8 187.7 509.9 182.2 504.8C172.8 496 160 478.4 160 448.1C160 395.1 203 352.1 256 352.1L352 352.1L352 432.1C352 445 359.8 456.7 371.8 461.7C383.8 466.7 397.5 463.9 406.7 454.8L566.7 294.8C579.2 282.3 579.2 262 566.7 249.5L406.7 89.5C397.5 80.3 383.8 77.6 371.8 82.6z"/></svg>
                </span>
                <span class="action-count">{{ $fmt($video['shares'] ?? 0) }}</span>
            </a>

            @if($audio)
                <a href="{{ $audio['url'] ?? $video['url'] }}" target="_blank" rel="noopener" class="action action-sound" aria-label="View sound">
                    @if(!empty($audio['cover']))
                        <img src="{{ $audio['cover'] }}" alt="" loading="lazy">
                    @else
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                    @endif
                </a>
            @endif
        </aside>

    </div>

    <a href="{{ $video['url'] }}" target="_blank" rel="noopener" class="watch-cta">
        <span class="watch-cta__label">Watch more on Loops</span>
        <span class="watch-cta__button">Watch now</span>
    </a>

    <footer class="embed-meta">
        <a href="{{ $accountUrl }}" target="_blank" rel="noopener" class="meta-handle">{{ '@' . $username }}</a>

        @if($caption)
            <div class="meta-caption-wrap">
                <p class="meta-caption">{!! $captionHtml !!}</p>
                <a href="{{ $video['url'] }}?referer=embed"
                target="_blank" rel="noopener"
                class="meta-caption-more" hidden>Read more</a>
            </div>
        @endif

        @if($audio)
            <a href="{{ $audio['url'] ?? $video['url'] }}" target="_blank" rel="noopener" class="meta-sound">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="14" height="14"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                <span>{{ $audio['title'] ?? ('original sound - ' . ($displayName ?: $username)) }}</span>
            </a>
        @endif
    </footer>
</article>
</body>
</html>
