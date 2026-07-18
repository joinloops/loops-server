<?php

namespace App\Services;

use App\Models\Hashtag;
use App\Models\VideoHashtag;
use Illuminate\Support\Facades\Cache;

class ExploreService
{
    const TRENDING_TAGS_KEY = 'explore:trending_tags';

    const GUEST_TAG_FEED_KEY = 'explore:getTagFeed_v0:';

    public function getTrendingTags($refresh = false)
    {
        if ($refresh) {
            Cache::forget(self::TRENDING_TAGS_KEY);
        }

        return Cache::remember(self::TRENDING_TAGS_KEY, now()->addWeek(), function () {
            return Hashtag::select(['id', 'name', 'count'])
                ->where('can_trend', true)
                ->orderByDesc('count')
                ->limit(12)
                ->get()
                ->toArray();
        });
    }

    public function getGuestTagFeed($hashtagId, $refresh = false)
    {
        $key = self::GUEST_TAG_FEED_KEY.$hashtagId;
        $minLikes = (int) config('loops.explore.tags.min_likes.guest', 10);

        $candidateHashtags = Cache::remember($key, now()->addHours(12), function () use ($hashtagId, $minLikes) {
            return VideoHashtag::where('video_hashtags.hashtag_id', $hashtagId)
                ->where('video_hashtags.visibility', 1)
                ->join('videos', 'videos.id', '=', 'video_hashtags.video_id')
                ->join('profiles', 'profiles.id', '=', 'videos.profile_id')
                ->where('videos.status', 2)
                ->where('videos.likes', '>', $minLikes)
                ->where('profiles.status', 1)
                ->select('video_hashtags.*')
                ->orderByDesc('video_hashtags.id')
                ->limit(100)
                ->get();
        });

        return $candidateHashtags->filter(function ($videoHashtag) use ($minLikes) {
            $videoData = app(VideoService::class)->getMediaData($videoHashtag->video_id);
            if (empty($videoData)) {
                return false;
            }

            $likes = data_get($videoData, 'likes', 0);

            if ($likes < $minLikes) {
                return false;
            }

            $profileId = data_get($videoData, 'account.id');
            if (! $profileId) {
                return false;
            }

            return app(AccountService::class)->get($profileId);
        })->take(11)->values();
    }
}
