<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DmConversationResource;
use App\Http\Resources\DmSearchResource;
use App\Models\ConversationParticipant;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DmConversationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $request->validate([
            'filter' => 'sometimes|in:primary,requests,hidden',
            'limit' => 'sometimes|integer|min:1|max:40',
        ]);

        $filter = $request->input('filter', 'primary');
        $profileId = $request->user()->profile_id;

        $query = ConversationParticipant::query()
            ->select('conversation_participants.*', 'conversations.last_message_at as sort_last_message_at')
            ->join('conversations', 'conversations.id', '=', 'conversation_participants.conversation_id')
            ->where('conversation_participants.profile_id', $profileId)
            ->whereNotNull('conversations.last_message_at')
            ->whereNotExists(function ($query) use ($profileId) {
                $query->select('user_filters.id')
                    ->from('conversation_participants as other_participants')
                    ->join('user_filters', 'user_filters.account_id', '=', 'other_participants.profile_id')
                    ->whereColumn('other_participants.conversation_id', 'conversation_participants.conversation_id')
                    ->where('other_participants.profile_id', '!=', $profileId)
                    ->where('user_filters.profile_id', $profileId);
            })
            ->orderByDesc('sort_last_message_at')
            ->orderByDesc('conversation_participants.id')
            ->with([
                'conversation.lastMessage.sender',
                'conversation.lastMessage.video',
                'conversation.participants.profile',
            ]);

        match ($filter) {
            'requests' => $query
                ->where('conversation_participants.state', ConversationParticipant::STATE_REQUEST)
                ->whereNull('conversation_participants.hidden_at'),
            'hidden' => $query
                ->where('conversation_participants.state', ConversationParticipant::STATE_ACTIVE)
                ->whereNotNull('conversation_participants.hidden_at'),
            default => $query
                ->where('conversation_participants.state', ConversationParticipant::STATE_ACTIVE)
                ->whereNull('conversation_participants.hidden_at'),
        };

        $rows = $query->cursorPaginate($request->integer('limit', 10));

        return DmConversationResource::collection($rows);
    }

    public function show(Request $request, int $id)
    {
        $participant = $this->participantOrFail($request, $id);
        $participant->conversation->load(['lastMessage.sender', 'lastMessage.video']);

        return new DmConversationResource($participant);
    }

    public function search(Request $request)
    {
        abort_unless($request->user()->can_dm == true, 403, 'You do not have permission for this action');

        $q = $request->input('q');
        $cleanQuery = Str::of($q)->startsWith('@') ? Str::substr($q, 1) : $q;
        $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $cleanQuery);
        $currentUserId = $request->user()->profile_id;

        $minAccountAgeDays = (int) config('loops.dm.compose.min_account_age_days');
        $minFollowers = (int) config('loops.dm.compose.min_followers');

        $searcher = Profile::select(['id', 'followers', 'created_at'])->findOrFail($currentUserId);

        $restricted = $searcher->created_at->gt(now()->subDays($minAccountAgeDays))
            || (int) $searcher->followers < $minFollowers;

        $res = Profile::select([
            'profiles.id',
            'profiles.local',
            'profiles.name',
            'profiles.avatar',
            'profiles.username',
            'profiles.following',
            'profiles.followers',
            'profiles.video_count',
            'profiles.domain',
            'profiles.status',
            'profiles.created_at',
        ])
            ->selectRaw('MAX(CASE WHEN followers.following_id IS NOT NULL THEN 1 ELSE 0 END) as is_followed')
            ->leftJoin('followers', function ($join) use ($currentUserId) {
                $join->on('followers.following_id', '=', 'profiles.id')
                    ->where('followers.profile_id', '=', $currentUserId);
            })
            ->where('profiles.id', '!=', $currentUserId)
            ->whereNotExists(function ($query) use ($currentUserId) {
                $query->select('id')
                    ->from('user_filters')
                    ->whereColumn('user_filters.account_id', 'profiles.id')
                    ->where('user_filters.profile_id', $currentUserId);
            })
            ->when($restricted, function ($query) use ($currentUserId) {
                $query->whereExists(function ($sub) use ($currentUserId) {
                    $sub->select('id')
                        ->from('followers as follower_check')
                        ->whereColumn('follower_check.profile_id', 'profiles.id')
                        ->where('follower_check.following_id', $currentUserId);
                })->whereExists(function ($sub) use ($currentUserId) {
                    $sub->select('id')
                        ->from('followers as following_check')
                        ->whereColumn('following_check.following_id', 'profiles.id')
                        ->where('following_check.profile_id', $currentUserId);
                });
            })
            ->where('profiles.username', 'like', $escapedQuery.'%')
            ->where('profiles.status', 1)
            ->groupBy(
                'profiles.id',
                'profiles.local',
                'profiles.name',
                'profiles.avatar',
                'profiles.username',
                'profiles.following',
                'profiles.followers',
                'profiles.video_count',
                'profiles.domain',
                'profiles.status',
                'profiles.created_at'
            )
            ->orderByDesc('is_followed')
            ->orderByDesc('profiles.followers')
            ->cursorPaginate(10)
            ->withQueryString();

        return DmSearchResource::collection($res)->additional([
            'meta' => [
                'restricted' => $restricted,
            ],
        ]);
    }

    public function read(Request $request, int $id)
    {
        $request->validate([
            'message_id' => 'sometimes|integer',
        ]);

        $participant = $this->participantOrFail($request, $id);

        $participant->update([
            'last_read_message_id' => $request->input(
                'message_id',
                $participant->conversation->last_message_id
            ),
        ]);

        return response()->json(['read' => true]);
    }

    public function accept(Request $request, int $id)
    {
        $participant = $this->participantOrFail($request, $id);

        abort_unless($participant->state === ConversationParticipant::STATE_REQUEST, 422, 'Not a pending request.');

        $participant->update(['state' => ConversationParticipant::STATE_ACTIVE]);

        return new DmConversationResource($participant->fresh([
            'conversation.lastMessage.sender',
            'conversation.lastMessage.video',
            'conversation.participants.profile',
        ]));
    }

    public function decline(Request $request, int $id)
    {
        $participant = $this->participantOrFail($request, $id);

        abort_unless($participant->state === ConversationParticipant::STATE_REQUEST, 422, 'Not a pending request.');

        $participant->update([
            'state' => ConversationParticipant::STATE_LEFT,
            'hidden_at' => now(),
        ]);

        return response()->json(['declined' => true]);
    }

    public function mute(Request $request, int $id)
    {
        $this->participantOrFail($request, $id)->update(['muted_at' => now()]);

        return response()->json(['muted' => true]);
    }

    public function unmute(Request $request, int $id)
    {
        $this->participantOrFail($request, $id)->update(['muted_at' => null]);

        return response()->json(['muted' => false]);
    }

    public function hide(Request $request, int $id)
    {
        $this->participantOrFail($request, $id)->update(['hidden_at' => now()]);

        return response()->json(['hidden' => true]);
    }

    public function unhide(Request $request, int $id)
    {
        $participant = $this->participantOrFail($request, $id);
        $participant->update(['hidden_at' => null]);

        return new DmConversationResource($participant->fresh([
            'conversation.lastMessage.sender',
            'conversation.lastMessage.video',
            'conversation.participants.profile',
        ]));
    }

    public function suggested(Request $request)
    {
        $profileId = $request->user()->profile_id;
        $limit = 12;

        $profiles = ConversationParticipant::query()
            ->select('conversation_participants.*', 'conversations.last_message_at as sort_last_message_at')
            ->join('conversations', 'conversations.id', '=', 'conversation_participants.conversation_id')
            ->where('conversation_participants.profile_id', $profileId)
            ->where('conversation_participants.state', ConversationParticipant::STATE_ACTIVE)
            ->whereNotNull('conversations.last_message_at')
            ->orderByDesc('sort_last_message_at')
            ->limit($limit)
            ->with('conversation.participants.profile')
            ->get()
            ->map(fn ($participant) => $participant->conversation->otherParticipant($profileId)?->profile)
            ->filter()
            ->unique('id')
            ->take($limit)
            ->values();

        return response()->json([
            'data' => $profiles->map(fn ($profile) => [
                'id' => (string) $profile->id,
                'username' => $profile->username,
                'name' => $profile->name ?? $profile->username,
                'avatar' => $profile->avatar ?? null,
                'domain' => $profile->domain,
                'is_remote' => $profile->domain !== null,
            ])->all(),
        ]);
    }

    protected function participantOrFail(Request $request, int $conversationId): ConversationParticipant
    {
        return ConversationParticipant::with('conversation.participants.profile')
            ->where('conversation_id', $conversationId)
            ->where('profile_id', $request->user()->profile_id)
            ->where('state', '!=', ConversationParticipant::STATE_LEFT)
            ->firstOrFail();
    }
}
