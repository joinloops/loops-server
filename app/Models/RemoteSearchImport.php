<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RemoteSearchImport extends Model
{
    protected $fillable = [
        'profile_id',
        'search_url',
        'searchable_type',
        'searchable_id',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function searchable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForProfile($query, $profileId)
    {
        return $query->where('profile_id', $profileId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('searchable_type', $type);
    }

    public function scopeWithinDays($query, int $days)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
