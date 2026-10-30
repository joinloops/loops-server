<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VideoScheduleService
{
    public const STATUS_LIVE = 2;

    public const MIN_LEAD_SECONDS = 900;

    public const MAX_HORIZON_DAYS = 30;

    public const MAX_PENDING = 10;

    public const MIN_GAP_SECONDS = 300;

    public const CLAIM_TIMEOUT_MINUTES = 15;

    public function schedule(Video $video, Carbon $at): Video
    {
        $at = $at->clone()->utc()->startOfMinute();

        $this->assertValidTime($at);
        $this->assertWithinLimits($video, $at);

        $video->forceFill([
            'publish_state' => Video::PUBLISH_STATE_SCHEDULED,
            'scheduled_at' => $at,
            'publishing_at' => null,
            'publish_failure_reason' => null,
        ])->save();

        return $video->refresh();
    }

    public function publishNow(Video $video): Video
    {
        $video->forceFill([
            'publish_state' => Video::PUBLISH_STATE_SCHEDULED,
            'scheduled_at' => now(),
            'publishing_at' => null,
        ])->save();

        $video = $video->refresh();

        if ($this->claim($video->id)) {
            $this->publish($video->refresh());
        }

        return $video->refresh();
    }

    public function claim(int $videoId): bool
    {
        return DB::table('videos')
            ->where('id', $videoId)
            ->where('publish_state', Video::PUBLISH_STATE_SCHEDULED)
            ->where('status', '!=', self::STATUS_LIVE)
            ->whereNull('ap_published_at')
            ->whereNull('publishing_at')
            ->update(['publishing_at' => now()]) === 1;
    }

    public function releaseClaim(int $videoId): void
    {
        DB::table('videos')->where('id', $videoId)->update(['publishing_at' => null]);
    }

    public function releaseStaleClaims(): int
    {
        return DB::table('videos')
            ->where('publish_state', Video::PUBLISH_STATE_SCHEDULED)
            ->whereNotNull('publishing_at')
            ->where('publishing_at', '<', now()->subMinutes(self::CLAIM_TIMEOUT_MINUTES))
            ->update(['publishing_at' => null]);
    }

    public function publish(Video $video): bool
    {
        if ($video->isLive()) {
            return false;
        }

        if (! $this->ownerCanPublish($video) || ! $this->isTranscodeReady($video)) {
            $this->releaseClaim($video->id);

            return false;
        }

        if (app(CollabService::class)->blocksPublish($video)) {
            $this->releaseClaim($video->id);

            return false;
        }

        $video->forceFill([
            'publish_state' => Video::PUBLISH_STATE_PUBLISHED,
            'status' => self::STATUS_LIVE,
            'ap_published_at' => now(),
            'publishing_at' => null,
            'publish_failure_reason' => null,
        ])->save();

        $this->federate($video->refresh());

        return true;
    }

    public function deleteScheduled(Video $video): bool
    {
        if ($video->isLive() || $video->publishing_at) {
            return false;
        }

        $video->forceFill([
            'publish_state' => Video::PUBLISH_STATE_DRAFT,
            'scheduled_at' => null,
            'publishing_at' => null,
        ])->save();

        $this->purgeMedia($video);

        $video->delete();

        return true;
    }

    protected function purgeMedia(Video $video): void
    {
        try {
            if (Storage::exists($video->vid)) {
                Storage::delete($video->vid);
            }
            $s3Path = 'videos/'.$video->profile_id.'/'.$video->id.'/';
            if (Storage::disk('s3')->exists($s3Path)) {
                Storage::disk('s3')->deleteDirectory($s3Path);
            }
        } catch (\Throwable $e) {
            Log::error('[schedule] media cleanup failed', [
                'video_id' => $video->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function markFailed(Video $video, ?string $reason = null): void
    {
        $video->forceFill([
            'publish_state' => Video::PUBLISH_STATE_FAILED,
            'publishing_at' => null,
            'publish_failure_reason' => $reason ? substr($reason, 0, 191) : null,
        ])->save();
    }

    public function shouldHoldForSchedule(Video $video): bool
    {
        return $video->publish_state === Video::PUBLISH_STATE_SCHEDULED
            && $video->scheduled_at
            && now()->parse($video->scheduled_at)->isFuture();
    }

    public function pendingCount(Profile $profile): int
    {
        return Video::where('profile_id', $profile->id)
            ->where('publish_state', Video::PUBLISH_STATE_SCHEDULED)
            ->where('status', '!=', self::STATUS_LIVE)
            ->count();
    }

    public function canSchedule(Profile $profile): bool
    {
        return $this->pendingCount($profile) < self::MAX_PENDING;
    }

    protected function assertValidTime(Carbon $at): void
    {
        if ($at->lt(now()->addSeconds(self::MIN_LEAD_SECONDS))) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Scheduled time must be at least 15 minutes from now.',
            ]);
        }

        if ($at->gt(now()->addDays(self::MAX_HORIZON_DAYS))) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Scheduled time cannot be more than '.self::MAX_HORIZON_DAYS.' days from now.',
            ]);
        }
    }

    protected function assertWithinLimits(Video $video, Carbon $at): void
    {
        $pending = Video::where('profile_id', $video->profile_id)
            ->where('publish_state', Video::PUBLISH_STATE_SCHEDULED)
            ->where('status', '!=', self::STATUS_LIVE)
            ->where('id', '!=', $video->id);

        if ((clone $pending)->count() >= self::MAX_PENDING) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'You can only have '.self::MAX_PENDING.' scheduled posts at a time.',
            ]);
        }

        $collides = (clone $pending)
            ->whereBetween('scheduled_at', [
                $at->clone()->subSeconds(self::MIN_GAP_SECONDS),
                $at->clone()->addSeconds(self::MIN_GAP_SECONDS),
            ])
            ->exists();

        if ($collides) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'You already have a post scheduled around that time.',
            ]);
        }
    }

    protected function isTranscodeReady(Video $video): bool
    {
        return (bool) $video->has_processed
            && $video->processing_status !== 'failed';
    }

    protected function ownerCanPublish(Video $video): bool
    {
        $profile = $video->profile;

        if (! $profile || $profile->status != 1 || $profile->can_upload != true) {
            return false;
        }

        return ! optional($profile->user)->delete_after;
    }

    protected function federate(Video $video): void
    {
        try {
            app(FederationDispatcher::class)->dispatchVideoCreation($video);
            $video->federated_at = now();
            $video->ap_published_at = now();
            $video->save();

        } catch (\Throwable $e) {
            Log::error('[schedule] federation dispatch failed', [
                'video_id' => $video->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
