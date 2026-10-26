<?php

namespace App\Http\Controllers;

use App\Services\ClientConfigService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClientConfigController extends Controller
{
    public function __construct(protected ClientConfigService $service) {}

    public function discovery(Request $request): Response
    {
        return $this->respond($request, $this->service->discovery(), 86400);
    }

    public function show(Request $request): Response
    {
        return $this->respond($request, $this->service->get(), ClientConfigService::CACHE_TTL_SECONDS);
    }

    protected function respond(Request $request, array $payload, int $maxAge): Response
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $etag = '"'.hash('xxh128', $body).'"';

        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age='.$maxAge,
            'ETag' => $etag,
            'Vary' => 'Accept-Encoding',
        ];

        $ifNoneMatch = array_map('trim', explode(',', (string) $request->header('If-None-Match')));

        if (in_array($etag, $ifNoneMatch, true) || in_array('W/'.$etag, $ifNoneMatch, true)) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers + ['Content-Type' => 'application/json']);
    }
}
