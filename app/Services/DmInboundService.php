<?php

namespace App\Services;

use App\Models\DmMedia;
use App\Models\Message;
use App\Models\Profile;
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

    public const MAX_ATTACHMENTS = 4;

    public function __construct(
        protected DirectMessageService $dm,
        protected SanitizeService $sanitize
    ) {}

    public function isDirectNote(array $activity, ?Profile $actor = null): bool
    {
        $object = $activity['object'] ?? null;

        if (! is_array($object) || ($object['type'] ?? null) !== 'Note') {
            return false;
        }

        $recipients = $this->recipients($activity);

        if (! $recipients) {
            return false;
        }

        $followersUrl = $actor?->getFollowersUrl();

        foreach ($recipients as $uri) {
            if (in_array($uri, self::PUBLIC_URIS, true)) {
                return false;
            }

            if ($followersUrl && strcasecmp(rtrim($uri, '/'), rtrim($followersUrl, '/')) === 0) {
                return false;
            }

            if (str_ends_with(rtrim($uri, '/'), '/followers')) {
                return false;
            }
        }

        return true;
    }

    public function handleCreate(array $activity, Profile $sender): ?Message
    {
        $object = $activity['object'] ?? null;
        $objectId = is_array($object) ? ($object['id'] ?? null) : null;

        if (! is_array($object) || ! is_string($objectId)) {
            return null;
        }

        if (! $this->sameOrigin($objectId, $sender->remote_url)) {
            return null;
        }

        if (Message::withTrashed()->where('ap_object_uri', $objectId)->exists()) {
            return null;
        }

        [$locals, $unresolvedOthers] = $this->resolveRecipients($activity, $sender);

        if (count($locals) !== 1 || $unresolvedOthers > 0) {
            // todo
            return null;
        }

        $recipient = $locals[0];

        $body = $this->htmlToText($object['content'] ?? null);
        $media = $this->mapAttachments($object, $sender);

        if ($body === null && ! $media) {
            return null;
        }

        return $this->dm->receive($sender, $recipient, [
            'object_uri' => $objectId,
            'context_uri' => $this->stringOrNull($object['context'] ?? null)
                ?? $this->stringOrNull($object['conversation'] ?? null),
            'body' => $body,
            'media' => $media,
        ]);
    }

    public function handleDelete(array $activity, Profile $sender): bool
    {
        $object = $activity['object'] ?? null;
        $objectId = is_string($object) ? $object : (is_array($object) ? ($object['id'] ?? null) : null);

        if (! is_string($objectId) || ! $this->sameOrigin($objectId, $sender->uri)) {
            return false;
        }

        $message = Message::with('conversation.participants.profile')
            ->where('ap_object_uri', $objectId)
            ->where('profile_id', $sender->id)
            ->first();

        if (! $message) {
            return false;
        }

        $this->dm->deleteMessage($message);

        return true;
    }

    public function handleUpdate(array $activity, Profile $sender): void
    {
        $object = $activity['object'] ?? null;
        $objectId = is_array($object) ? ($object['id'] ?? null) : null;

        if (! is_array($object) || ! is_string($objectId)) {
            return;
        }

        if (! $this->sameOrigin($objectId, $sender->remote_url)) {
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

    protected function resolveRecipients(array $activity, Profile $sender): array
    {
        $locals = [];
        $unresolvedOthers = 0;

        foreach ($this->recipients($activity) as $uri) {
            if (in_array($uri, self::PUBLIC_URIS, true)) {
                continue;
            }

            if ($sender->remote_url && strcasecmp($uri, $sender->remote_url) === 0) {
                continue;
            }

            $local = $this->localProfileFromUri($uri);

            if ($local) {
                $locals[$local->id] = $local;
            } else {
                $unresolvedOthers++;
            }
        }

        return [array_values($locals), $unresolvedOthers];
    }

    protected function localProfileFromUri(string $uri): ?Profile
    {
        if (! $this->sanitize->isLocalObject($uri)) {
            return null;
        }

        $match = $this->sanitize->matchUrlTemplate(
            url: $uri,
            templates: ['/ap/users/{pId}'],
            useAppHost: true,
            constraints: ['pId' => '\d+']
        );

        if (! $match || ! isset($match['pId'])) {
            return null;
        }

        return Profile::whereNull('domain')
            ->where('status', 1)
            ->find($match['pId']);
    }

    /**
     * @return list<DmMediaAttributes>
     */
    protected function mapAttachments(array $object, Profile $sender): array
    {
        $attachments = $object['attachment'] ?? [];

        if (! is_array($attachments)) {
            return [];
        }

        if (array_is_list($attachments) === false) {
            $attachments = [$attachments];
        }

        $mapped = [];

        foreach (array_slice($attachments, 0, self::MAX_ATTACHMENTS) as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $url = $this->attachmentUrl($attachment);

            if (! $url) {
                continue;
            }

            $mime = $this->stringOrNull($attachment['mediaType'] ?? null);

            $mapped[] = [
                'profile_id' => $sender->id,
                'type' => DmMedia::typeFromMime($mime),
                'mime_type' => $mime,
                'remote_url' => $url,
                'width' => $this->intOrNull($attachment['width'] ?? null),
                'height' => $this->intOrNull($attachment['height'] ?? null),
                'blurhash' => Str::limit($this->stringOrNull($attachment['blurhash'] ?? null) ?? '', 64, '') ?: null,
                'description' => Str::limit($this->stringOrNull($attachment['name'] ?? null) ?? '', 1500, '') ?: null,
            ];
        }

        return $mapped;
    }

    protected function attachmentUrl(array $attachment): ?string
    {
        $url = $attachment['url'] ?? null;

        if (is_array($url)) {
            $first = collect($url)->first(fn ($u) => is_array($u) && is_string($u['href'] ?? null));
            $url = is_array($first) ? $first['href'] : (is_string($url[0] ?? null) ? $url[0] : null);
        }

        if (! is_string($url)) {
            return null;
        }

        return $this->sanitize->url($url, true) ? $url : null;
    }

    protected function recipients(array $activity): array
    {
        $object = is_array($activity['object'] ?? null) ? $activity['object'] : [];

        $sets = [
            $activity['to'] ?? [],
            $activity['cc'] ?? [],
            $activity['audience'] ?? [],
            $object['to'] ?? [],
            $object['cc'] ?? [],
            $object['audience'] ?? [],
        ];

        $out = [];

        foreach ($sets as $set) {
            foreach ((array) $set as $uri) {
                if (is_string($uri) && $uri !== '') {
                    $out[] = $uri;
                }
            }
        }

        return array_values(array_unique($out));
    }

    protected function htmlToText(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = trim($this->sanitize->cleanHtmlWithSpacing($html));

        return $text === '' ? null : Str::limit($text, 5000);
    }

    protected function sameOrigin(?string $a, ?string $b): bool
    {
        $hostA = strtolower((string) parse_url((string) $a, PHP_URL_HOST));
        $hostB = strtolower((string) parse_url((string) $b, PHP_URL_HOST));

        return $hostA !== '' && $hostA === $hostB;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
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
