<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Profile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type DmMediaAttributes from DirectMessageService
 */
class DmInboundService
{
    public const PUBLIC_URIS = [
        'https://www.w3.org/ns/activitystreams#Public',
        'as:Public',
        'Public',
    ];

    protected const ALLOWED_MEDIA_TYPES = [
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/gif' => 'image',
        'image/webp' => 'image',
        'video/mp4' => 'video',
    ];

    public const MAX_MEDIA_ATTACHMENTS = 4;

    public function __construct(
        protected DirectMessageService $dms,
        protected SanitizeService $sanitizer
    ) {}

    public function isDirectNote(array $activity, ?Profile $actor = null): bool
    {
        $object = $activity['object'] ?? null;

        if (! is_array($object) || ($object['type'] ?? null) !== 'Note') {
            return false;
        }

        $to = Arr::wrap($object['to'] ?? []);
        $cc = Arr::wrap($object['cc'] ?? []);
        $audience = array_merge($to, $cc);

        if (empty($to) || empty($audience)) {
            return false;
        }

        foreach ($audience as $uri) {
            if (! is_string($uri)) {
                return false;
            }

            if (in_array($uri, self::PUBLIC_URIS, true)) {
                return false;
            }

            if ($actor && $uri === $actor->getFollowersUrl()) {
                return false;
            }

            if (str_ends_with(rtrim($uri, '/'), '/followers')) {
                return false;
            }
        }

        return true;
    }

    public function handleCreate(array $activity, Profile $actor): ?Message
    {
        $object = $activity['object'];

        $actorUri = rtrim($actor->getActorId(), '/');

        $recipientUris = collect(array_merge(
            Arr::wrap($object['to'] ?? []),
            Arr::wrap($object['cc'] ?? [])
        ))
            ->filter(fn ($uri) => is_string($uri) && $uri !== '')
            ->map(fn (string $uri) => rtrim($uri, '/'))
            ->unique()
            ->reject(fn (string $uri) => $uri === $actorUri)
            ->values();

        if ($recipientUris->isEmpty()) {
            return null;
        }

        $max = (int) config('loops.dm.groups.max_participants', 12);

        if ($recipientUris->count() + 1 > $max) {
            if (config('logging.dev_log')) {
                Log::info('Dropped inbound DM: too many recipients', [
                    'actor' => $actor->username,
                    'object_id' => $object['id'] ?? 'unknown',
                    'recipient_count' => $recipientUris->count(),
                ]);
            }

            return null;
        }

        $recipients = $recipientUris
            ->map(fn (string $uri) => $this->resolveRecipient($uri))
            ->filter()
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return null;
        }

        $payload = [
            'object_uri' => $object['id'],
            'context_uri' => $this->extractContext($object),
            'body' => $this->stripLeadingMentions($this->extractBody($object), $recipients),
            'media' => $this->extractMedia($object, $actor),
        ];

