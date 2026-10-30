<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $profile_id The account doing the trusting (the coauthor).
 * @property int $trusted_profile_id The account whose invites are auto-approved.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CollabTrust extends Model
{
    protected $fillable = ['profile_id', 'trusted_profile_id'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function trustedProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'trusted_profile_id');
    }
}
