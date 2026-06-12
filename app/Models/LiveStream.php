<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStream extends Model
{
    use HasFactory, HasSnowflakePrimary;

    protected $fillable = [
        'profile_id',
        'title',
        'description',
        'status',
        'stream_key',
        'viewer_count',
        'max_viewers',
        'thumbnail_url',
        'scheduled_at',
        'started_at',
        'ended_at',
        'product_ids',
        'chat_enabled',
        'recording_url',
    ];

    protected $casts = [
        'viewer_count' => 'integer',
        'max_viewers' => 'integer',
        'product_ids' => 'array',
        'chat_enabled' => 'boolean',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
