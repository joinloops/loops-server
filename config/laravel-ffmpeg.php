<?php

return [
    'ffmpeg' => [
        'binaries' => env('FFMPEG_BINARIES', 'ffmpeg'),

        'threads' => env('FFMPEG_THREADS', 12),   // set to false to disable the default 'threads' filter
    ],

    'ffprobe' => [
        'binaries' => env('FFPROBE_BINARIES', 'ffprobe'),
    ],

    'timeout' => env('FFMPEG_TIMEOUT', 3600),   // set to false to disable the default 'threads' filter

    'log_channel' => env('FFMPEG_LOG_CHANNEL', false),

    'temporary_files_root' => env('FFMPEG_TEMPORARY_FILES_ROOT', sys_get_temp_dir()),

    'temporary_files_encrypted_hls' => env('FFMPEG_TEMPORARY_ENCRYPTED_HLS', env('FFMPEG_TEMPORARY_FILES_ROOT', sys_get_temp_dir())),
];
