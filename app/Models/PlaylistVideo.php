<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $playlist_id
 * @property int $video_id
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistVideo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistVideo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistVideo query()
 *
 * @mixin \Eloquent
 */
class PlaylistVideo extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'playlist_video';

    protected $fillable = [
        'playlist_id',
        'video_id',
        'position',
    ];
}
