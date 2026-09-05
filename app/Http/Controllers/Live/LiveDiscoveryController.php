<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\Controller;
use App\Models\Follower;
use App\Models\LiveStream;
use App\Services\AccountService;
use App\Services\Live\LiveViewerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LiveDiscoveryController extends Controller
{
    protected const LIMIT = 20;

    public function __construct(protected LiveViewerService $viewers)
    {
        $this->middleware('auth');
    }

    public function active(Request $request)
    {
        if (! config('live.enabled')) {
            return response()->json(['data' => []]);
        }

        $profileId = optional($request->user())->profile_id;

        $streams = Cache::remember('loops:live:active', 30, function () {
            return LiveStream::query()
                ->live()
                ->where('visibility', 1)
                ->with(['profile', 'channel'])
                ->orderByDesc('started_at')
                ->limit(self::LIMIT * 3)
                ->get()
                ->filter(fn (LiveStream $stream) => $stream->channel && $stream->channel->is_enabled)
                ->values();
        });

        if ($streams->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $followingIds = $profileId
            ? $this->followingIds($profileId, $streams->pluck('profile_id')->all())
            : [];

        $data = $streams
            ->sortByDesc(
                fn (LiveStream $stream): int => in_array((int) $stream->profile_id, $followingIds, true) ? 1 : 0
            )
            ->take(self::LIMIT)
            ->map(function (LiveStream $stream) use ($followingIds): ?array {
                $profile = AccountService::get($stream->profile_id);
                $channel = $stream->channel;

                if (! $profile || ! $channel) {
                    return null;
                }

                return [
                    'stream_id' => (string) $stream->id,
                    'public_id' => $channel->public_id,
                    'title' => $stream->title,
                    'started_at' => $stream->started_at?->toISOString(),
                    'viewers' => $this->viewers->count($stream),
                    'is_following' => in_array(
                        (int) $stream->profile_id,
                        $followingIds,
                        true
                    ),
                    'account' => [
                        'id' => (string) $stream->profile_id,
                        'username' => $profile['username'],
                        'name' => $profile['name'],
                        'avatar' => $profile['avatar'],
                    ],
                ];
            })
            ->filter(fn (?array $row): bool => $row !== null)
            ->values();

        return response()->json(['data' => $data]);
    }

    protected function followingIds(int $profileId, array $candidateIds): array
    {
        if (empty($candidateIds)) {
            return [];
        }

        return Follower::where('profile_id', $profileId)
            ->whereIn('following_id', $candidateIds)
            ->pluck('following_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
