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
        return $this->respond($request, $this->service->discovery(), 'public, max-age=86400', 'Accept-Encoding');
    }

    public function show(Request $request): Response
    {
        $user = $this->service->accountFromRequest($request);

        if ($user) {
            return $this->respond(
                $request,
                $this->service->forUser($user),
                'private, max-age='.ClientConfigService::ACCOUNT_MAX_AGE_SECONDS,
                'Accept-Encoding, Authorization'
            );
        }

        return $this->respond(
            $request,
            $this->service->get(),
            'public, max-age='.ClientConfigService::CACHE_TTL_SECONDS,
            'Accept-Encoding, Authorization'
        );
    }

    protected function respond(Request $request, array $payload, string $cacheControl, string $vary): Response
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $etag = '"'.hash('xxh128', $body).'"';

        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Expose-Headers' => 'ETag',
            'Cache-Control' => $cacheControl,
            'ETag' => $etag,
            'Vary' => $vary,
        ];

        $ifNoneMatch = array_map('trim', explode(',', (string) $request->header('If-None-Match')));

        if (in_array($etag, $ifNoneMatch, true) || in_array('W/'.$etag, $ifNoneMatch, true)) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers + ['Content-Type' => 'application/json']);
    }
}
