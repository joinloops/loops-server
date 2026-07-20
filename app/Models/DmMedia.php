<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $message_id
 * @property int $profile_id
 * @property string $type
 * @property string|null $mime_type
 * @property string|null $remote_url
 * @property string|null $preview_remote_url
 * @property string|null $path
 * @property string|null $preview_path
 * @property int|null $width
 * @property int|null $height
 * @property string|null $blurhash
 * @property int|null $size
 * @property string|null $description
 * @property string|null $provider
 * @property string|null $external_id
 * @property int $order
 * @property \Illuminate\Support\Carbon|null $cached_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Message|null $message
 * @property-read \App\Models\Profile|null $profile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereBlurhash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereCachedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia wherePreviewPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia wherePreviewRemoteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereRemoteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmMedia whereWidth($value)
 *
 * @mixin \Eloquent
 */
class DmMedia extends Model
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPE_GIF = 'gif';

    public const TYPE_AUDIO = 'audio';

    public const TYPE_UNKNOWN = 'unknown';

    public const PROVIDER_KLIPY = 'klipy';

    protected $table = 'dm_media';

    protected $guarded = [];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
        'order' => 'integer',
        'cached_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function url(): string
    {
        if ($this->path && $this->cached_at) {
            return Storage::url($this->path);
        }

        return $this->remote_url;
    }

    public function previewUrl(): ?string
    {
        if ($this->preview_path && $this->cached_at) {
            return Storage::url($this->preview_path);
        }

        return $this->preview_remote_url;
    }

    public function toEntity(): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->type,
            'mime_type' => $this->mime_type,
            'url' => $this->url(),
            'preview_url' => $this->previewUrl(),
            'width' => $this->width,
            'height' => $this->height,
            'blurhash' => $this->blurhash,
            'description' => $this->description,
        ];
    }

    public function toApAttachment(): array
    {
        $attachment = [
            'type' => 'Document',
            'mediaType' => $this->mime_type,
            'url' => $this->url(),
        ];

        if ($this->description) {
            $attachment['name'] = $this->description;
        }

        if ($this->width && $this->height) {
            $attachment['width'] = $this->width;
            $attachment['height'] = $this->height;
        }

        if ($this->blurhash) {
            $attachment['blurhash'] = $this->blurhash;
        }

        return $attachment;
    }

    public static function typeFromMime(?string $mime): string
    {
        if (! $mime) {
            return self::TYPE_UNKNOWN;
        }

        if ($mime === 'image/gif') {
            return self::TYPE_GIF;
        }

        return match (true) {
            str_starts_with($mime, 'image/') => self::TYPE_IMAGE,
            str_starts_with($mime, 'video/') => self::TYPE_VIDEO,
            str_starts_with($mime, 'audio/') => self::TYPE_AUDIO,
            default => self::TYPE_UNKNOWN,
        };
    }
}
