<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDmMediaRequest;
use App\Http\Resources\DmMessageResource;
use App\Models\ConversationParticipant;
use App\Models\DmMedia;
use App\Models\Message;
use App\Models\Profile;
use App\Services\DirectMessageService;
use App\Services\FollowerService;
use App\Services\KlipyMediaSelector;
use Illuminate\Http\Request;

class DmMessageController extends Controller
{
    use ApiHelpers;

    public function __construct(
        protected DirectMessageService $service
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request, int $conversationId)
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        ConversationParticipant::where('conversation_id', $conversationId)
            ->where('profile_id', $request->user()->profile_id)
            ->where('state', '!=', ConversationParticipant::STATE_LEFT)
            ->firstOrFail();

        $messages = Message::with(['sender', 'video'])
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->cursorPaginate($request->integer('limit', 5));

        return DmMessageResource::collection($messages);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can_dm == true, 403, 'You do not have permission for this action');

        $data = $request->validate([
            'conversation_id' => 'required_without:recipient_id|nullable|integer',
            'recipient_id' => 'required_without:conversation_id|nullable|integer|exists:profiles,id',
            'type' => 'required|in:text,loop_share',
            'body' => 'required_if:type,text|nullable|string|max:2000',
            'video_id' => 'required_if:type,loop_share|nullable|integer|exists:videos,id',
        ]);

        $minAccountAgeDays = (int) config('loops.dm.compose.min_account_age_days');
        $minFollowers = (int) config('loops.dm.compose.min_followers');

        $sender = Profile::findOrFail($request->user()->profile_id);

        $restricted = $sender->created_at->gt(now()->subDays($minAccountAgeDays)) || (int) $sender->followers < $minFollowers;
        if ($restricted && isset($data['recipient_id'])) {
            abort_unless(app(FollowerService::class)->follows($data['recipient_id'], $sender->id), 403, 'You do not have permission for this action');
        }

        if (filled($data['conversation_id'] ?? null)) {
            $participant = ConversationParticipant::with('conversation.participants.profile')
                ->where('conversation_id', $data['conversation_id'])
                ->where('profile_id', $sender->id)
                ->firstOrFail();

            $recipient = $participant->conversation->otherParticipant($sender->id)?->profile;

            abort_if(! $recipient, 422, 'Recipient not found.');
        } else {
            $recipient = Profile::findOrFail($data['recipient_id']);
        }

        $message = $this->service->send($sender, $recipient, $data);

        return new DmMessageResource($message);
    }

    public function destroy(Request $request, int $id)
    {
        $message = Message::with('conversation.participants.profile')
            ->where('id', $id)
            ->where('profile_id', $request->user()->profile_id)
            ->firstOrFail();

        $this->service->deleteMessage($message);

        return response()->json(['deleted' => true]);
    }

    public function storeMedia(StoreDmMediaRequest $request)
    {
        $data = $request->validated();

        $sender = Profile::findOrFail($request->user()->profile_id);
        $recipient = $this->resolveRecipient($sender, $data);

        $body = $data['body'] ?? null;
        $item = $data['item'];
        $klipyId = (string) data_get($item, 'id', data_get($item, 'slug'));

        $picked = app(KlipyMediaSelector::class)->pick($item, $data['type']);

        $message = $this->service->send($sender, $recipient, [
            'type' => Message::TYPE_MEDIA,
            'body' => $body,
            'media' => [[
                'profile_id' => $sender->id,
                'type' => $data['type'] === 'gifs'
                    ? DmMedia::TYPE_GIF
                    : DmMedia::typeFromMime($picked['mime_type']),
                'mime_type' => $picked['mime_type'],
                'remote_url' => $picked['url'],
                'preview_remote_url' => data_get($item, 'preview.url'),
                'width' => $picked['width'],
                'height' => $picked['height'],
                'description' => data_get($item, 'title'),
                'provider' => DmMedia::PROVIDER_KLIPY,
                'external_id' => $klipyId,
            ]],
        ]);

        return new DmMessageResource($message);
    }

    protected function resolveRecipient(Profile $sender, array $data): Profile
    {
        if (filled($data['conversation_id'] ?? null)) {
            $participant = ConversationParticipant::with('conversation.participants.profile')
                ->where('conversation_id', $data['conversation_id'])
                ->where('profile_id', $sender->id)
                ->firstOrFail();

            $recipient = $participant->conversation->otherParticipant($sender->id)?->profile;

            abort_if(! $recipient, 422, 'Recipient not found.');

            return $recipient;
        }

        return Profile::findOrFail($data['recipient_id']);
    }
}
