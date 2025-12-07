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
use App\Jobs\Federation\DeliverFollowRequest;
use App\Jobs\Federation\DeliverUndoFollowActivity;
use App\Jobs\Federation\DeliverUndoFollowRequestActivity;
use App\Models\Follower;
use App\Models\FollowRequest;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\UserFilter;
use App\Services\AccountService;
use App\Services\FollowerService;
use App\Services\NotificationService;
use App\Services\UserFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $pid = $request->user()->profile_id;

        return NotificationResource::collection(
            Notification::whereUserId($pid)
                ->whereIn('type', [11, 15, 16, 21, 22, 23, 31])
                ->orderByDesc('id')
                ->cursorPaginate(20)
        );
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
        $pid = $request->user()->profile_id;
        Notification::whereUserId($pid)->update(['read_at' => now()]);
        NotificationService::clearUnreadCount($pid);

        return $this->success();
    }

    public function accountBlock(Request $request, $id)
    {
        $pid = $request->user()->profile_id;
        abort_if($pid == $id, 403, 'You cannot block yourself');

        $profile = Profile::findOrFail($id);

        UserFilter::updateOrCreate([
            'profile_id' => $pid,
            'account_id' => $profile->id,
        ]);

        Follower::whereProfileId($pid)->whereFollowingId($profile->id)->delete();
        Follower::whereProfileId($profile->id)->whereFollowingId($pid)->delete();
        FollowRequest::whereProfileId($pid)->whereFollowingId($profile->id)->delete();
        FollowRequest::whereProfileId($profile->id)->whereFollowingId($pid)->delete();
        FollowerService::refreshAndSync($pid, $profile->id);
        $res = (new ProfileResource($profile))->toArray($request);
        $res['is_blocking'] = true;

        return response()->json($res);
    }

    public function accountUnblock(Request $request, $id)
    {
        $pid = $request->user()->profile_id;
        abort_if($pid == $id, 403, 'You cannot unblock yourself');

        $profile = Profile::findOrFail($id);

        UserFilter::whereProfileId($pid)
            ->whereAccountId($profile->id)
            ->delete();

        FollowerService::refreshAndSync($pid, $profile->id);
        $res = (new ProfileResource($profile))->toArray($request);
        $res['is_blocking'] = false;

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

        return $this->data(AccountService::get($id));
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

        return $this->data(AccountService::get($id));
    }

    public function undoFollowRequest(Request $request, $id)
    {
        $pid = $request->user()->profile_id;

        if ($pid == $id) {
            return $this->error('You cannot unfollow your own account', 422);
        }

        $profile = Profile::findOrFail($id);
        $followRequest = FollowRequest::whereProfileId($pid)->whereFollowingId($profile->id)->firstOrFail();

        if ($profile->local) {
            $followRequest->delete();

            return $this->data(AccountService::get($id));
        } else {
            $followRequest->update(['following_state' => 5]);
            DeliverUndoFollowRequestActivity::dispatch($followRequest)->onQueue('activitypub-out');
        }

        return $this->data(AccountService::get($id));
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

        $account = AccountService::get($id);

        if (! $account) {
            return $this->error('Record not found');
        }

        $res = [
            'pending_follow_request' => false,
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
        $pid = $request->user()->profile_id;

        $accounts = Profile::active()
            ->whereLocal(true)
            ->where('id', '!=', $pid)
            ->orderByDesc('followers')
            ->take(10)
            ->pluck('id');

        $res = $accounts->map(function ($id) {
            return AccountService::get($id);
        })->filter(function ($acct) use ($pid) {
            if (! $acct) {
                return false;
            }

            if (FollowerService::follows($pid, $acct['id'])) {
                return false;
            }

            return true;
        })->shuffle()->toArray();

        return $this->data($res);
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
            ->join('profiles', 'followers.profile_id', '=', 'profiles.id');

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
            ->join('profiles', 'followers.following_id', '=', 'profiles.id');

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
            'profiles.*',
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
            ->where('profiles.id', '!=', $authProfileId)
            ->groupBy('profiles.id')
            ->orderByDesc('followers_count')
            ->limit($limit)
            ->get();

        $suggestions = $targetFollows;

        if ($suggestions->count() < $limit) {
            $needed = $limit - $suggestions->count();

            $followersOfTarget = Profile::select([
                'profiles.*',
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
                'profiles.*',
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
}
