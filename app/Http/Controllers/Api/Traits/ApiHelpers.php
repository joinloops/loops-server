<?php

namespace App\Http\Controllers\Api\Traits;

use Stevebauman\Purify\Facades\Purify;

trait ApiHelpers
{
    public function data($data, $addl = false, $code = 200, $headers = [])
    {
        $res = compact('data');
        if ($addl && is_array($addl)) {
            $res = array_merge($res, $addl);
        }

        return response()->json($res, $code, $headers, JSON_UNESCAPED_SLASHES);
    }

    public function error($msg, $code = 403, $headers = [])
    {
        return response()->json([
            'data' => [],
            'error' => [
                'code' => $code,
                'message' => $msg,
            ],
        ], $code, $headers);
    }

    public static function purify($text)
    {
        return Purify::clean($text);
    }

    public function success()
    {
        return response()->json([
            'data' => [],
            'error' => [
                'code' => 'ok',
                'message' => '',
            ],
        ]);
    }
}
