<?php

namespace App\Services;

use App\Models\Video;
use Cache;
use Illuminate\Support\Str;
use Storage;

class VideoService
{
    const CACHE_KEY = 'api:s:video:';

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
                $ext = pathinfo($video->vid, PATHINFO_EXTENSION);
                $url = str_replace('.'.$ext, '.jpg', $video->vid);
                $thumb = Storage::disk('s3')->url($url);
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
                'url' => $video->shareUrl(),
                'likes' => $video->likes,
                'comments' => $video->comments,
                'created_at' => $video->created_at->format('c'),
                'media' => [
                    'duration' => $video->duration,
                    'width' => 1280,
                    'height' => 720,
                    'thumbnail' => $thumb,
                    'src_url' => $mediaUrl,
                ],
            ];
        });
    }
}
