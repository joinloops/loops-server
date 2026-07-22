<?php

namespace App\Services;

use App\Events\DmMessageCreated;
use App\Events\DmMessageDeleted;
use App\Jobs\Federation\DeliverDmActivity;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Profile;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @phpstan-type DmMediaAttributes array{
 *     profile_id: int,
 *     type: string,
 *     mime_type: string|null,
 *     remote_url: string,
 *     preview_remote_url?: string|null,
 *     width?: int|null,
 *     height?: int|null,
 *     blurhash?: string|null,
 *     description?: string|null,
 *     provider?: string,
 *     external_id?: string
 * }
 */
class DirectMessageService
{
    public const INBOUND_REQUEST_SOFT_CAP = 5;

    public function __construct(
        protected DmActivityPubService $ap
    ) {}

    public function canMessage(Profile $sender, Profile $recipient): bool
    {
        if ($sender->id === $recipient->id) {
            return false;
        }

        if ($this->isBlocked($sender, $recipient)) {
            return false;
        }

        return match ($this->dmPrivacySetting($recipient)) {
            'off' => false,
            'following' => $this->follows($recipient, $sender),
            default => true,
        };
    }

    public function send(Profile $sender, Profile $recipient, array $payload): Message
    {
        abort_unless(
            $this->canInitiateConversation($sender, $recipient),
            403,
            'You do not have permission for this action'
        );

        abort_unless($this->canMessage($sender, $recipient), 403, 'You cannot message this account.');

        $message = DB::transaction(function () use ($sender, $recipient, $payload) {
            $conversation = $this->findOrCreateDm($sender, $recipient);

            $senderParticipant = $this->ensureParticipant(
                $conversation,
                $sender->id,
                ConversationParticipant::STATE_ACTIVE
            );
            $senderParticipant->forceFill([
                'state' => ConversationParticipant::STATE_ACTIVE,
                'hidden_at' => null,
            ])->save();

            $recipientState = match ($recipient->dm_privacy) {
                'off' => false,
                'following' => $this->follows($recipient, $sender)
                    ? ConversationParticipant::STATE_ACTIVE
                    : ConversationParticipant::STATE_REQUEST,
                'everyone' => ConversationParticipant::STATE_ACTIVE,
                default => false,
            };

            if ($recipientState === false) {
                abort(403, 'Message not deliverable.');
            }

            $recipientParticipant = $this->ensureParticipant(
                $conversation,
                $recipient->id,
                $recipientState
            );

            if (
                $recipientParticipant->state === ConversationParticipant::STATE_REQUEST
                && $conversation->messages()->where('profile_id', $sender->id)->exists()
            ) {
                abort(403, 'Message request pending. You can send more messages once they accept.');
            }

            $message = $conversation->messages()->create([
                'profile_id' => $sender->id,
                'type' => $payload['type'],
                'body' => $payload['body'] ?? null,
                'video_id' => $payload['video_id'] ?? null,
            ]);

            if (isset($payload['video_id'])) {
                Video::where('id', $payload['video_id'])->increment('shares');
            }

            $message->forceFill([
                'ap_object_uri' => $this->ap->objectUri($message),
            ])->save();

            /** @var list<DmMediaAttributes> $mediaList */
            $mediaList = $payload['media'] ?? [];
            $this->attachMedia($message, $mediaList);

            $conversation->forceFill([
                'last_message_id' => $message->id,
                'last_message_at' => $message->created_at,
            ])->save();

            $senderParticipant->forceFill([
                'last_read_message_id' => $message->id,
            ])->save();

            if ($recipientParticipant->state === ConversationParticipant::STATE_ACTIVE) {
                $recipientParticipant->forceFill(['hidden_at' => null])->save();
            }

            return $message->load(['sender', 'video', 'media', 'conversation.participants.profile']);
        });

        event(new DmMessageCreated($message));

        $recipientParticipant = $message->conversation->participantFor($recipient->id);

        if ($this->isRemote($recipient)) {
            DeliverDmActivity::dispatch(
                $this->ap->buildCreate($message),
                $this->ap->inboxUrl($recipient),
                (int) $sender->id
            );
        } elseif (
            $recipientParticipant
            && $recipientParticipant->state === ConversationParticipant::STATE_ACTIVE
            && ! $recipientParticipant->isMuted()
        ) {
            $this->sendPushNotification($recipient, $message);
        }

        return $message;
    }

