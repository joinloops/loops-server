<?php

namespace App\Jobs\Federation;

use App\Federation\Coauthors;
use App\Models\Profile;
use App\Models\Video;
use App\Models\VideoCollaboration;
use App\Services\ActivityPubService;
use App\Services\CollabService;
use App\Services\SanitizeService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class VerifyRemoteCoauthorsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 120;

    public $uniqueFor = 600;

    public function __construct(
        public string $videoId,
        public string $noteId,
        public string $noteActor,
        public array $claims,
        public bool $authoritative = true,
    ) {
        $this->onQueue('remote-video');
    }

    public function uniqueId(): string
    {
        return 'verify-coauthors-'.$this->videoId.'-'.sha1(json_encode($this->claims));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(ActivityPubService $activityPub, SanitizeService $sanitize, CollabService $collabs): void
    {
        $video = Video::find($this->videoId);

        if (! $video || $video->is_local || $video->ap_id !== $this->noteId) {
            return;
        }

        $author = $video->profile;

        if (! $author || $author->local || $author->getActorId() !== $this->noteActor) {
            return;
        }

        $noteHost = $this->host($this->noteId);

        if (! $noteHost || $noteHost !== $this->host($this->noteActor)) {
            return;
        }

        $max = max(0, (int) config('loops.collabs.max_remote_coauthors', 3));
        $verified = [];

        foreach (array_slice($this->claims, 0, $max) as $claim) {
            $url = is_string($claim) ? $claim : (is_array($claim) ? ($claim['id'] ?? null) : null);

            if (! is_string($url) || ! $sanitize->url($url, true) || $this->host($url) !== $noteHost) {
                continue;
            }

            $profile = $this->verify($activityPub, $url, $noteHost, $author);

            if (! $profile) {
                continue;
            }

            VideoCollaboration::updateOrCreate(
                ['video_id' => $video->id, 'profile_id' => $profile->id],
                [
                    'inviter_profile_id' => $author->id,
                    'state' => VideoCollaboration::STATE_APPROVED,
                    'is_local' => false,
                    'auto_approved' => false,
                    'attestation_url' => $url,
                    'responded_at' => now(),
                    'expires_at' => null,
                ]
            );

            $verified[] = (string) $profile->id;
        }

        if ($this->authoritative) {
            VideoCollaboration::where('video_id', $video->id)
                ->where('is_local', false)
                ->when(! empty($verified), fn ($q) => $q->whereNotIn('profile_id', $verified))
                ->delete();
        }

        $collabs->forgetVideo($video->id);
    }

    protected function verify(ActivityPubService $activityPub, string $url, string $noteHost, Profile $author): ?Profile
    {
        $attestation = $this->fetch($activityPub, $url);

        if (! $attestation) {
            $this->debug('fetch failed', $url);

            return null;
        }

        $checks = [
            'type' => ($attestation['type'] ?? null) === Coauthors::AUTHORIZATION_TYPE,
            'id' => ($attestation['id'] ?? null) === $url,
            'interactingObject' => ($attestation['interactingObject'] ?? null) === $this->noteId,
            'interactionTarget' => ($attestation['interactionTarget'] ?? null) === $this->noteActor,
            'attributedTo' => is_string($attestation['attributedTo'] ?? null)
                && $this->host($attestation['attributedTo']) === $noteHost,
        ];

        if (in_array(false, $checks, true)) {
            $this->debug('attestation check failed: '.implode(',', array_keys(array_filter($checks, fn ($ok) => ! $ok))), $url);

            return null;
        }

        $profile = app(Profile::class)->findOrCreateFromUrl($attestation['attributedTo']);

        if (! $profile || $profile->local || (string) $profile->id === (string) $author->id || (int) $profile->status !== 1) {
            $this->debug('coauthor did not resolve to a usable remote profile', $url);

            return null;
        }

        if ($profile->getActorId() !== $attestation['attributedTo']) {
            $this->debug('coauthor actor id mismatch', $url);

            return null;
        }

        return $profile;
    }

    protected function fetch(ActivityPubService $activityPub, string $url): ?array
    {
        $response = $activityPub->get($url, [], true, true, true, true);

        if (! $response) {
            return null;
        }

        if (is_array($response)) {
            return $response;
        }

        if (is_object($response) && method_exists($response, 'json')) {
            $json = $response->json();

            return is_array($json) ? $json : null;
        }

        if (is_string($response)) {
            $json = json_decode($response, true);

            return is_array($json) ? $json : null;
        }

        return null;
    }

    protected function host(?string $url): ?string
    {
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;

        return $host ? strtolower($host) : null;
    }

    protected function debug(string $message, string $url): void
    {
        if (config('logging.dev_log')) {
            Log::info('VerifyRemoteCoauthors: '.$message, ['video_id' => $this->videoId, 'url' => $url]);
        }
    }
}
