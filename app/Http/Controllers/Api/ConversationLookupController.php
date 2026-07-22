<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DmConversationResource;
use App\Models\Profile;
use App\Services\DirectMessageService;
use App\Services\DmPermissionService;
use Illuminate\Http\Request;

class ConversationLookupController extends Controller
{
    public function lookupOrCreate(Request $request, DirectMessageService $dm)
    {
        $request->validate(['participant_id' => 'required|integer']);

        $me = $request->user()->profile_id;
        abort_unless($request->user()->can_dm == true, 403, 'You do not have permissions to DM');
        $them = (int) $request->input('participant_id');

        abort_if($me === $them, 422, 'You cannot message yourself.');

        $recipient = Profile::findOrFail((int) $request->input('participant_id'));

        abort_if($recipient->dm_privacy === 'off', 404, 'Account does not accept dms');

        $result = DmPermissionService::canInitiate($request->user()->profile, $recipient);

        abort_unless($result->allowed, $result->status, $result->reason);

        $conversation = $dm->getOrCreateConversation($request->user()->profile, $recipient);

        $participant = $conversation->participants
            ->firstWhere('profile_id', $me)
            ->setRelation('conversation', $conversation);

        return new DmConversationResource($participant);
    }
}
