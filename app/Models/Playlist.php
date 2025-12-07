<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $name
 * @property string|null $description
 * @property string $visibility
 * @property string|null $cover_image
 * @property-read int|null $videos_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Profile $profile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Video> $videos
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereCoverImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereVideosCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereVisibility($value)
 *
 * @mixin \Eloquent
 */
class Playlist extends Model
{
    protected $fillable = [
        'profile_id',
        'name',
        'description',
        'visibility',
        'cover_image',
        'videos_count',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * @return BelongsToMany<Video, $this>
     */
    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function addVideo(Video $video, ?int $position = null): void
    {
        $video->playlists()->detach();

        if ($position === null) {
            $maxPosition = $this->videos()->max('position') ?? -1;
            $position = $maxPosition + 1;
        }

        $this->videos()->attach($video->id, ['position' => $position]);
        $this->updateCoverImage();
        $this->updateProfileHasPlaylists();
    }

    public function removeVideo(Video $video): void
    {
        $this->videos()->detach($video->id);
        $this->reorderPositions();
        $this->updateCoverImage();
        $this->updateProfileHasPlaylists();
    }

    public function reorderVideos(array $videoIds): void
    {
        foreach ($videoIds as $position => $videoId) {
            $this->videos()->updateExistingPivot($videoId, ['position' => $position]);
        }

        $this->updateCoverImage();
    }

    protected function reorderPositions(): void
    {
        $videos = $this->videos()->orderBy('position')->get();

        foreach ($videos as $index => $video) {
            $this->videos()->updateExistingPivot($video->getKey(), ['position' => $index]);
        }
    }

    protected function updateCoverImage(): void
    {
        /** @var Video|null $firstVideo */
        $firstVideo = $this->videos()->first();
        if ($firstVideo) {
            $this->update(['cover_image' => $firstVideo->thumb()]);
        }
    }

    public function updateProfileHasPlaylists(): void
    {
        $exists = self::whereProfileId($this->profile_id)
            ->where('visibility', 'public')
            ->has('videos', '>', 1)
            ->exists();

        $this->profile->update(['has_playlists' => $exists]);
    }

    public function isVisibleTo(?Profile $viewer): bool
    {
        if ($this->visibility === 'public') {
            return true;
        }

        if (! $viewer) {
            return false;
        }

        if ($this->profile_id === $viewer->id) {
            return true;
        }

        if ($this->visibility === 'unlisted') {
            return true;
        }

        if ($this->visibility === 'followers') {
            return $viewer->following()->where('following_id', $this->profile_id)->exists();
        }

        return false;
    }
}