    public function receive(Profile $sender, Profile $recipient, array $payload): ?Message
    {
        if ($sender->id === $recipient->id) {
            return null;
        }

        if ($this->isBlocked($sender, $recipient)) {
            return null;
        }

        $privacy = $this->dmPrivacySetting($recipient);

        if ($privacy === 'off') {
            return null;
        }

        if ($privacy === 'following' && ! $this->follows($recipient, $sender)) {
            return null;
        }

        $message = DB::transaction(function () use ($sender, $recipient, $payload) {
            $conversation = $this->findOrCreateDm($sender, $recipient, $payload['context_uri'] ?? null);

            $senderParticipant = $this->ensureParticipant(
                $conversation,
                $sender->id,
                ConversationParticipant::STATE_ACTIVE
            );
            $senderParticipant->forceFill([
                'state' => ConversationParticipant::STATE_ACTIVE,
                'hidden_at' => null,
            ])->save();

            $recipientState = match ($recipient->dm_privacy) {
                'off' => false,
                'following' => $this->follows($recipient, $sender)
                    ? ConversationParticipant::STATE_ACTIVE
                    : ConversationParticipant::STATE_REQUEST,
                'everyone' => ConversationParticipant::STATE_ACTIVE,
                default => false,
            };

            if (! $recipientState) {
                return;
            }

            $recipientParticipant = $this->ensureParticipant(
                $conversation,
                $recipient->id,
                $recipientState
            );

            if (
                $recipientParticipant->state === ConversationParticipant::STATE_REQUEST
                && $conversation->messages()->where('profile_id', $sender->id)->count()
                    >= self::INBOUND_REQUEST_SOFT_CAP
            ) {
                return;
            }

            $message = $conversation->messages()->create([
                'profile_id' => $sender->id,
                'type' => ! empty($payload['media']) ? Message::TYPE_MEDIA : Message::TYPE_TEXT,
                'body' => $payload['body'] ?? null,
            ]);

            $message->forceFill([
                'ap_object_uri' => $payload['object_uri'],
            ])->save();

            /** @var list<DmMediaAttributes> $mediaList */
            $mediaList = $payload['media'] ?? [];
            $this->attachMedia($message, $mediaList);

            $conversation->forceFill([
                'last_message_id' => $message->id,
                'last_message_at' => $message->created_at,
            ])->save();

            if ($recipientParticipant->state === ConversationParticipant::STATE_ACTIVE) {
                $recipientParticipant->forceFill(['hidden_at' => null])->save();
            }

            return $message->load(['sender', 'video', 'media', 'conversation.participants.profile']);
        });

        if (! $message) {
            return null;
        }

        event(new DmMessageCreated($message));

        $recipientParticipant = $message->conversation->participantFor($recipient->id);

        if (
            $recipientParticipant
            && $recipientParticipant->state === ConversationParticipant::STATE_ACTIVE
            && ! $recipientParticipant->isMuted()
        ) {
            $this->sendPushNotification($recipient, $message);
        }

        return $message;
    }

    public function deleteMessage(Message $message): void
    {
        $conversation = $message->conversation;
        $conversation->loadMissing('participants.profile');
        $wasLast = (int) $conversation->last_message_id === (int) $message->id;

        $message->delete();

        if ($wasLast) {
            $latest = $conversation->messages()->orderByDesc('id')->first();
            $conversation->forceFill([
                'last_message_id' => $latest?->id,
                'last_message_at' => $latest?->created_at,
            ])->save();
        }

        event(new DmMessageDeleted($conversation, (string) $message->id));

        $other = $conversation->otherParticipant($message->profile_id);

        if ($other && $other->profile && $this->isRemote($other->profile) && $message->ap_object_uri) {
            DeliverDmActivity::dispatch(
                $this->ap->buildDelete($message),
                $this->ap->inboxUrl($other->profile),
                (int) $message->profile_id
            );
        }
    }

