<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\HashidService;
use App\Services\VideoService;
use Illuminate\Http\Request;

class EmbedController extends Controller
{
    public function show(Request $request, string $shortcode)
    {
        $id = HashidService::safeDecode($shortcode);

        $theme = match ($request->query('theme')) {
            'light' => 'light',
            'dark' => 'dark',
            default => 'light'
        };

        $video = Video::published()->embeddable()->safeForWork()->local()->find($id);

        if (! $video) {
            return response()->view('embed.unavailable', [
                'theme' => $theme,
            ]);
        }

        $res = VideoService::getMediaData($video->id);

        if (! $res) {
            return response()->view('embed.unavailable', [
                'theme' => $theme,
            ]);
        }
        
        if ($video->has_audio && $video->sound_id) {
            $res['audio'] = [
                'cover' => data_get($res, 'account.avatar', '/storage/avatars/default.jpg'),
                'url' => url('/sounds/'.$video->sound_id),
            ];
        }

        return response()->view('embed.player', [
            'video' => $res,
            'shortcode' => $shortcode,
            'startTime' => $request->integer('t', 0),
            'theme' => $theme,
        ]);
    }
}