        return $this->dms->receive($actor, $recipients, $payload);
    }

    /**
     * Mastodon prefixes DM content with mentions of the addressed accounts.
     * Strip any run of leading mentions that match conversation participants,
     * falling back to the original body when nothing but mentions remains.
     *
     * @param  Collection<int, Profile>  $recipients
     */
    protected function stripLeadingMentions(?string $body, Collection $recipients): ?string
    {
        if ($body === null) {
            return null;
        }

        $names = $recipients
            ->map(fn (Profile $recipient) => preg_quote($recipient->username, '/'))
            ->filter()
            ->implode('|');

        if ($names === '') {
            return $body;
        }

        $pattern = '/^(?:@[[:space:]]?(?:'.$names.')(?:@[A-Za-z0-9\.\-]+)?[[:space:]]+)+/iu';
        $stripped = preg_replace($pattern, '', $body, 1);

        if (! is_string($stripped)) {
            return $body;
        }

        $stripped = trim($stripped);

        return $stripped === '' ? $body : $stripped;
    }

    public function handleDelete(array $activity, Profile $sender): bool
    {
        $object = $activity['object'] ?? null;
        $objectId = is_string($object) ? $object : (is_array($object) ? ($object['id'] ?? null) : null);

        if (! is_string($objectId) || ! $this->sameOrigin($objectId, $this->senderUri($sender))) {
            return false;
        }

        $message = Message::with('conversation.participants.profile')
            ->where('ap_object_uri', $objectId)
            ->where('profile_id', $sender->id)
            ->first();

        if (! $message) {
            return false;
        }

        $this->dms->deleteMessage($message);

        return true;
    }

    public function handleUpdate(array $activity, Profile $sender): void
    {
        $object = $activity['object'] ?? null;
        $objectId = is_array($object) ? ($object['id'] ?? null) : null;

        if (! is_array($object) || ! is_string($objectId)) {
            return;
        }

        if (! $this->sameOrigin($objectId, $this->senderUri($sender))) {
            return;
        }

        $message = Message::where('ap_object_uri', $objectId)
            ->where('profile_id', $sender->id)
            ->first();

        if (! $message) {
            return;
        }

        $body = $this->htmlToText($object['content'] ?? null);

        if ($body === null && ! $message->media()->exists()) {
            return;
        }

        $message->forceFill([
            'body' => $body,
            'edited_at' => $this->parseDate($object['updated'] ?? null) ?? now(),
        ])->save();
        // deferred: attachment edits and a realtime update broadcast
    }

    protected function resolveRecipient(string $uri): ?Profile
    {
        if ($this->sanitizer->isLocalObject($uri)) {
            $match = $this->sanitizer->matchUrlTemplate(
                url: $uri,
                templates: ['/ap/users/{profileId}'],
                useAppHost: true,
                constraints: ['profileId' => '\d+']
            );

            if ($match && isset($match['profileId'])) {
                return Profile::where('local', true)
                    ->where('status', 1)
                    ->find($match['profileId']);
            }

            return null;
        }

        $known = Profile::where('uri', $uri)->first();

        if ($known) {
            return $known;
        }

        return $this->fetchRemoteProfile($uri);
    }

    protected function fetchRemoteProfile(string $uri): ?Profile
    {
        try {
            return app(Profile::class)->findOrCreateFromUrl($uri);
        } catch (\Exception $e) {
            if (config('logging.dev_log')) {
                Log::warning('Failed to resolve DM co-recipient', [
                    'uri' => $uri,
                    'error' => $e->getMessage(),
                ]);
            }

            return null;
        }
    }

    protected function extractContext(array $object): ?string
    {
        $context = $object['context'] ?? $object['conversation'] ?? null;

        if (is_array($context)) {
            $context = $context['id'] ?? null;
        }

        if (! is_string($context) || $context === '' || strlen($context) > 255) {
            return null;
        }

        return $context;
    }

    protected function extractBody(array $object): ?string
    {
        $content = $object['content'] ?? $object['summary'] ?? $object['name'] ?? null;

        if (! is_string($content) || $content === '') {
            return null;
        }

        $clean = $this->sanitizer->cleanHtmlWithSpacing($content);

        return $clean !== '' ? $clean : null;
    }

    /**
     * @return list<DmMediaAttributes>
     */
    protected function extractMedia(array $object, Profile $sender): array
    {
        $attachments = $object['attachment'] ?? [];

        if (! is_array($attachments)) {
            return [];
        }

        $media = [];

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $mediaType = $attachment['mediaType'] ?? null;

            if (! is_string($mediaType) || ! isset(self::ALLOWED_MEDIA_TYPES[$mediaType])) {
                continue;
            }

            $url = $attachment['url'] ?? null;

            if (is_array($url)) {
                $url = data_get($url, 'href') ?? data_get($url, '0.href') ?? data_get($url, '0');
            }

            if (! is_string($url) || ! $this->sanitizer->url($url, true)) {
                continue;
            }

            $preview = data_get($attachment, 'icon.url') ?? data_get($attachment, 'preview.url');

            if (! is_string($preview) || ! $this->sanitizer->url($preview, true)) {
                $preview = null;
            }

            $media[] = [
                'profile_id' => (int) $sender->id,
                'type' => self::ALLOWED_MEDIA_TYPES[$mediaType],
                'mime_type' => $mediaType,
                'remote_url' => $url,
                'preview_remote_url' => $preview,
                'width' => is_numeric($attachment['width'] ?? null) ? (int) $attachment['width'] : null,
                'height' => is_numeric($attachment['height'] ?? null) ? (int) $attachment['height'] : null,
                'blurhash' => is_string($attachment['blurhash'] ?? null)
                    ? (Str::limit($attachment['blurhash'], 64, '') ?: null)
                    : null,
                'description' => is_string($attachment['name'] ?? null)
                    ? (Str::limit($attachment['name'], 1500, '') ?: null)
                    : null,
            ];

            if (count($media) >= self::MAX_MEDIA_ATTACHMENTS) {
                break;
            }
        }

        return $media;
    }

    protected function htmlToText(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = trim($this->sanitizer->cleanHtmlWithSpacing($html));

        return $text === '' ? null : Str::limit($text, 5000);
    }

    protected function senderUri(Profile $sender): ?string
    {
        return $sender->uri ?? $sender->remote_url ?? null;
    }

    protected function sameOrigin(?string $a, ?string $b): bool
    {
        $hostA = strtolower((string) parse_url((string) $a, PHP_URL_HOST));
        $hostB = strtolower((string) parse_url((string) $b, PHP_URL_HOST));

        return $hostA !== '' && $hostA === $hostB;
    }

    protected function parseDate(mixed $value): ?\Illuminate\Support\Carbon
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
