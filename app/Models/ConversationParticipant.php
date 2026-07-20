<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $profile_id
 * @property string $state
 * @property int|null $last_read_message_id
 * @property \Illuminate\Support\Carbon|null $muted_at
 * @property \Illuminate\Support\Carbon|null $hidden_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Conversation|null $conversation
 * @property-read \App\Models\Profile|null $profile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant whereConversationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant whereHiddenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant whereLastReadMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant whereMutedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConversationParticipant whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ConversationParticipant extends Model
{
    public const STATE_ACTIVE = 'active';

    public const STATE_REQUEST = 'request';

    public const STATE_LEFT = 'left';

    protected $guarded = [];

    protected $casts = [
        'muted_at' => 'datetime',
        'hidden_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function isMuted(): bool
    {
        return $this->muted_at !== null;
    }
}
