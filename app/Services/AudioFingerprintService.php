<?php

namespace App\Services;

use App\Models\VideoSound;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class AudioFingerprintService
{
    public const CAN_FINGER_KEY = 'loops:s:audiofinger:can';

    public const MAX_FP_SECONDS = 120;

    public function processVideo(
        string $localMediaPath,
        int $videoId,
        string|int $profileId,
        ?int $duration,
        bool $allowReuse
    ): ?VideoSound {
        if (! config('loops.chromaprint.enabled')) {
            return null;
        }

        if (! $this->canFingerprint()) {
            return null;
        }

        $fingerprint = $this->generateFingerprint($localMediaPath, $duration);

        // @phpstan-ignore-next-line
        if (! is_string($fingerprint) || $fingerprint === '') {
            throw new \RuntimeException('Empty fingerprint generated');
        }

        $hash = hash('sha256', $fingerprint);

        $existing = $this->findExactMatch($hash);
        if ($existing) {
            $existing->incrementUsage();
            return $existing;
        }

        return Cache::lock("sound:fingerprint:{$hash}", 10)->block(5, function () use (
            $hash, $fingerprint, $duration, $videoId, $profileId, $allowReuse
        ) {
            if ($found = $this->findExactMatch($hash)) {
                return $found;
            }

            try {
                return VideoSound::create([
                    'fingerprint' => $fingerprint,
                    'fingerprint_hash' => $hash,
                    'duration' => $duration,
                    'original_video_id' => $videoId,
                    'profile_id' => (string) $profileId,
                    'is_original' => true,
                    'allow_reuse' => $allowReuse,
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    return $this->findExactMatch($hash);
                }
                throw $e;
            }
        });
    }

    public function canFingerprint(bool $refresh = false): bool
    {
        if (! config('loops.chromaprint.enabled')) {
            return false;
        }

        if ($refresh) {
            Cache::forget(self::CAN_FINGER_KEY);
        }

        return Cache::remember(self::CAN_FINGER_KEY, now()->addHours(24), function () {
            $process = new Process(['fpcalc', '-version']);
            $process->setTimeout(5);
            $process->run();

            return $process->isSuccessful();
        });
    }

    public function generateFingerprint(string $localPath, ?int $duration = null): string
    {
        if (str_starts_with($localPath, '-')) {
            throw new \InvalidArgumentException('Invalid media path');
        }

        $seconds = $duration ? min((int) $duration, self::MAX_FP_SECONDS) : self::MAX_FP_SECONDS;

        $process = new Process([
            'fpcalc',
            '-json',
            '-length', (string) $seconds,
            $localPath,
        ]);

        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = json_decode($process->getOutput(), true);

        if (! is_array($output) || empty($output['fingerprint'])) {
            Log::warning('fpcalc returned unexpected output', [
                'out' => $process->getOutput(),
                'err' => $process->getErrorOutput(),
            ]);

            throw new \RuntimeException('Failed to generate fingerprint');
        }

        return (string) $output['fingerprint'];
    }

    protected function findExactMatch(string $fingerprintHash): ?VideoSound
    {
        return VideoSound::where('fingerprint_hash', $fingerprintHash)->first();
    }

    protected function isUniqueConstraintViolation(QueryException $e): bool
    {
        $info = $e->errorInfo;

        return isset($info[0], $info[1]) && $info[0] === '23000' && (int) $info[1] === 1062;
    }
}
