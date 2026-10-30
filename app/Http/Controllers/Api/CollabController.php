<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\CollabRequestResource;
use App\Models\CollabTrust;
use App\Models\Profile;
use App\Models\Video;
use App\Models\VideoCollaboration;
use App\Services\AccountService;
use App\Services\CollabService;
use App\Services\UserAuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollabController extends Controller
{
    use ApiHelpers;

    public function __construct(protected CollabService $collabs)
    {
        $this->middleware('auth');
    }

    public function requests(Request $request)
    {
        $validated = $request->validate([
            'state' => ['sometimes', 'string', Rule::in(['pending', 'all'])],
        ]);

        $pid = $request->user()->profile_id;
        $state = $validated['state'] ?? 'pending';

        $collabs = VideoCollaboration::with('video')
            ->where('profile_id', $pid)
            ->where('is_local', true)
            ->when($state === 'pending', fn ($q) => $q->where('state', VideoCollaboration::STATE_PENDING))
            ->when($state === 'all', fn ($q) => $q->where('state', '!=', VideoCollaboration::STATE_REMOVED))
            ->whereHas('video')
            ->orderByDesc('id')
            ->cursorPaginate(10)
            ->withQueryString();

        return CollabRequestResource::collection($collabs);
    }

    public function show(Request $request, $id)
    {
        $collab = $this->ownCollab($request, $id);

        return new CollabRequestResource($collab->load('video'));
    }

    public function accept(Request $request, $id)
    {
        $validated = $request->validate([
            'trust' => ['sometimes', 'boolean'],
        ]);

        $collab = $this->ownCollab($request, $id);
        $collab = $this->collabs->accept($collab, (bool) ($validated['trust'] ?? false));

        return new CollabRequestResource($collab->load('video'));
    }

    public function decline(Request $request, $id)
    {
        $collab = $this->ownCollab($request, $id);
        $collab = $this->collabs->decline($collab);

        return new CollabRequestResource($collab->load('video'));
    }

    public function leave(Request $request, $id)
    {
        $collab = $this->ownCollab($request, $id);

        abort_unless($collab->isApproved(), 422, 'You are not credited on this video.');

        $this->collabs->remove($collab, 'coauthor');

        return $this->success();
    }

    public function removeFromVideo(Request $request, $videoId)
    {
        $video = $this->ownVideo($request, $videoId);
        $collab = $this->collabs->forVideo($video);

        abort_if($collab === null, 404, 'This video has no collaborator.');

        $this->collabs->remove($collab, 'creator');

        return $this->data(['collab' => null, 'is_published' => (bool) $video->fresh()?->isLive()]);
    }

    public function discard(Request $request, $videoId)
    {
        $video = $this->ownVideo($request, $videoId);

        $this->collabs->discardHeldVideo($video);

        return $this->success();
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:60', 'regex:/^@?[A-Za-z0-9._\-]+$/'],
        ]);

        $me = $request->user()->profile;
        $q = ltrim($validated['q'], '@');
        $escaped = addcslashes($q, '%_\\');

        $candidates = Profile::where('local', true)
            ->where('status', 1)
            ->where('id', '!=', $me->id)
            ->where('collab_state', '!=', 'off')
            ->where('username', 'like', $escaped.'%')
            ->orderByDesc('followers')
            ->limit(25)
            ->get();

        $results = $candidates
            ->filter(fn (Profile $p) => $this->collabs->canInvite($me, $p))
            ->take(8)
            ->map(function (Profile $p) use ($me) {
                $account = AccountService::compact($p->id);

                return $account ? array_merge($account, [
                    'auto_approve' => $this->collabs->trusts($p->id, $me->id),
                ]) : null;
            })
            ->filter()
            ->values();

        return $this->data($results);
    }

    public function trusted(Request $request)
    {
        $pid = $request->user()->profile_id;

        $rows = CollabTrust::where('profile_id', $pid)
            ->orderByDesc('id')
            ->cursorPaginate(20)
            ->withQueryString();

        $rows->setCollection($rows->getCollection()->map(function (CollabTrust $row) {
            $account = AccountService::compact($row->trusted_profile_id);

            return $account ? array_merge($account, [
                'trusted_at' => $row->created_at?->format('c'),
            ]) : null;
        })->filter()->values());

        return response()->json($rows);
    }

    public function trust(Request $request, $profileId)
    {
        $me = $request->user()->profile;
        $target = Profile::where('local', true)->where('status', 1)->findOrFail($profileId);

        $this->collabs->trust($me, $target);

        return $this->success();
    }

    public function untrust(Request $request, $profileId)
    {
        $this->collabs->untrust($request->user()->profile_id, $profileId);

        return $this->success();
    }

    public function getSettings(Request $request)
    {
        $profile = $request->user()->profile;

        return $this->data([
            'state' => in_array($profile->collab_state, CollabService::STATES, true) ? $profile->collab_state : CollabService::DEFAULT_STATE,
            'trusted_count' => CollabTrust::where('profile_id', $profile->id)->count(),
            'enabled' => $this->collabs->enabled(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'state' => ['required', 'string', Rule::in(CollabService::STATES)],
        ]);

        $user = $request->user();
        $profile = $user->profile;

        app(UserAuditLogService::class)->logAccountCollabSettings($user, [
            'old' => $profile->collab_state,
            'new' => $validated['state'],
        ]);

        $profile->update(['collab_state' => $validated['state']]);
        AccountService::del($profile->id);

        return $this->data(['state' => $validated['state']]);
    }

    protected function ownCollab(Request $request, $id): VideoCollaboration
    {
        return VideoCollaboration::where('profile_id', $request->user()->profile_id)
            ->where('is_local', true)
            ->findOrFail($id);
    }

    protected function ownVideo(Request $request, $videoId): Video
    {
        return Video::where('profile_id', $request->user()->profile_id)
            ->where('is_local', true)
            ->findOrFail($videoId);
    }
}
