<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RemoteSearchLimitException;
use App\Federation\Validators\NoteWithVideoAttachmentValidator;
use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\SearchResultResource;
use App\Http\Resources\StarterKitResource;
use App\Http\Resources\VideoResource;
use App\Jobs\Federation\FetchRemoteAvatarJob;
use App\Jobs\Federation\ProcessRemoteVideoJob;
use App\Jobs\StarterKit\FetchRemoteStarterKitMedia;
use App\Models\Hashtag;
use App\Models\Profile;
use App\Models\RemoteSearchImport;
use App\Models\StarterKit;
use App\Models\UserFilter;
use App\Models\Video;
use App\Services\ActivityPubService;
use App\Services\HashidService;
use App\Services\SanitizeService;
use App\Services\StarterKitService;
use App\Services\VideoService;
use App\Services\WebfingerService;
use App\Support\CursorToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    use ApiHelpers;

    protected WebfingerService $webfingerService;

    public function __construct(WebfingerService $webfingerService)
    {
        $this->middleware('auth');
        $this->webfingerService = $webfingerService;
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:1|max:100',
            'type' => 'sometimes|in:top,all,videos,users,hashtags,starter_kits',
            'limit' => 'sometimes|integer|min:1|max:20',
            'cursor' => 'sometimes|string|max:2000',
        ]);

        $maxPages = 5;
        $type = $validated['type'] ?? 'all';

        $maxItems = match ($type) {
            'top' => 12,
            'all' => 12,
            'users' => 12,
            'videos' => 24,
            'hashtags' => 30,
            'starter_kits' => 20,
            default => 12,
        };

        $curCursor = $request->input('cursor') ?? null;
        $limit = $validated['limit'] ?? 10;
        $query = trim($validated['query']);

        $ctx = hash('sha256', implode('|', [
            $request->user()->id ?? 'guest',
            $type,
            $limit,
            mb_strtolower($query),
        ]));

        $decodedCursor = null;
        $hops = 0;

        if ($request->filled('cursor')) {
            ['cursor' => $decodedCursor, 'hops' => $hops] = CursorToken::decode($request->input('cursor'), $ctx);

            if ($hops >= $maxPages) {
                return $this->defaultResponse($request, $limit);
            }

            if (($hops * $limit) >= $maxItems) {
                return $this->defaultResponse($request, $limit);
            }
        }

        $authProfileId = $request->user()?->profile_id;

        $esc = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query);
        $like = "{$esc}%";

        $hashtagsData = collect();
        $usersData = collect();
        $videosData = collect();
        $starterKitsData = collect();
        $pager = null;
        $nextCursorToken = null;
        $nextLaravelCursor = null;

        if (in_array($type, ['all', 'top'])) {
            $videos = Video::query()
                ->select(['id', 'profile_id', 'caption', 'likes', 'status', 'visibility', 'created_at'])
                ->where('status', 2)
                ->where('visibility', 1)
                ->where('caption', 'like', '%'.$like)
                ->orderByDesc('likes')
                ->orderByDesc('id')
                ->cursorPaginate(
                    perPage: $limit,
                    columns: ['*'],
                    cursorName: 'cursor',
                    cursor: $decodedCursor
                )
                ->withQueryString();

            $videosData = $videos->getCollection();
            $pager = $videos;
            $nextLaravelCursor = $pager->nextCursor()?->encode();
            $nextCursorToken = CursorToken::encode($nextLaravelCursor, $ctx, $hops + 1);

            $usersData = Profile::query()
                ->select(['profiles.id', 'profiles.username', 'profiles.name', 'profiles.followers', 'profiles.status'])
                ->withFollowingStatus($authProfileId)
                ->where(function ($q) use ($like) {
                    $q->where('username', 'like', $like)
                        ->orWhere('name', 'like', $like);
                })
                ->where('status', 1)
                ->orderByDesc('followers')
                ->limit(2)
                ->get();

            $hashtags = Hashtag::query()
                ->select(['id', 'name', 'name_normalized', 'count', 'can_search', 'created_at'])
                ->where('can_search', true)
                ->where('name', 'like', $like)
                ->orderByDesc('count')
                ->limit(6)
                ->get();

            $starterKits = StarterKit::query()
                ->select(['id', 'title', 'description', 'slug', 'remote_url', 'approved_accounts', 'is_local', 'is_discoverable', 'is_sensitive', 'uses', 'icon_url', 'header_url', 'profile_id', 'total_accounts', 'status', 'created_at', 'updated_at'])
                ->where('status', 10)
                ->where(function ($q) use ($like) {
                    $q->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like);
                })
                ->orderByDesc('approved_accounts')
                ->limit(3)
                ->get();

            $starterKitsData = StarterKitResource::collection($starterKits);
            $hashtagsData = $hashtags;
        }

        if ($type === 'videos') {
            $videos = Video::query()
                ->select(['id', 'profile_id', 'caption', 'likes', 'status', 'visibility', 'created_at'])
                ->where('status', 2)
                ->where('visibility', 1)
                ->where('caption', 'like', '%'.$like)
                ->orderByDesc('likes')
                ->orderByDesc('id')
                ->cursorPaginate(
                    perPage: $limit,
                    columns: ['id', 'profile_id', 'caption', 'likes', 'status', 'visibility', 'created_at'],
                    cursorName: 'cursor',
                    cursor: $decodedCursor
                )
                ->withQueryString();

            $videosData = $videos->getCollection();
            $pager = $videos;
            $nextLaravelCursor = $pager->nextCursor()?->encode();
            $nextCursorToken = CursorToken::encode($nextLaravelCursor, $ctx, $hops + 1);
        }

        if ($type === 'users') {
            $users = Profile::query()
                ->select(['profiles.id', 'profiles.username', 'profiles.name', 'profiles.followers', 'profiles.status'])
                ->withFollowingStatus($authProfileId)
                ->where(function ($q) use ($like) {
                    $q->where('username', 'like', $like)
                        ->orWhere('name', 'like', $like);
                })
                ->where('profiles.status', 1)
                ->orderByDesc('profiles.followers')
                ->orderByDesc('profiles.id')
                ->cursorPaginate(
                    perPage: $limit,
                    columns: ['*'],
                    cursorName: 'cursor',
                    cursor: $decodedCursor
                )
                ->withQueryString();

            $usersData = $users->getCollection();
            $pager = $users;
            $nextLaravelCursor = $pager->nextCursor()?->encode();
            $nextCursorToken = CursorToken::encode($nextLaravelCursor, $ctx, $hops + 1);
        }

        if ($type === 'hashtags') {
            $hashtags = Hashtag::query()
                ->select(['id', 'name', 'name_normalized', 'count', 'can_search', 'created_at'])
                ->where('can_search', true)
                ->where('name', 'like', $like)
                ->orderByDesc('count')
                ->cursorPaginate(
                    perPage: $limit,
                    columns: ['*'],
                    cursorName: 'cursor',
                    cursor: $decodedCursor
                )
                ->withQueryString();

            $hashtagsData = $hashtags->getCollection();
            $pager = $hashtags;
            $nextLaravelCursor = $pager->nextCursor()?->encode();
            $nextCursorToken = CursorToken::encode($nextLaravelCursor, $ctx, $hops + 1);
        }

        if ($type === 'starter_kits') {
            $urlKit = $this->resolveStarterKitFromUrl($query);
            if ($urlKit) {
                $starterKitsData = collect([$urlKit]);
            } else {
                $kits = StarterKit::query()
                    ->select(['id', 'title', 'description', 'slug', 'remote_url', 'approved_accounts', 'is_local', 'is_discoverable', 'is_sensitive', 'uses', 'icon_url', 'header_url', 'profile_id', 'total_accounts', 'status', 'created_at', 'updated_at'])
                    ->where('status', 10)
                    ->where('visibility', 1)
                    ->where(function ($q) use ($like) {
                        $q->where('title', 'like', $like)
                            ->orWhere('description', 'like', '%'.$like);
                    })
                    ->orderByDesc('approved_accounts')
                    ->orderByDesc('id')
                    ->cursorPaginate(
                        perPage: $limit,
                        columns: ['*'],
                        cursorName: 'cursor',
                        cursor: $decodedCursor
                    )
                    ->withQueryString();

                $starterKitsData = StarterKitResource::collection($kits);
                $pager = $kits;
                $nextLaravelCursor = $pager->nextCursor()?->encode();
                $nextCursorToken = CursorToken::encode($nextLaravelCursor, $ctx, $hops + 1);
            }
        }

        return new SearchResultResource([
            'hashtags' => $hashtagsData,
            'users' => $usersData,
            'videos' => $videosData,
            'starter_kits' => $starterKitsData,
            'pager' => [
                'limit' => $limit,
                'prev_cursor' => $curCursor,
                'next_cursor' => $nextCursorToken,
                'has_more' => (bool) $nextLaravelCursor,
            ],
        ]);
    }

    public function defaultResponse($request, $limit)
    {
        return response()->json([
            'data' => [
                'hashtags' => [],
                'users' => [],
                'videos' => [],
                'starter_kits' => [],
            ],
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

    public function getUsers(SearchRequest $request)
    {
        $q = $request->input('q');
        $cleanQuery = Str::of($q)->startsWith('@') ? Str::substr($q, 1) : $q;
        $currentUserId = $request->user()->profile_id;

        $res = $this->searchLocal($cleanQuery, $currentUserId);

        if ($res->isEmpty() && $this->isRemoteQuery($cleanQuery)) {
            $remoteProfile = $this->searchRemote($cleanQuery, $currentUserId);

            if ($remoteProfile) {
                return ProfileResource::collection(collect([$remoteProfile]));
            }
        }

        return ProfileResource::collection($res);
    }

    protected function searchLocal(string $cleanQuery, int $currentUserId)
    {
        $escapedQuery = str_replace(['%', '_'], ['\%', '\_'], $cleanQuery);

        return Profile::select([
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
    }

    protected function searchRemote(string $query, int $currentUserId): ?Profile
    {
        try {
            if (filter_var($query, FILTER_VALIDATE_URL) && app(SanitizeService::class)->url($query, true)) {
                return $this->lookupByUrl($query, $currentUserId);
            }

            $profile = $this->webfingerService->findOrCreateRemoteActor($query);

            if (! $profile || $this->isBlocked($profile->id, $currentUserId)) {
                return null;
            }

            return $profile;
        } catch (\Exception $e) {
            Log::warning('Remote profile lookup failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function lookupByUrl(string $url, int $currentUserId): ?Profile
    {
        $existing = Profile::where('uri', $url)->first();

        if ($existing) {
            return $this->isBlocked($existing->id, $currentUserId) ? null : $existing;
        }

        return app(Profile::class)->findOrCreateFromUrl($url, null, true);
    }

    protected function isBlocked(int $profileId, int $currentUserId): bool
    {
        return UserFilter::where('profile_id', $currentUserId)
            ->where('account_id', $profileId)
            ->exists();
    }

    protected function isRemoteQuery(string $query): bool
    {
        if (preg_match('/^@?[a-zA-Z0-9_]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $query)) {
            return true;
        }

        if (filter_var($query, FILTER_VALIDATE_URL)) {
            return true;
        }

        return false;
    }

    protected function resolveStarterKitFromUrl(string $query): ?StarterKit
    {
        if (! filter_var($query, FILTER_VALIDATE_URL)) {
            return null;
        }

        $sanitize = app(SanitizeService::class);

        $match = $sanitize->matchUrlTemplate(
            url: $query,
            templates: [
                '/starter-kits/{hash}/{slug}',
                '/ap/kit/{id}',
            ],
            useAppHost: true,
            constraints: [
                'id' => '[0-9]+',
                'hash' => '[0-9a-zA-Z_-]{1,11}',
                'slug' => '[a-zA-Z0-9_-]+',
            ],
        );

        if ($match) {
            if (isset($match['hash']) && $match['hash']) {
                $hashId = HashidService::safeDecode($match['hash']);

                return StarterKit::where('status', 10)
                    ->findOrFail($hashId);
            }

            if (isset($match['id'])) {
                return StarterKit::where('status', 10)
                    ->findOrFail($match['id']);
            }
        }

        return $this->fetchRemoteStarterKit($query);
    }

    protected function fetchRemoteStarterKit(string $url): ?StarterKit
    {
        try {
            $existing = StarterKit::where(function ($query) use ($url) {
                $query->where('remote_object_url', $url)
                    ->orWhere('remote_url', $url);
            })->first();

            if ($existing) {
                return $existing;
            }

            $response = app(ActivityPubService::class)->get($url);

            if (! $response || ! is_array($response)) {
                return null;
            }

            $type = $response['type'] ?? null;
            if ($type !== 'FeaturedCollection') {
                return null;
            }

            return $this->importRemoteStarterKit($response, $url);
        } catch (\Exception $e) {
            Log::warning('Remote starter kit fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function importRemoteStarterKit(array $data, string $sourceUrl): ?StarterKit
    {
        $name = $data['name'] ?? null;
        $summary = isset($data['summary']) ? strip_tags($data['summary']) : null;
        $attributedTo = $data['attributedTo'] ?? null;
        $items = $data['orderedItems'] ?? [];
        $totalItems = $data['totalItems'] ?? count($items);
        $discoverable = $data['discoverable'] ?? false;
        $sensitive = $data['sensitive'] ?? false;

        if (! $name || ! $attributedTo || $sensitive || ! $discoverable) {
            return null;
        }

        $author = app(Profile::class)->findOrCreateFromUrl($attributedTo);

        if (! $author) {
            return null;
        }

        $apId = $data['id'] ?? $sourceUrl;

        $kit = StarterKit::updateOrCreate(
            ['remote_object_url' => $apId],
            [
                'title' => $name,
                'slug' => Str::slug($name),
                'description' => $summary,
                'profile_id' => $author->id,
                'total_accounts' => $totalItems,
                'approved_accounts' => $totalItems,
                'is_discoverable' => $discoverable,
                'is_local' => false,
                'remote_url' => $data['url'] ?? $sourceUrl,
                'remote_icon_url' => $this->extractImageHref($data['icon'] ?? null),
                'remote_header_url' => $this->extractImageHref($data['image'] ?? null),
                'status' => 10,
                'visibility' => '1',
                'created_at' => $data['published'] ?? now(),
                'updated_at' => $data['updated'] ?? now(),
            ]
        );

        $this->syncRemoteKitItems($kit, $items);

        FetchRemoteStarterKitMedia::dispatch($kit->id);

        return $kit;
    }

    protected function extractImageHref(?array $image): ?string
    {
        if (! $image || ($image['type'] ?? null) !== 'Image') {
            return null;
        }

        $url = $image['url'] ?? null;

        if (is_string($url)) {
            return $url;
        }

        if (is_array($url) && ($url['type'] ?? null) === 'Link') {
            return $url['href'] ?? null;
        }

        return null;
    }

    protected function syncRemoteKitItems(StarterKit $kit, array $items): void
    {
        $order = 0;

        foreach ($items as $item) {
            if (($item['type'] ?? null) !== 'FeaturedItem') {
                continue;
            }

            $actorUrl = $item['featuredObject'] ?? null;
            $objectType = $item['featuredObjectType'] ?? null;

            if (! $actorUrl || $objectType !== 'Person') {
                continue;
            }

            $profile = Profile::where('uri', $actorUrl)->first();

            if (! $profile) {
                try {
                    $profile = app(Profile::class)->findOrCreateFromUrl($actorUrl);
                } catch (\Exception $e) {
                    Log::debug('Failed to resolve kit item actor', ['url' => $actorUrl]);

                    continue;
                }
            }

            if (! $profile) {
                continue;
            }

            $kit->starterKitAccounts()->updateOrCreate(
                ['profile_id' => $profile->id],
                [
                    'kit_status' => 1,
                    'kit_account_local' => $profile->local,
                    'order' => $order++,
                    'approved_at' => now(),
                    'attestation_url' => $item['featureAuthorization'],
                    'remote_object_id' => $item['id'] ?? null,
                ]
            );
        }
    }

    public function remoteLookup(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:500',
        ]);

        $query = trim($validated['q']);
        $currentUserId = $request->user()->profile_id;

        if (app(SanitizeService::class)->isLocalObject($query)) {
            $res = $this->resolveUrl($request, $query, $currentUserId);

            $videos = [];
            $users = [];

            if ($res['type'] === 'video') {
                $videos = [new VideoResource($res['data'])];
            }

            if ($res['type'] === 'user') {
                $users = [$res['data']];
            }

            return response()->json([
                'data' => [
                    'hashtags' => [],
                    'users' => $users,
                    'videos' => $videos,
                    'starter_kits' => [],
                ],
                'links' => [
                    'first' => null,
                    'last' => null,
                    'prev' => null,
                    'next' => null,
                ],
                'meta' => [
                    'path' => $request->url(),
                    'per_page' => 10,
                    'next_cursor' => null,
                    'prev_cursor' => null,
                ],
            ]);
        }

        $validUrl = app(SanitizeService::class)->url($query, true, false);

        abort_if(! $validUrl, 403, 'Invalid url');

        if (! filter_var($query, FILTER_VALIDATE_URL)) {
            $cleanQuery = Str::of($query)->startsWith('@') ? Str::substr($query, 1) : $query;

            if (! $this->isRemoteQuery($cleanQuery)) {
                return response()->json([
                    'type' => null,
                    'data' => null,
                    'error' => 'Invalid query format',
                ], 422);
            }

            $profile = $this->searchRemote($cleanQuery, $currentUserId);

            if (! $profile) {
                return response()->json([
                    'type' => null,
                    'data' => null,
                    'error' => 'No remote account found',
                ]);
            }

            return response()->json([
                'data' => [
                    'hashtags' => [],
                    'users' => [new ProfileResource($profile)],
                    'videos' => [],
                    'starter_kits' => [],
                ],
                'links' => [
                    'first' => null,
                    'last' => null,
                    'prev' => null,
                    'next' => null,
                ],
                'meta' => [
                    'path' => $request->url(),
                    'per_page' => 10,
                    'next_cursor' => null,
                    'prev_cursor' => null,
                ],
            ]);
        }

        $result = $this->resolveUrl($request, $query, $currentUserId);

        if (! $result) {
            return response()->json([
                'type' => null,
                'data' => null,
                'error' => 'Could not resolve this URL',
            ], 404);
        }

        return response()->json($result);
    }

    public function remoteVideoStatus(Request $request)
    {
        $validated = $request->validate([
            'ap_id' => 'required|string|url|max:500',
        ]);

        $url = app(SanitizeService::class)->url($validated['ap_id'], true);

        if (! $url) {
            return response()->json(['status' => 'unavailable'], 404);
        }

        $currentUserId = $request->user()->profile_id;

        $video = Video::published()->where('ap_id', $validated['ap_id'])->first();

        if (! $video) {
            return response()->json(['status' => 'pending']);
        }

        if ($this->isBlocked($video->profile_id, $currentUserId)) {
            return response()->json(['status' => 'unavailable'], 404);
        }

        VideoService::deleteMediaData($video->id);

        return response()->json([
            'status' => 'ready',
            'video' => new VideoResource($video),
        ]);
    }

    protected function resolveUrl(Request $request, string $url, int $currentUserId): ?array
    {
        $sanitize = app(SanitizeService::class);

        $localResult = $this->resolveLocalUrl($request, $sanitize, $url, $currentUserId);
        if ($localResult) {
            return $localResult;
        }

        return $this->resolveRemoteUrl($request, $url, $currentUserId);
    }

    protected function resolveLocalUrl(Request $request, SanitizeService $sanitize, string $url, int $currentUserId): ?array
    {
        $match = $sanitize->matchUrlTemplate(
            url: $url,
            templates: [
                '/starter-kits/{hash}/{slug}',
                '/ap/kit/{id}',
            ],
            useAppHost: true,
            constraints: [
                'id' => '[0-9]+',
                'hash' => '[0-9a-zA-Z_-]{1,11}',
                'slug' => '[a-zA-Z0-9_-]+',
            ],
        );

        if ($match) {
            $kit = null;

            if (isset($match['hash'])) {
                $hashId = HashidService::safeDecode($match['hash']);
                $kit = $hashId ? StarterKit::where('status', 10)->find($hashId) : null;
            } elseif (isset($match['id'])) {
                $kit = StarterKit::where('status', 10)->find($match['id']);
            }

            if ($kit) {
                return ['type' => 'starter_kit', 'data' => $kit];
            }
        }

        $match = $sanitize->matchUrlTemplate(
            url: $url,
            templates: [
                '/v/{hash}',
                '/ap/users/{profileId}/video/{id}',
            ],
            useAppHost: true,
            constraints: [
                'id' => '[0-9]+',
                'profileId' => '[0-9]+',
                'hash' => '[0-9a-zA-Z_-]{1,11}',
            ],
        );

        if ($match) {
            $video = null;

            if (isset($match['hash'])) {
                $hashId = HashidService::safeDecode($match['hash']);
                $video = $hashId ? Video::published()->find($hashId) : null;
            } elseif (isset($match['id'], $match['profile_id'])) {
                $video = Video::published()->whereProfileId($match['profile_id'])->find($match['id']);
            }

            if ($video) {
                return ['type' => 'video', 'data' => $video];
            }
        }

        $match = $sanitize->matchUrlTemplate(
            url: $url,
            templates: [
                '/@{username}',
                '/ap/users/{id}',
            ],
            useAppHost: true,
            constraints: [
                'id' => '[0-9]+',
                'username' => '[a-zA-Z0-9._-]+',
            ],
        );

        if ($match) {
            $profile = null;

            if (isset($match['username'])) {
                $profile = Profile::where('username', $match['username'])->where('status', 1)->first();
            } elseif (isset($match['id'])) {
                $profile = Profile::where('status', 1)->find($match['id']);
            }

            if ($profile && ! $this->isBlocked($profile->id, $currentUserId)) {
                return ['type' => 'user', 'data' => new ProfileResource($profile)];
            }
        }

        return null;
    }

    protected function resolveRemoteUrl(Request $request, string $url, int $currentUserId): ?array
    {
        try {
            $existingKit = StarterKit::where(function ($q) use ($url) {
                $q->where('remote_object_url', $url)->orWhere('remote_url', $url);
            })->first();

            if ($existingKit) {
                return [
                    'data' => [
                        'hashtags' => [],
                        'users' => [],
                        'videos' => [],
                        'starter_kits' => [app(StarterKitService::class)->get($existingKit->id)],
                    ],
                ];
            }

            $existingProfile = Profile::where(function ($q) use ($url) {
                $q->where('uri', $url)->orWhere('remote_url', $url);
            })->first();

            if ($existingProfile && ! $this->isBlocked($existingProfile->id, $currentUserId)) {
                return [
                    'data' => [
                        'hashtags' => [],
                        'users' => [new ProfileResource($existingProfile)],
                        'videos' => [],
                        'starter_kits' => [],
                    ],
                ];
            }

            $response = app(ActivityPubService::class)->get($url);

            if (! $response || ! is_array($response)) {
                return null;
            }

            $type = $response['type'] ?? null;

            return match ($type) {
                'Person' => $this->handleRemoteActor($request, $response, $url, $currentUserId),
                'FeaturedCollection' => $this->handleRemoteFeaturedCollection($request, $response, $url),
                'Note' => $this->handleRemoteNote($request, $response, $url, $currentUserId),
                default => null,
            };
        } catch (\App\Exceptions\RemoteSearchLimitException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::warning('Remote URL resolve failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

    }

    protected function handleRemoteNote($request, array $data, string $url, int $currentUserId): ?array
    {
        $windowDays = 30;
        $limit = (int) config('loops.remote_search.monthly_video_limit', 30);

        $videoQuery = RemoteSearchImport::forProfile($currentUserId)
            ->ofType((new Video)->getMorphClass())
            ->withinDays($windowDays);

        $videoCount = (clone $videoQuery)->count();

        if ($videoCount >= $limit) {
            $oldest = (clone $videoQuery)
                ->orderBy('created_at')
                ->first();

            $availableAt = $oldest?->created_at
                ?->addDays($windowDays)
                ?->toIso8601String();

            throw new RemoteSearchLimitException(
                resource: 'video',
                limit: $limit,
                current: $videoCount,
                windowDays: $windowDays,
                availableAt: $availableAt,
            );
        }

        $validator = app(NoteWithVideoAttachmentValidator::class);

        try {
            $validator->validate($data);
        } catch (\Exception $e) {
            throw $e;
        }

        $profile = app(Profile::class)->findOrCreateFromUrl($data['attributedTo']);

        if (! $profile || $this->isBlocked($profile->id, $currentUserId)) {
            return null;
        }

        if (! $profile->last_fetched_at || $profile->last_fetched_at->lt(now()->subDays(30))) {
            FetchRemoteAvatarJob::dispatch($profile)->onQueue('actor-update');
        }

        $video = Video::published()->where('ap_id', $data['id'])->first();

        if (! $video) {
            $import = RemoteSearchImport::create([
                'profile_id' => $currentUserId,
                'search_url' => $url,
                'searchable_type' => (new Video)->getMorphClass(),
            ]);

            ProcessRemoteVideoJob::dispatch($profile->id, $data, $data['attachment'][0], $import->id)->onQueue('remote-video');

            return [
                'data' => [
                    'hashtags' => [],
                    'users' => [],
                    'videos' => [],
                    'starter_kits' => [],
                    'pending' => [
                        'type' => 'video',
                        'ap_id' => $data['id'],
                        'profile' => new ProfileResource($profile),
                        'submitted_at' => now()->toIso8601String(),
                    ],
                ],
            ];
        }

        return [
            'data' => [
                'hashtags' => [],
                'users' => [],
                'videos' => [new VideoResource($video)],
                'starter_kits' => [],
            ],
        ];
    }

    protected function handleRemoteActor($request, array $data, string $url, int $currentUserId): ?array
    {
        $profile = app(Profile::class)->findOrCreateFromUrl($data['id'] ?? $url);

        if (! $profile || $this->isBlocked($profile->id, $currentUserId)) {
            return null;
        }

        return [
            'data' => [
                'hashtags' => [],
                'users' => [new ProfileResource($profile)],
                'videos' => [],
                'starter_kits' => [],
            ],
        ];
    }

    protected function handleRemoteFeaturedCollection($request, array $data, string $url): ?array
    {
        $kit = $this->importRemoteStarterKit($data, $url);

        return [
            'data' => [
                'hashtags' => [],
                'users' => [],
                'videos' => [],
                'starter_kits' => [app(StarterKitService::class)->get($kit->id)],
            ],
        ];
    }
}
