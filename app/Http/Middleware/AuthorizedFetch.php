<?php

namespace App\Http\Middleware;

use App\Federation\Signatures\InvalidSignatureException;
use App\Models\InstanceActor;
use App\Models\Profile;
use App\Services\ConfigService;
use App\Services\HttpSignatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizedFetch
{
    protected $signatureService;

    public function __construct(HttpSignatureService $signatureService)
    {
        $this->signatureService = $signatureService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $config = app(ConfigService::class);

        if (! $config->federation()) {
            return abort(404, 'Resource not found');
        }

        if ($config->federationAuthorizedFetch()) {
            if (! $request->hasHeader('Signature')) {
                return abort(403, 'Resource not found.1');
            }

            try {
                $incoming = $this->signatureService->parseIncoming(
                    $request->headers->all(),
                    $request->method(),
                    $request->getRequestUri(),
                    $request->getContent() === '' ? null : $request->getContent()
                );
            } catch (InvalidSignatureException $e) {
                abort(403, 'Resource not found.3');
            }

            $keyId = $this->stripUrlFragments($incoming->keyId);

            if (! $keyId) {
                abort(403, 'Resource not found.3.1');
            }

            if ($config->federationMode() != 'open') {
                $allowedHosts = $config->federationAllowedServers();
                $remoteDomain = parse_url($keyId, PHP_URL_HOST);
                if ($remoteDomain && ! in_array($remoteDomain, $allowedHosts)) {
                    return abort(403, 'Resource not found.2');
                }
            }

            $actor = InstanceActor::findOrCreateFromUrl($keyId);

            if ($actor && $actor->public_key && $incoming->verify($actor->public_key)) {
                return $next($request);
            }

            $profile = Profile::whereUri($keyId)->first();

            if ($profile && $profile->public_key && $incoming->verify($profile->public_key)) {
                return $next($request);
            }

            return abort(403, 'Resource not found.4');
        }

        return $next($request);
    }

    protected function stripUrlFragments($url)
    {
        $parsed = parse_url($url);

        $cleanUrl = '';

        if (isset($parsed['scheme'])) {
            if ($parsed['scheme'] != 'https') {
                return;
            }

            $cleanUrl .= 'https://';
        }

        if (isset($parsed['host'])) {
            $cleanUrl .= $parsed['host'];
        }

        if (isset($parsed['path'])) {
            $cleanUrl .= $parsed['path'];
        }

        return $cleanUrl;
    }
}
