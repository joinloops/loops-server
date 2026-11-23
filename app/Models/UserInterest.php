<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInterest extends Model
{
    protected $fillable = [
        'profile_id',
        'interest_type',
        'interest_value',
        'score',
        'interaction_count',
        'last_interaction_at',
    ];

    protected $casts = [
        'score' => 'decimal:4',
        'last_interaction_at' => 'datetime',
    ];

    const TYPE_HASHTAG = 'hashtag';

    const TYPE_CATEGORY = 'category';

    const TYPE_CREATOR = 'creator';

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
