<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoService
{
    const CACHE_KEY = 'api:s:video:v1:';

    public static function totalUserCount($pid, $onlyPublished = true, $refresh = false)
    {
        $key = self::CACHE_KEY.'total-user-count-pid:'.$pid;

        if ($onlyPublished) {
            $key = $key.':published';
        }

        if ($refresh) {
            Cache::forget(self::CACHE_KEY.'total-user-count-pid:'.$pid);
            Cache::forget(self::CACHE_KEY.'total-user-count-pid:'.$pid.':published');
        }

        return Cache::remember($key, now()->addDays(7), function () use ($pid, $onlyPublished) {
            return $onlyPublished ?
                Video::whereProfileId($pid)->published()->count() :
                Video::whereProfileId($pid)->count();
        });
    }

    public static function deleteMediaData($id)
    {
        return Cache::forget(self::CACHE_KEY.$id);
    }

    public static function getCompactStats($id, $clear = false)
    {
        if ($clear) {
            Cache::forget(self::CACHE_KEY.$id);
        }

        $res = collect(self::getMediaData($id));
        $filtered = $res->only(['id', 'hid', 'likes', 'comments', 'bookmarks', 'shares']);
        $merged = $filtered->merge(['profile_id' => (string) $res['account']['id']]);

        return $merged->all();
    }

    public static function getMediaData($id, $clear = false)
    {
        if ($clear) {
            Cache::forget(self::CACHE_KEY.$id);
        }

        return Cache::remember(self::CACHE_KEY.$id, now()->addHours(6), function () use ($id) {
            $video = Video::published()->find($id);
            if (! $video) {
                return false;
            }

            $thumb = url('/storage/videos/video-placeholder.jpg');
            if ($video->has_thumb) {
                if ($video->thumbnail) {
                    $thumb = $video->thumbnail;
                } elseif ($video->thumbnail_path) {
                    $thumb = Storage::disk('s3')->url($video->thumbnail_path);
                } else {
                    $ext = pathinfo($video->vid, PATHINFO_EXTENSION);
                    $url = str_replace('.'.$ext, '.jpg', $video->vid);
                    $thumb = Storage::disk('s3')->url($url);
                }
            }

            $mediaUrl = Storage::disk('s3')->url($video->vid_optimized);
            $captionText = Str::limit($video->caption ?? 'Untitled loop', 20);
            $captionText .= " • $video->likes likes • $video->comments comments";

            return [
                'id' => (string) $video->id,
                'hid' => $video->hashid(),
                'account' => AccountService::compact($video->profile_id),
                'caption' => $video->caption,
                'captionText' => $captionText,
                'captionLinked' => $video->caption ? AutoLinkerService::link($video->caption) : null,
                'url' => $video->shareUrl(),
                'likes' => $video->likes,
                'shares' => $video->shares,
                'comments' => $video->comment_state === 4 ? $video->comments : 0,
                'bookmarks' => $video->bookmarks,
                'is_sensitive' => $video->is_sensitive,
                'created_at' => $video->created_at->format('c'),
                'updated_at' => $video->updated_at->format('c'),
                'media' => [
                    'duration' => $video->duration,
                    'width' => $video->width ?? 720,
                    'height' => $video->height ?? 1280,
                    'thumbnail' => $thumb,
                    'src_url' => $mediaUrl,
                ],
            ];
        });
    }
}
