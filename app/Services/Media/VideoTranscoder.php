<?php

namespace App\Services\Media;

use Illuminate\Http\File;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoTranscoder
{
    public const WORKDIR_PREFIX = 'loops-media-';

    private const KNOWN_UNDECODABLE = ['', 'none', 'unknown', 'apac'];

    private static ?array $decodableCodecs = null;

    public function ffmpegBinary(): string
    {
        return (string) config('laravel-ffmpeg.ffmpeg.binaries', 'ffmpeg');
    }

    public function ffprobeBinary(): string
    {
        return (string) config('laravel-ffmpeg.ffprobe.binaries', 'ffprobe');
    }

    public function tempRoot(): string
    {
        $root = config('loops.media.transcode.temp_dir')
            ?: config('laravel-ffmpeg.temporary_files_root')
            ?: sys_get_temp_dir();

        return rtrim((string) $root, DIRECTORY_SEPARATOR);
    }

    public function makeWorkdir(string $label): string
    {
        $dir = $this->tempRoot().DIRECTORY_SEPARATOR.self::WORKDIR_PREFIX.Str::slug($label).'-'.Str::random(10);

        if (! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Unable to create transcode workdir: '.$dir);
        }

        return $dir;
    }

    public function cleanup(?string $dir): void
    {
        if ($dir && is_dir($dir) && str_contains(basename($dir), self::WORKDIR_PREFIX)) {
            Filesystem::deleteDirectory($dir);
        }
    }

    public function pruneStaleWorkdirs(int $olderThanSeconds = 7200): void
    {
        $pattern = $this->tempRoot().DIRECTORY_SEPARATOR.self::WORKDIR_PREFIX.'*';

        foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $dir) {
            $mtime = @filemtime($dir);

            if ($mtime && $mtime < (time() - $olderThanSeconds)) {
                Filesystem::deleteDirectory($dir);
            }
        }
    }

    public function download(string $disk, string $path, string $localPath): string
    {
        $in = Storage::disk($disk)->readStream($path);

        if (! is_resource($in)) {
            throw new \RuntimeException('Unable to open source stream: '.$path);
        }

        $out = fopen($localPath, 'wb');

        if (! $out) {
            fclose($in);
            throw new \RuntimeException('Unable to open local file for writing: '.$localPath);
        }

        try {
            stream_copy_to_stream($in, $out);
        } finally {
            fclose($in);
            fclose($out);
        }

        clearstatcache(true, $localPath);

        if (! is_file($localPath) || filesize($localPath) === 0) {
            throw new \RuntimeException('Downloaded source is empty: '.$path);
        }

        return $localPath;
    }

    public function upload(string $disk, string $localPath, string $destination): void
    {
        $dir = trim(dirname($destination), '.'.DIRECTORY_SEPARATOR);
        $stored = Storage::disk($disk)->putFileAs($dir, new File($localPath), basename($destination), 'public');

        if (! $stored) {
            throw new \RuntimeException('Failed to upload processed file to '.$destination);
        }
    }

    /**
     * @return array{format: array, streams: array}
     */
    public function probe(string $file, int $timeout = 60): array
    {
        $result = Process::timeout($timeout)->run([
            $this->ffprobeBinary(),
            '-v', 'error',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $file,
        ]);

        if (! $result->successful()) {
            throw new TranscodeException(
                'ffprobe failed (exit '.$result->exitCode().')',
                $result->errorOutput(),
                exitCode: $result->exitCode(),
            );
        }

        $json = json_decode($result->output(), true);

        if (! is_array($json) || empty($json['streams'])) {
            throw new TranscodeException('ffprobe returned no streams', $result->errorOutput(), permanent: true);
        }

        return [
            'format' => (array) ($json['format'] ?? []),
            'streams' => (array) $json['streams'],
        ];
    }

    public function decodableCodecs(): ?array
    {
        if (self::$decodableCodecs !== null) {
            return self::$decodableCodecs ?: null;
        }

        try {
            $result = Process::timeout(15)->run([$this->ffmpegBinary(), '-hide_banner', '-codecs']);
            self::$decodableCodecs = $result->successful()
                ? self::parseDecodableCodecs($result->output())
                : [];
        } catch (\Throwable $e) {
            self::$decodableCodecs = [];
        }

        return self::$decodableCodecs ?: null;
    }

    public static function parseDecodableCodecs(string $codecsOutput): array
    {
        $codecs = [];

        foreach (preg_split('/\R/', $codecsOutput) as $line) {
            // " DEVIL. h264   H.264 / AVC ..." -> first flag D means decoding supported
            if (preg_match('/^\s*D[E.][VASDT][A-Z.]{3}\s+(\S+)\s/', $line, $m)) {
                $codecs[$m[1]] = true;
            }
        }

        return $codecs;
    }

    /**
     * @return array{video: ?array, audio: ?array, undecodable_audio: bool}
     */
    public static function selectStreams(array $probe, ?array $decodable = null): array
    {
        $isDecodable = function (array $stream) use ($decodable): bool {
            $codec = strtolower((string) ($stream['codec_name'] ?? ''));

            $tag = strtolower((string) ($stream['codec_tag_string'] ?? ''));

            if (in_array($codec, self::KNOWN_UNDECODABLE, true) || $tag === 'apac') {
                return false;
            }

            return $decodable === null || isset($decodable[$codec]);
        };

        $video = null;
        $bestArea = -1;
        $audioCandidates = [];
        $sawAudio = false;

        foreach ($probe['streams'] ?? [] as $stream) {
            $type = $stream['codec_type'] ?? null;
            $disposition = (array) ($stream['disposition'] ?? []);

            if ($type === 'video') {
                if (! empty($disposition['attached_pic']) || ! $isDecodable($stream)) {
                    continue;
                }

                $area = (int) ($stream['width'] ?? 0) * (int) ($stream['height'] ?? 0);

                if ($area > $bestArea) {
                    $bestArea = $area;
                    $video = $stream;
                }
            }

            if ($type === 'audio') {
                $sawAudio = true;

                if ($isDecodable($stream) && (int) ($stream['channels'] ?? 1) > 0) {
                    $audioCandidates[] = $stream;
                }
            }
        }

        $audio = null;
        foreach ($audioCandidates as $candidate) {
            if (! empty($candidate['disposition']['default'])) {
                $audio = $candidate;
                break;
            }
        }
        $audio ??= $audioCandidates[0] ?? null;

        return [
            'video' => $video,
            'audio' => $audio,
            'undecodable_audio' => $sawAudio && $audio === null,
        ];
    }

    public static function parseRate(?string $rate): ?float
    {
        if (! $rate || ! str_contains($rate, '/')) {
            return is_numeric($rate) ? (float) $rate : null;
        }

        [$num, $den] = array_map('floatval', explode('/', $rate, 2));

        return $den > 0 && $num > 0 ? $num / $den : null;
    }

    public static function rotationOf(array $stream): int
    {
        foreach ((array) ($stream['side_data_list'] ?? []) as $sideData) {
            if (isset($sideData['rotation'])) {
                return abs((int) $sideData['rotation']) % 180;
            }
        }

        if (isset($stream['tags']['rotate'])) {
            return abs((int) $stream['tags']['rotate']) % 180;
        }

        return 0;
    }

    public static function plan(array $probe, array $settings, ?array $decodable = null): array
    {
        $streams = self::selectStreams($probe, $decodable);
        $video = $streams['video'];

        if (! $video) {
            throw new TranscodeException('No decodable video stream found', permanent: true);
        }

        $width = (int) ($video['width'] ?? 0);
        $height = (int) ($video['height'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            throw new TranscodeException('Could not determine video dimensions', permanent: true);
        }

        if (self::rotationOf($video) === 90) {
            [$width, $height] = [$height, $width];
        }

        // Never upscale: a 480p upload stays 480p instead of burning CPU on a 720p re-encode.
        $configuredShortEdge = max(2, (int) $settings['short_edge']);
        $sourceShortEdge = min($width, $height);
        $shortEdge = (int) (floor(min($configuredShortEdge, $sourceShortEdge) / 2) * 2);
        $shortEdge = max(2, $shortEdge);

        $scaleFactor = ($shortEdge / 720) ** 2;

        if ($height > $width) {
            $scale = "scale={$shortEdge}:-2:flags=lanczos";
            $baseKbps = 2500;
            $outWidth = $shortEdge;
            $outHeight = (int) round($height * ($shortEdge / $width) / 2) * 2;
        } elseif ($width > $height) {
            $scale = "scale=-2:{$shortEdge}:flags=lanczos";
            $baseKbps = 3000;
            $outWidth = (int) round($width * ($shortEdge / $height) / 2) * 2;
            $outHeight = $shortEdge;
        } else {
            $scale = "scale={$shortEdge}:{$shortEdge}:flags=lanczos";
            $baseKbps = 2500;
            $outWidth = $shortEdge;
            $outHeight = $shortEdge;
        }

        $maxKbps = max(300, (int) round($baseKbps * $scaleFactor));

        $maxFps = max(1, (int) ($settings['max_fps'] ?? 60));
        $sourceFps = self::parseRate($video['avg_frame_rate'] ?? null)
            ?? self::parseRate($video['r_frame_rate'] ?? null)
            ?? 30.0;

        $filters = [];

        if ($sourceFps > $maxFps + 0.5) {
            $filters[] = "fps={$maxFps}";
            $fps = $maxFps;
        } else {
            $fps = max(1, min((int) round($sourceFps), $maxFps));
        }

        $filters[] = $scale;
        $filters[] = 'format=yuv420p';

        return [
            'video_index' => (int) $video['index'],
            'audio_index' => $streams['audio'] ? (int) $streams['audio']['index'] : null,
            'undecodable_audio' => $streams['undecodable_audio'],
            'width' => $outWidth,
            'height' => $outHeight,
            'fps' => $fps,
            'source_fps' => $sourceFps,
            'max_kbps' => $maxKbps,
            'filters' => implode(',', $filters),
            'source_duration' => (float) ($probe['format']['duration'] ?? $video['duration'] ?? 0),
        ];
    }

    public static function buildTranscodeCommand(string $ffmpeg, string $input, string $output, array $plan, array $settings): array
    {
        $safeMode = (bool) ($settings['safe_mode'] ?? false);
        $preset = $safeMode ? (string) $settings['fallback_preset'] : (string) $settings['preset'];
        $maxKbps = (int) $plan['max_kbps'];

        $cmd = [
            $ffmpeg,
            '-hide_banner', '-nostdin', '-nostats', '-y',
            '-loglevel', 'warning',
            '-fflags', $safeMode ? '+genpts+discardcorrupt' : '+genpts',
            '-err_detect', 'ignore_err',
            '-i', $input,
            '-map', '0:'.$plan['video_index'],
        ];

        if ($plan['audio_index'] !== null) {
            $cmd = array_merge($cmd, ['-map', '0:'.$plan['audio_index']]);
        }

        $cmd = array_merge($cmd, [
            '-map_metadata', '-1',
            '-map_chapters', '-1',
            '-vf', $plan['filters'],
            '-c:v', 'libx264',
            '-preset', $preset,
            '-crf', (string) $settings['crf'],
            '-maxrate', $maxKbps.'k',
            '-bufsize', ($maxKbps * 2).'k',
            '-profile:v', 'high',
            '-level:v', '4.1',
            '-pix_fmt', 'yuv420p',
        ]);

        if (! empty($settings['advanced']) && ! $safeMode) {
            $fps = (int) $plan['fps'];
            $cmd = array_merge($cmd, [
                '-g', (string) ($fps * 2),
                '-keyint_min', (string) $fps,
                '-x264-params', 'aq-mode=3:aq-strength=0.9:psy-rd=1.00,0.15:ref=4:bframes=3',
                '-color_primaries', 'bt709',
                '-color_trc', 'bt709',
                '-colorspace', 'bt709',
            ]);
        } else {
            $cmd = array_merge($cmd, ['-nal-hrd', 'vbr', '-tune', 'film']);
        }

        if ($plan['audio_index'] !== null) {
            $cmd = array_merge($cmd, [
                '-c:a', 'aac',
                '-b:a', ((int) $settings['audio_kbps']).'k',
                '-ac', '2',
            ]);
        } else {
            $cmd[] = '-an';
        }

        $threads = $settings['threads'] ?? null;
        if ($threads) {
            $cmd = array_merge($cmd, ['-threads', (string) (int) $threads]);
        }

        return array_merge($cmd, [
            '-t', (string) (int) $settings['max_duration'],
            '-movflags', '+faststart',
            '-f', 'mp4',
            $output,
        ]);
    }

    public function transcode(string $input, string $output, array $plan, array $settings, int $timeout): void
    {
        $cmd = self::buildTranscodeCommand($this->ffmpegBinary(), $input, $output, $plan, $settings);

        $this->runFfmpeg($cmd, $timeout, 'transcode');

        clearstatcache(true, $output);

        if (! is_file($output) || filesize($output) === 0) {
            throw new TranscodeException('ffmpeg produced an empty output file');
        }
    }

    public static function buildFrameCommand(string $ffmpeg, string $input, string $output, int $videoIndex, float $atSeconds): array
    {
        return [
            $ffmpeg,
            '-hide_banner', '-nostdin', '-nostats', '-y',
            '-loglevel', 'error',
            '-ss', number_format(max(0, $atSeconds), 3, '.', ''),
            '-i', $input,
            '-map', '0:'.$videoIndex,
            '-map_metadata', '-1',
            '-frames:v', '1',
            '-q:v', '2',
            '-update', '1',
            '-f', 'image2',
            $output,
        ];
    }

    public function extractFrame(string $input, string $output, int $videoIndex, array $offsets = [0.0, 1.0, 2.5], int $timeout = 60): float
    {
        $lastError = null;

        foreach ($offsets as $offset) {
            @unlink($output);

            try {
                $this->runFfmpeg(self::buildFrameCommand($this->ffmpegBinary(), $input, $output, $videoIndex, $offset), $timeout, 'thumbnail');
            } catch (TranscodeException $e) {
                $lastError = $e;

                continue;
            }

            clearstatcache(true, $output);

            if (is_file($output) && filesize($output) > 0) {
                return $offset;
            }
        }

        throw $lastError ?? new TranscodeException('Unable to extract a thumbnail frame');
    }

    private function runFfmpeg(array $cmd, int $timeout, string $label): void
    {
        $timeout = max(30, $timeout);

        try {
            $result = Process::timeout($timeout)->run($cmd);
        } catch (\Illuminate\Process\Exceptions\ProcessTimedOutException $e) {
            throw new TranscodeException("ffmpeg {$label} timed out after {$timeout}s", '', previous: $e);
        }

        if (! $result->successful()) {
            throw new TranscodeException(
                "ffmpeg {$label} exited with code {$result->exitCode()}",
                $result->errorOutput()
            );
        }

        $stderr = trim($result->errorOutput());
        if ($stderr !== '' && config('logging.dev_log')) {
            Log::debug("ffmpeg {$label} warnings", ['stderr' => TranscodeException::tail($stderr)]);
        }
    }
}
