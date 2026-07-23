<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DmConversationResource;
use App\Models\Conversation;
use App\Models\Profile;
use App\Services\DirectMessageService;
use Illuminate\Http\Request;

class DmGroupController extends Controller
{
    public function __construct(
        protected DirectMessageService $dms
    ) {}

    public function store(Request $request)
    {
        $maxRecipients = $this->maxParticipants() - 1;

        $data = $request->validate([
            'profile_ids' => "required|array|min:2|max:{$maxRecipients}",
            'profile_ids.*' => 'required|string',
        ]);

        $profile = $request->user()->profile;

        $recipients = Profile::whereIn('id', $data['profile_ids'])->get();

        abort_if(
            $recipients->count() < 2,
            422,
            'Group conversations need at least two other participants.'
        );

        $conversation = $this->dms->createGroup($profile, $recipients);

        $viewer = $conversation->participantFor($profile->id);
        $viewer->setRelation('conversation', $conversation);

        return new DmConversationResource($viewer);
    }

    public function addParticipants(Request $request, string $conversationId)
    {
        $maxRecipients = $this->maxParticipants() - 1;

        $data = $request->validate([
            'profile_ids' => "required|array|min:1|max:{$maxRecipients}",
            'profile_ids.*' => 'required|string',
        ]);

        $profile = $request->user()->profile;
        $conversation = Conversation::findOrFail($conversationId);

        $recipients = Profile::whereIn('id', $data['profile_ids'])->get();

        abort_if($recipients->isEmpty(), 422, 'No valid profiles to add.');

        $conversation = $this->dms->addParticipants($profile, $conversation, $recipients);

        $viewer = $conversation->participantFor($profile->id);
        $viewer->setRelation('conversation', $conversation);

        return new DmConversationResource($viewer);
    }

    public function leave(Request $request, string $conversationId)
    {
        $profile = $request->user()->profile;
        $conversation = Conversation::findOrFail($conversationId);

        $this->dms->leaveGroup($profile, $conversation);

        return response()->json(['ok' => true]);
    }

    protected function maxParticipants(): int
    {
        return (int) config('loops.dm.groups.max_participants', 12);
    }
}
