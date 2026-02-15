<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchFollowersRequest;
use App\Http\Resources\AccountCompactResource;
use App\Http\Resources\FollowerResource;
use App\Http\Resources\FollowingResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserVideoLikeResource;
use App\Jobs\Federation\DeliverFollowRequest;
use App\Jobs\Federation\DeliverUndoFollowActivity;
use App\Jobs\Federation\DeliverUndoFollowRequestActivity;
use App\Models\Follower;
use App\Models\FollowRequest;
use App\Models\HiddenSuggestion;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\ProfileLink;
use App\Models\SystemMessage;
use App\Models\UserFilter;
use App\Models\VideoLike;
use App\Services\AccountService;
use App\Services\AccountSuggestionService;
use App\Services\BlockedDomainService;
use App\Services\ConfigService;
use App\Services\FollowerService;
use App\Services\LinkLimitService;
use App\Services\NotificationService;
use App\Services\SystemMessageService;
use App\Services\UserFilterService;
use App\Support\CursorToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{
    use ApiHelpers;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function selfAccountInfo(Request $request)
    {
        $user = $request->user();
        $pid = $user->profile_id;
        $profile = Profile::findOrFail($pid);
        if ($profile->status != 1) {
            return $this->error('This resource is not available', 403);
        }
        $res = (new ProfileResource($profile))->toArray($request);
        $res['is_admin'] = (bool) $user->is_admin;
        $res['is_owner'] = true;
        $res['likes_count'] = (int) AccountService::getAccountLikesCount($pid);

        return response()->json(['data' => $res]);
    }

    public function getAccountInfo(Request $request, $id)
    {
        $pid = false;
        $user = auth('web')->user() ?? auth('api')->user();
        if ($user) {
            $pid = $user->profile_id;
        }
        $profile = Profile::active()->find($id);

        if (! $profile || $request->user()->cannot('view', $profile)) {
            return $this->error('Account not found or is unavailable', 404);
        }

        $res = (new ProfileResource($profile))->toArray($request);
        $res['is_owner'] = $pid ? $pid == $profile->id : false;
        $res['likes_count'] = (int) AccountService::getAccountLikesCount($id);

        return response()->json(['data' => $res]);
    }

    public function notifications(Request $request)
    {
        $validated = $request->validate([
            'type' => 'sometimes|in:all,activity,system,followers,videoLike,videoShare,comments',
        ]);

        $pid = $request->user()->profile_id;
        $hasCursor = $request->filled('cursor');
        $type = data_get($validated, 'type', 'all');
        $types = match ($type) {
            'all' => Notification::allTypes(),
            'activity' => Notification::activityTypes(),
            'followers' => Notification::followerTypes(),
            'system' => Notification::systemTypes(),
            'videoLike' => Notification::videoLikeTypes(),
            'videoShare' => Notification::videoShareTypes(),
            'comments' => Notification::commentsTypes(),
            default => Notification::allTypes()
        };

        $res = Notification::whereUserId($pid)
            ->whereIn('type', $types)
            ->where('actor_state', 1)
            ->orderByDesc('created_at')
            ->cursorPaginate(20);

        if ($hasCursor) {
            return NotificationResource::collection($res);
        }

        return NotificationResource::collection($res)->additional([
            'meta' => [
                'unread_counts' => NotificationService::getTotalUnreadCount($pid),
            ],
        ]);
    }

    public function notificationUnreadCount(Request $request)
    {
        $pid = $request->user()->profile_id;

        return $this->data(['unread_count' => NotificationService::getUnreadCount($pid)]);
    }

    public function markNotificationAsRead(Request $request, $id)
    {
        $pid = $request->user()->profile_id;

        $notify = Notification::whereUserId($pid)->findOrFail($id);
        $notify->read_at = now();
        $notify->save();

        NotificationService::clearUnreadCount($pid);

        return new NotificationResource($notify);
    }

    public function markAllNotificationsAsRead(Request $request)
    {
        $validated = $request->validate([
            'type' => 'sometimes|in:all,activity,system,followers',
        ]);
        $pid = $request->user()->profile_id;
        $type = data_get($validated, 'type', 'all');

        match ($type) {
            'all' => Notification::whereUserId($pid)->update(['read_at' => now()]),
            'activity' => Notification::whereUserId($pid)->whereIn('type', Notification::activityTypes())->update(['read_at' => now()]),
            'followers' => Notification::whereUserId($pid)->whereIn('type', Notification::followerTypes())->update(['read_at' => now()]),
            'system' => Notification::whereUserId($pid)->whereIn('type', Notification::systemTypes())->update(['read_at' => now()]),
            default => Notification::whereUserId($pid)->update(['read_at' => now()]),
        };
        NotificationService::clearUnreadCount($pid);

        return $this->success();
    }

    public function getSystemNotification(Request $request, $id)
    {
        $systemMessage = SystemMessage::active()->published()->where('key_id', $id)->first();
        $cached = app(SystemMessageService::class)->getFull($systemMessage->id);

        return response()->json(['data' => $cached]);
    }

    public function accountBlock(Request $request, $id)
    {
        $pid = $request->user()->profile_id;
        abort_if($pid == $id, 403, 'You cannot block yourself');

        $profile = Profile::findOrFail($id);

        $count = UserFilter::where('profile_id', $pid)->count();

        abort_if($count > 250, 422, 'You cannot block any more accounts');

        $res = DB::transaction(function () use ($pid, $profile, $request) {
            UserFilter::updateOrCreate([
                'profile_id' => $pid,
                'account_id' => $profile->id,
            ]);

            Follower::whereProfileId($pid)->whereFollowingId($profile->id)->delete();
            Follower::whereProfileId($profile->id)->whereFollowingId($pid)->delete();
            FollowRequest::whereProfileId($pid)->whereFollowingId($profile->id)->delete();
            FollowRequest::whereProfileId($profile->id)->whereFollowingId($pid)->delete();

            FollowerService::refreshAndSync($pid, $profile->id);
            AccountSuggestionService::removeForUser($pid, $profile->id);
            AccountSuggestionService::invalidate($pid);

            $res = (new ProfileResource($profile))->toArray($request);
            $res['is_blocking'] = true;

            return $res;
        });

        return response()->json($res);
    }

    public function accountUnblock(Request $request, $id)
    {
        $pid = $request->user()->profile_id;
        abort_if($pid == $id, 403, 'You cannot unblock yourself');

        $profile = Profile::findOrFail($id);

        $res = DB::transaction(function () use ($pid, $profile, $request) {
            UserFilter::whereProfileId($pid)
                ->whereAccountId($profile->id)
                ->delete();

            AccountSuggestionService::invalidate($pid);
            FollowerService::refreshAndSync($pid, $profile->id);

            $res = (new ProfileResource($profile))->toArray($request);
            $res['is_blocking'] = false;

            return $res;
        });

        return response()->json($res);
    }

    public function follow(Request $request, $id)
    {
        $pid = $request->user()->profile_id;

        if ($pid == $id) {
            return $this->error('You cannot follow your own account', 422);
        }

        $profile = Profile::active()->findOrFail($id);

        if ($request->user()->cannot('view', $profile)) {
            return $this->error('Account not found or is unavailable', 404);
        }

        $res = DB::transaction(function () use ($pid, $id, $profile) {
            if ($profile->local) {
                $res = Follower::updateOrCreate([
                    'profile_id' => $pid,
                    'following_id' => $id,
                ], [
                    'following_is_local' => (bool) $profile->local,
                ]);

                if ($res->wasRecentlyCreated) {
                    NotificationService::newFollower($id, $pid);
                }
            } else {
                // Delete existing follow request to force a new one, to fix broken federation
                FollowRequest::whereProfileId($pid)->whereFollowingId($profile->id)->delete();

                $res = FollowRequest::firstOrCreate([
                    'profile_id' => $pid,
                    'following_id' => $profile->id,
                    'profile_is_local' => true,
                    'following_is_local' => false,
                    'following_state' => 0,
                ]);

                DeliverFollowRequest::dispatch($res)->onQueue('activitypub-out');
            }

            return AccountService::get($id);
        });

        return $this->data($res);
    }

    public function unfollow(Request $request, $id)
    {
        $pid = $request->user()->profile_id;

        if ($pid == $id) {
            return $this->error('You cannot unfollow your own account', 422);
        }

        $profile = Profile::findOrFail($id);

        $res = Follower::where([
            'profile_id' => $pid,
            'following_id' => $profile->id,
        ])->first();

        if (! $res) {
            return $this->data(AccountService::get($id));
        }

        $res = DB::transaction(function () use ($pid, $id, $profile, $res) {
            if ($profile->local) {
                $res->delete();
                NotificationService::unFollow($id, $pid);
            } else {
                $followRequest = FollowRequest::whereProfileId($pid)->whereFollowingId($profile->id)->first();
                if ($followRequest) {
                    DeliverUndoFollowRequestActivity::dispatch($followRequest)->onQueue('activitypub-out');
                } else {
                    DeliverUndoFollowActivity::dispatch($res)->onQueue('activitypub-out');
                }
                $res->delete();
            }

            return AccountService::get($id);
        });

        return $this->data($res);
    }

    public function undoFollowRequest(Request $request, $id)
    {
        $pid = $request->user()->profile_id;

        if ($pid == $id) {
            return $this->error('You cannot unfollow your own account', 422);
        }

        $profile = Profile::findOrFail($id);
        $followRequest = FollowRequest::whereProfileId($pid)->whereFollowingId($profile->id)->firstOrFail();

        $res = DB::transaction(function () use ($id, $profile, $followRequest) {
            if ($profile->local) {
                $followRequest->delete();

                return $this->data(AccountService::get($id));
            } else {
                $followRequest->update(['following_state' => 5]);
                DeliverUndoFollowRequestActivity::dispatch($followRequest)->onQueue('activitypub-out');
            }

            return AccountService::get($id);
        });

        return $this->data($res);
    }

    public function getRelationshipState(Request $request, $id)
    {
        $pid = $request->user()->profile_id;
        if ($id == $pid) {
            return $this->data([
                'following' => false,
                'blocking' => false,
            ]);
        }

        $account = AccountService::get($id, false);

        if (! $account) {
            return $this->error('Record not found');
        }

        $res = [
            'pending_follow_request' => false,
            'followed_by' => (bool) FollowerService::follows($id, $pid),
            'following' => (bool) FollowerService::follows($pid, $id),
            'blocking' => (bool) UserFilterService::isBlocking($pid, $id),
        ];

        if (! $account['local']) {
            $res['pending_follow_request'] = FollowRequest::whereProfileId($pid)->whereFollowingId($id)->whereFollowingState(0)->exists();
        }

        return $this->data($res);
    }

    public function getSuggestedAccounts(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:3|max:20',
        ]);

        $pid = (int) $request->user()->profile_id;
        $limit = data_get($validated, 'limit', 20);
        $accounts = AccountSuggestionService::get($pid, $limit);

        return $this->data($accounts);
    }

    public function hideSuggestion(Request $request)
    {
        $request->validate([
            'profile_id' => 'required|integer|exists:profiles,id',
        ]);

        $pid = (int) $request->user()->profile_id;
        $profileId = (int) $request->input('profile_id');

        if ($pid === $profileId) {
            return response()->json(['error' => 'Cannot hide your own account'], 422);
        }

        if (HiddenSuggestion::where('profile_id', $pid)->count() > 100) {
            return response()->json(['message' => 'You have reached the limit']);
        }

        AccountSuggestionService::hide($pid, $profileId);

        return response()->json(['message' => 'Suggestion hidden']);
    }

    public function unhideSuggestion(Request $request)
    {
        $request->validate([
            'profile_id' => 'required|integer|exists:profiles,id',
        ]);

        $pid = (int) $request->user()->profile_id;
        $profileId = (int) $request->input('profile_id');

        if ($pid === $profileId) {
            return response()->json(['error' => 'Cannot unhide your own account'], 422);
        }

        AccountSuggestionService::unhide($pid, $profileId);

        return response()->json(['message' => 'Suggestion unhidden']);
    }

    public function accountFollowers(SearchFollowersRequest $request, $id)
    {
        $profile = Profile::active()->findOrFail($id);

        if ($request->user() && $request->user()->cannot('viewAny', [Profile::class])) {
            return $this->error('Unavailable', 403);
        }

        if ($profile->manuallyApprovesFollowers && $id != $request->user()->profile_id) {
            return $this->error('You do not have permission to view this.', 403);
        }

        $authProfileId = $request->user()?->profile_id;
        $isOwner = $authProfileId && ((int) $authProfileId === (int) $profile->id || $request->user()->is_admin);
        $hasSearch = $request->filled('search');

        $query = Follower::where('followers.following_id', $id)
            ->join('profiles', 'followers.profile_id', '=', 'profiles.id')
            ->where('profiles.status', 1);

        if ($request->filled('search')) {
            $search = $request->validated()['search'];
            $query->where('profiles.username', 'like', $search.'%');
        }

        if ($authProfileId) {
            $query->leftJoin('followers as auth_following', function ($join) use ($authProfileId) {
                $join->on('auth_following.following_id', '=', 'followers.profile_id')
                    ->where('auth_following.profile_id', '=', $authProfileId);
            })
                ->addSelect([
                    'followers.*',
                    DB::raw('CASE WHEN auth_following.id IS NOT NULL THEN 1 ELSE 0 END as is_following'),
                ]);
        }

        $followers = $query
            ->orderByDesc('followers.id')
            ->cursorPaginate($isOwner ? 15 : ($hasSearch ? 10 : 30))
            ->withQueryString();

        if (! $isOwner) {
            $followers->setCollection($followers->getCollection());
            $followers = new \Illuminate\Pagination\CursorPaginator(
                $followers->items(),
                $followers->perPage(),
                $followers->cursor(),
                [
                    'path' => $followers->path(),
                    'cursorName' => $followers->getCursorName(),
                ]
            );
        }

        return FollowerResource::collection($followers);
    }

    public function accountFollowing(SearchFollowersRequest $request, $id)
    {
        $profile = Profile::active()->findOrFail($id);

        if ($request->user() && $request->user()->cannot('viewAny', [Profile::class])) {
            return $this->error('Unavailable', 403);
        }

        if ($profile->manuallyApprovesFollowers && $id != $request->user()->profile_id) {
            return $this->error('You do not have permission to view this.', 403);
        }

        $authProfileId = $request->user()?->profile_id;
        $isOwner = $authProfileId && ((int) $authProfileId === (int) $profile->id || $request->user()->is_admin);
        $hasSearch = $request->filled('search');

        $query = Follower::where('followers.profile_id', $id)
            ->join('profiles', 'followers.following_id', '=', 'profiles.id')
            ->where('profiles.status', 1);

        if ($request->filled('search')) {
            $search = $request->validated()['search'];

            $query->where('profiles.username', 'like', $search.'%');
        }

        if ($authProfileId) {
            $query->leftJoin('followers as auth_following', function ($join) use ($authProfileId) {
                $join->on('auth_following.following_id', '=', 'followers.following_id')
                    ->where('auth_following.profile_id', '=', $authProfileId);
            })
                ->addSelect([
                    'followers.*',
                    DB::raw('CASE WHEN auth_following.id IS NOT NULL THEN 1 ELSE 0 END as is_following'),
                ]);
        } else {
            $query->select('followers.*');
        }

        $followers = $query
            ->orderByDesc('followers.id')
            ->cursorPaginate($isOwner ? 15 : ($hasSearch ? 10 : 30))
            ->withQueryString();

        if (! $isOwner) {
            $followers->setCollection($followers->getCollection());
            $followers = new \Illuminate\Pagination\CursorPaginator(
                $followers->items(),
                $followers->perPage(),
                $followers->cursor(),
                [
                    'path' => $followers->path(),
                    'cursorName' => $followers->getCursorName(),
                ]
            );
        }

        return FollowingResource::collection($followers);
    }

    public function accountFriends(SearchFollowersRequest $request, $id)
    {
        $profile = Profile::active()->findOrFail($id);
        $authProfileId = $request->user()->profile_id;

        if ($authProfileId == $id) {
            return $this->error('Cannot get mutual following with yourself', 400);
        }

        if (! $request->user()->can_follow || $request->user()->cannot('viewAny', [Profile::class])) {
            return $this->error('Unavailable', 403);
        }

        if ($profile->manuallyApprovesFollowers && $id != $authProfileId) {
            $isFollowing = Follower::where('profile_id', $authProfileId)
                ->where('following_id', $id)
                ->exists();

            if (! $isFollowing) {
                return $this->error('You do not have permission to view this.', 403);
            }
        }

        $query = Follower::select('f1.*')
            ->from('followers as f1')
            ->join('followers as f2', 'f1.following_id', '=', 'f2.following_id')
            // @phpstan-ignore argument.type
            ->where('f1.profile_id', $authProfileId)
            // @phpstan-ignore argument.type
            ->where('f2.profile_id', $id);

        $query->addSelect([
            DB::raw('1 as is_following'),
        ]);

        $mutualFollows = $query->orderByDesc('f1.id')->cursorPaginate(15);

        return FollowingResource::collection($mutualFollows);
    }

    public function accountSuggestedFollows(Request $request, $id)
    {
        $profile = Profile::active()->findOrFail($id);

        $authProfileId = $request->user()->profile_id;

        if ($authProfileId == $id) {
            return $this->error('Cannot get suggestions for own profile', 400);
        }

        $limit = 15;

        $targetFollows = Profile::select([
            'profiles.id',
            DB::raw('COUNT(f_count.id) as followers_count'),
            DB::raw('1 as suggestion_type'),
            DB::raw('0 as is_following'),
        ])
            ->join('followers as f_target', 'f_target.following_id', '=', 'profiles.id')
            ->leftJoin('followers as f_count', 'f_count.following_id', '=', 'profiles.id')
            // @phpstan-ignore argument.type
            ->where('f_target.profile_id', $id)
            ->whereNotExists(function ($query) use ($authProfileId) {
                $query->select(DB::raw(1))
                    ->from('followers')
                    ->whereColumn('followers.following_id', 'profiles.id')
                    ->where('followers.profile_id', $authProfileId);
            })
            ->whereNotExists(function ($query) use ($authProfileId) {
                $query->select(DB::raw(1))
                    ->from('user_filters')
                    ->whereColumn('user_filters.account_id', 'profiles.id')
                    ->where('user_filters.profile_id', $authProfileId);
            })
            ->whereNotExists(function ($query) use ($authProfileId) {
                $query->select(DB::raw(1))
                    ->from('user_filters')
                    ->whereColumn('user_filters.profile_id', 'profiles.id')
                    ->where('user_filters.account_id', $authProfileId);
            })
            ->where('profiles.status', 1)
            ->where('profiles.id', '!=', $authProfileId)
            ->groupBy('profiles.id')
            ->orderByDesc('followers_count')
            ->limit($limit)
            ->get();

        $suggestions = $targetFollows;

        if ($suggestions->count() < $limit) {
            $needed = $limit - $suggestions->count();

            $followersOfTarget = Profile::select([
                'profiles.id',
                DB::raw('COUNT(f_count.id) as followers_count'),
                DB::raw('2 as suggestion_type'),
                DB::raw('0 as is_following'),
            ])
                ->join('followers as f_follower', 'f_follower.profile_id', '=', 'profiles.id')
                ->leftJoin('followers as f_count', 'f_count.following_id', '=', 'profiles.id')
                // @phpstan-ignore argument.type
                ->where('f_follower.following_id', $id)
                ->whereNotExists(function ($query) use ($authProfileId) {
                    $query->select(DB::raw(1))
                        ->from('followers')
                        ->whereColumn('followers.following_id', 'profiles.id')
                        ->where('followers.profile_id', $authProfileId);
                })
                ->whereNotExists(function ($query) use ($authProfileId) {
                    $query->select(DB::raw(1))
                        ->from('user_filters')
                        ->whereColumn('user_filters.account_id', 'profiles.id')
                        ->where('user_filters.profile_id', $authProfileId);
                })
                ->whereNotExists(function ($query) use ($authProfileId) {
                    $query->select(DB::raw(1))
                        ->from('user_filters')
                        ->whereColumn('user_filters.profile_id', 'profiles.id')
                        ->where('user_filters.account_id', $authProfileId);
                })
                ->where('profiles.id', '!=', $authProfileId)
                ->whereNotIn('profiles.id', $suggestions->pluck('id'))
                ->groupBy('profiles.id')
                ->orderByDesc('followers_count')
                ->limit($needed)
                ->get();

            $suggestions = $suggestions->concat($followersOfTarget);
        }

        if ($suggestions->count() < $limit) {
            $needed = $limit - $suggestions->count();

            $popularAccounts = Profile::select([
                'profiles.id',
                DB::raw('COUNT(f_count.id) as followers_count'),
                DB::raw('3 as suggestion_type'),
                DB::raw('0 as is_following'),
            ])
                ->leftJoin('followers as f_count', 'f_count.following_id', '=', 'profiles.id')
                ->whereNotExists(function ($query) use ($authProfileId) {
                    $query->select(DB::raw(1))
                        ->from('followers')
                        ->whereColumn('followers.following_id', 'profiles.id')
                        ->where('followers.profile_id', $authProfileId);
                })
                ->whereNotExists(function ($query) use ($authProfileId) {
                    $query->select(DB::raw(1))
                        ->from('user_filters')
                        ->whereColumn('user_filters.account_id', 'profiles.id')
                        ->where('user_filters.profile_id', $authProfileId);
                })
                ->whereNotExists(function ($query) use ($authProfileId) {
                    $query->select(DB::raw(1))
                        ->from('user_filters')
                        ->whereColumn('user_filters.profile_id', 'profiles.id')
                        ->where('user_filters.account_id', $authProfileId);
                })
                ->where('profiles.id', '!=', $authProfileId)
                ->whereNotIn('profiles.id', $suggestions->pluck('id'))
                ->groupBy('profiles.id')
                ->orderByDesc('followers_count')
                ->limit($needed)
                ->get();

            $suggestions = $suggestions->concat($popularAccounts);
        }

        return AccountCompactResource::collection($suggestions);
    }

    public function accountVideoLikes(Request $request)
    {
        $validated = $request->validate([
            'cursor' => 'sometimes|string|max:2000',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);
        $user = $request->user();
        abort_unless($user->status === 1, 404);

        $preCursor = $validated['cursor'] ?? null;
        $limit = $validated['limit'] ?? 10;

        $ctx = hash('sha256', implode('|', [
            $user->id,
            'self-likes',
            'limit:'.$limit,
            'order:id_desc',
        ]));

        $decodedCursor = null;
        $hops = 0;
        $maxPages = $user->is_admin ? 100 : 10;
        $maxItems = $user->is_admin ? 300 : 120;

        if ($request->filled('cursor')) {
            ['cursor' => $decodedCursor, 'hops' => $hops] = CursorToken::decode($request->input('cursor'), $ctx);

            if ($hops >= $maxPages) {
                return $this->defaultCursorTokenResponse($request, $limit);
            }

            if (($hops * $limit) >= $maxItems) {
                return $this->defaultCursorTokenResponse($request, $limit);
            }
        }

        $pager = VideoLike::where('video_likes.profile_id', $user->profile_id)
            ->join('videos', 'videos.id', '=', 'video_likes.video_id')
            ->join('profiles', 'profiles.id', '=', 'videos.profile_id')
            ->where('videos.status', 2)
            ->where('profiles.status', 1)
            ->select('video_likes.*')
            ->orderByDesc('video_likes.id')
            ->cursorPaginate(
                perPage: $limit,
                columns: ['*'],
                cursorName: 'cursor',
                cursor: $decodedCursor
            );

        $nextLaravelCursor = $pager->nextCursor()?->encode();
        $nextCursorToken = CursorToken::encode($nextLaravelCursor, $ctx, $hops + 1);

        $tags = $pager->getCollection();

        return UserVideoLikeResource::collection($tags)->additional([
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'path' => $request->url(),
                'per_page' => $limit,
                'next_cursor' => $nextCursorToken,
                'prev_cursor' => $preCursor,
            ],
        ]);
    }

    public function defaultCursorTokenResponse($request, $limit)
    {
        return response()->json([
            'data' => [],
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'path' => $request->url(),
                'per_page' => $limit,
                'next_cursor' => null,
                'prev_cursor' => null,
            ],
        ]);
    }

    public function getProfileLinks(Request $request)
    {
        $profile = $request->user()->profile;
        $links = $profile->profileLinks()->orderBy('position')->get();
        $res = [
            'id' => (string) $profile->id,
            'min_threshold' => (int) LinkLimitService::getMinThreshold(),
            'total_allowed' => (int) LinkLimitService::getMaxLinks($profile),
            'available_slots' => (int) LinkLimitService::getRemainingSlots($profile),
            'can_add' => (bool) LinkLimitService::canAddLink($profile),
            'links' => $links->map(fn ($link) => [
                'id' => (string) $link->id,
                'url_pretty' => str_replace('https://', '', $link->url),
                'url' => $link->url,
                'created_at' => $link->created_at->format('c'),
            ]),
        ];

        return $this->data($res);
    }

    public function removeProfileLink(Request $request, $id)
    {
        $profile = $request->user()->profile;
        $link = ProfileLink::whereProfileId($profile->id)->findOrFail($id);

        $link->delete();

        $remainingLinks = ProfileLink::whereProfileId($profile->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($remainingLinks as $index => $remainingLink) {
            $remainingLink->position = $index;
            $remainingLink->save();
        }

        $profile->syncLinksJson();

        return $this->data([
            'id' => (string) $profile->id,
            'message' => 'Successfully deleted',
        ]);
    }

    public function getPushNotificationStatus(Request $request)
    {
        $allowed = app(ConfigService::class)->pushNotifications();
        $user = $request->user();

        $res = [
            'allowed' => (bool) $allowed,
            'enabled' => (bool) $user->push_token,
            'platform' => $user->push_token_platform,
        ];

        return $this->data($res);
    }

    public function enablePushNotifications(Request $request)
    {
        $allowed = app(ConfigService::class)->pushNotifications();

        if (! $allowed) {
            return $this->error('Push Notifications are not enabled on this server', 422);
        }

        $validated = $request->validate([
            'token' => 'required|string|starts_with:ExponentPushToken[',
            'platform' => 'required|string|in:android,ios',
        ]);
        $user = $request->user();

        if ($user->status != 1 || ! $user->email_verified_at) {
            return $this->error('You do not have permission for this.', 422);
        }

        $user->update([
            'push_token' => $validated['token'],
            'push_token_verified_at' => now(),
            'push_token_platform' => $validated['platform'],
        ]);

        return $this->success();
    }

    public function disablePushNotifications(Request $request)
    {
        $allowed = app(ConfigService::class)->pushNotifications();

        if (! $allowed) {
            return $this->error('Push Notifications are not enabled on this server', 422);
        }

        $user = $request->user();

        if ($user->status != 1 || ! $user->email_verified_at) {
            return $this->error('You do not have permission for this.', 422);
        }

        $user->update(['push_token' => null, 'push_token_verified_at' => null]);

        return $this->success();
    }

    public function profileLinkStore(Request $request)
    {
        $profile = $request->user()->profile;
        if (! LinkLimitService::canAddLink($profile)) {
            return $this->error('You have reached your maximum link limit', 422);
        }
        $validator = Validator::make($request->all(), [
            'url' => 'required|active_url:https|max:120',
        ]);

        if ($validator->fails()) {
            return $this->error('Invalid URL provided', 422);
        }

        $validation = BlockedDomainService::validateUrl($request->url);
        if (! $validation['valid']) {
            return $this->error('Invalid URL provided', 422);
        }

        $existingLink = $profile->profileLinks()
            ->where('url', $request->url)
            ->first();

        if ($existingLink) {
            return $this->error('You have already added this link to your profile', 422);
        }

        $maxPosition = $profile->profileLinks()->max('position') ?? -1;

        $profile->profileLinks()->create([
            'url' => $request->url,
            'position' => $maxPosition + 1,
        ]);

        $profile->syncLinksJson();

        $links = $profile->profileLinks()->orderBy('position')->get();

        $res = [
            'id' => (string) $profile->id,
            'min_threshold' => (int) LinkLimitService::getMinThreshold(),
            'total_allowed' => (int) LinkLimitService::getMaxLinks($profile),
            'available_slots' => (int) LinkLimitService::getRemainingSlots($profile),
            'can_add' => (bool) LinkLimitService::canAddLink($profile),
            'links' => $links,
        ];

        return $this->data($res);
    }
}
