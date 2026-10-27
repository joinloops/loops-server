<?php

namespace App\Rules;

use App\Services\Media\TranscodeException;
use App\Services\Media\VideoTranscoder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class HasVideoStream implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid() || ! $value->getRealPath()) {
            $fail('The :attribute upload is incomplete. Please try again.');

            return;
        }

        $transcoder = app(VideoTranscoder::class);

        try {
            $probe = $transcoder->probe($value->getRealPath(), 30);
        } catch (TranscodeException $e) {
            $fail('This file could not be read as a video. Please export it as an MP4 and try again.');

            return;
        } catch (\Throwable $e) {
            Log::warning('Upload video probe unavailable, skipping stream check', ['error' => $e->getMessage()]);

            return;
        }

        $video = VideoTranscoder::selectStreams($probe, $transcoder->decodableCodecs())['video'];

        if (! $video || (int) ($video['width'] ?? 0) <= 0 || (int) ($video['height'] ?? 0) <= 0) {
            $fail('This file has no video track, only audio. If it was converted in your browser, try a different browser or upload an H.264 MP4.');
        }
    }
}