    public function findOrCreateDm(Profile $a, Profile $b, ?string $contextUri = null): Conversation
    {
        return Conversation::firstOrCreate(
            ['participants_hash' => Conversation::dmHash($a->id, $b->id)],
            [
                'type' => Conversation::TYPE_DM,
                'created_by_profile_id' => $a->id,
                'context_uri' => $contextUri ?? url('/ap/contexts/'.Str::uuid()),
            ]
        );
    }

    public function getOrCreateConversation(Profile $sender, Profile $recipient): Conversation
    {
        abort_unless(
            $this->canInitiateConversation($sender, $recipient),
            403,
            'You do not have permission for this action'
        );

        abort_unless($this->canMessage($sender, $recipient), 403, 'You cannot message this account.');

        return DB::transaction(function () use ($sender, $recipient) {
            $conversation = $this->findOrCreateDm($sender, $recipient);

            $senderParticipant = $this->ensureParticipant(
                $conversation,
                $sender->id,
                ConversationParticipant::STATE_ACTIVE
            );
            $senderParticipant->forceFill([
                'state' => ConversationParticipant::STATE_ACTIVE,
                'hidden_at' => null,
            ])->save();

            $recipientState = match ($recipient->dm_privacy) {
                'off' => false,
                'following' => $this->follows($recipient, $sender)
                    ? ConversationParticipant::STATE_ACTIVE
                    : ConversationParticipant::STATE_REQUEST,
                'everyone' => ConversationParticipant::STATE_ACTIVE,
                default => false,
            };

            if ($recipientState === false) {
                return;
            }

            $this->ensureParticipant(
                $conversation,
                $recipient->id,
                $recipientState
            );

            return $conversation->load(['participants.profile']);
        });
    }

    /**
     * @param  list<DmMediaAttributes>  $mediaList
     */
    protected function attachMedia(Message $message, array $mediaList): void
    {
        if (! $mediaList) {
            return;
        }

        $order = 0;
        $entities = [];

        foreach ($mediaList as $attributes) {
            $media = $message->media()->create(array_merge($attributes, ['order' => $order++]));
            $entities[] = $media->toEntity();
        }

        $message->forceFill(['entities' => ['media' => $entities]])->save();
    }

    public function isRestrictedSender(Profile $sender): bool
    {
        $minAccountAgeDays = (int) config('loops.dm.compose.min_account_age_days');
        $minFollowers = (int) config('loops.dm.compose.min_followers');

        return $sender->created_at->gt(now()->subDays($minAccountAgeDays))
            || (int) $sender->followers < $minFollowers;
    }

    protected function canInitiateConversation(Profile $sender, Profile $recipient): bool
    {
        if (! $this->isRestrictedSender($sender)) {
            return true;
        }

        $conversationExists = Conversation::where(
            'participants_hash',
            Conversation::dmHash($sender->id, $recipient->id)
        )->exists();

        if ($conversationExists) {
            return true;
        }

        return $this->follows($sender, $recipient) && $this->follows($recipient, $sender);
    }

    protected function ensureParticipant(Conversation $conversation, int $profileId, string $initialState): ConversationParticipant
    {
        return ConversationParticipant::firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'profile_id' => $profileId,
            ],
            [
                'state' => $initialState,
            ]
        );
    }

    protected function isRemote(Profile $profile): bool
    {
        return $profile->local == false;
    }

    protected function isBlocked(Profile $a, Profile $b): bool
    {
        $filters = app(UserFilterService::class);

        return $filters->isBlocking($a->id, $b->id);
    }

    protected function follows(Profile $profile, Profile $target): bool
    {
        return DB::table('followers')
            ->where('profile_id', $profile->id)
            ->where('following_id', $target->id)
            ->exists();
    }

    protected function dmPrivacySetting(Profile $profile): string
    {
        return $profile->dm_privacy ?? 'everyone';
    }

    protected function sendPushNotification(Profile $recipient, Message $message): void
    {
        // todo
    }
}
