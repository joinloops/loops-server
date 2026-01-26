<?php

namespace App\Http\Middleware;

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

            $signature = $request->header('Signature');
            $headerParts = app(HttpSignatureService::class)->parseSignatureHeader($signature);

            if (! $headerParts) {
                abort(403, 'Resource not found.3.1');
            }

            if (! isset($headerParts['keyId'], $headerParts['headers'])) {
                abort(403, 'Resource not found.3');
            }

            $keyId = $this->stripUrlFragments($headerParts['keyId']);

            // Validate the remote actor's domain against allowedServers
            if ($config->federationMode() != 'open') {
                $allowedHosts = $config->federationAllowedServers();
                $remoteDomain = parse_url($keyId, PHP_URL_HOST);
                if ($remoteDomain && ! in_array($remoteDomain, $allowedHosts)) {
                    return abort(403, 'Resource not found.2');
                }
            }

            $signedHeadersList = explode(' ', strtolower($headerParts['headers']));

            $requiredHeaders = ['(request-target)', 'host', 'date'];
            foreach ($requiredHeaders as $required) {
                if (! in_array($required, $signedHeadersList)) {
                    abort(403, 'Missing required header: '.$required);
                }
            }

            $headers = $this->buildSignedHeaders($request, $signedHeadersList);

            $keyId = $this->stripUrlFragments($headerParts['keyId']);

            $actor = InstanceActor::findOrCreateFromUrl($keyId);

            if ($actor && $actor->public_key) {
                $verified = $this->signatureService->verify(
                    $signature,
                    $actor->public_key,
                    $headers,
                    $request->method(),
                    $request->getRequestUri()
                );

                if ($verified) {
                    return $next($request);
                }
            }

            $profile = Profile::whereUri($keyId)->first();

            if ($profile && $profile->public_key) {
                $verified = $this->signatureService->verify(
                    $signature,
                    $profile->public_key,
                    $headers,
                    $request->method(),
                    $request->getRequestUri()
                );

                if ($verified) {
                    return $next($request);
                }
            }

            return abort(403, 'Resource not found.4');
        }

        return $next($request);
    }

    /**
     * Build the headers array based on what was actually signed
     */
    protected function buildSignedHeaders(Request $request, array $signedHeadersList): array
    {
        $headers = [];

        foreach ($signedHeadersList as $headerName) {
            switch ($headerName) {
                case '(request-target)':
                    $headers['(request-target)'] = strtolower($request->method()).' '.$request->getRequestUri();
                    break;

                case 'host':
                    $headers['Host'] = $request->header('Host');
                    break;

                case 'date':
                    $headers['Date'] = $request->header('Date');
                    break;

                case 'content-type':
                    if ($request->hasHeader('Content-Type')) {
                        $headers['Content-Type'] = $request->header('Content-Type');
                    }
                    break;

                case 'digest':
                    if ($request->hasHeader('Digest')) {
                        $headers['Digest'] = $request->header('Digest');
                    }
                    break;

                case 'content-length':
                    if ($request->hasHeader('Content-Length')) {
                        $headers['Content-Length'] = $request->header('Content-Length');
                    }
                    break;

                default:
                    $headerValue = $request->header($headerName);
                    if ($headerValue) {
                        $properCase = implode('-', array_map('ucfirst', explode('-', $headerName)));
                        $headers[$properCase] = $headerValue;
                    }
                    break;
            }
        }

        return $headers;
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
