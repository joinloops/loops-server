<?php

namespace App\Http\Middleware;

use App\Services\HttpSignatureService;
use Closure;
use Illuminate\Http\Request;

class HasHttpSignature
{
    public function __construct(protected HttpSignatureService $signatures) {}

    public function handle(Request $request, Closure $next)
    {
        $headers = $request->headers->all();

        if (! $request->hasHeader('Signature')) {
            return response()->json(['error' => 'Missing signature header'], 401);
        }

        if (! $this->signatures->hasSignature($headers)) {
            return response()->json(['error' => 'Malformed signature header'], 401);
        }

        $request->attributes->set('raw_signature', $request->header('Signature'));
        $request->attributes->set('raw_headers', $headers);

        $keyId = $this->signatures->peekKeyId($headers);

        if ($keyId) {
            $originDomain = parse_url(preg_replace('/#.*$/', '', $keyId), PHP_URL_HOST);

            if ($originDomain) {
                $request->attributes->set('activitypub_origin_domain', strtolower($originDomain));
            }
        }

        return $next($request);
    }
}
