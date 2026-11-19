<?php

namespace App\Http\Middleware;

use App\Federation\Migration;
use App\Federation\Signatures\InvalidSignatureException;
use App\Services\ConfigService;
use App\Services\HttpSignatureService;
use App\Services\Migration\AttestationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MigrationSignedFetch
{
    public function __construct(
        protected HttpSignatureService $signatures,
        protected AttestationService $attestation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! app(ConfigService::class)->federation()) {
            abort(404);
        }

        if (! $this->signatures->hasSignature($request->headers->all())) {
            return $this->deny();
        }

        try {
            $incoming = $this->signatures->parseIncoming(
                $request->headers->all(),
                $request->method(),
                $request->getRequestUri(),
                null
            );
        } catch (InvalidSignatureException $e) {
            return $this->deny();
        }

        $keyId = $incoming->keyId;
        $host = Migration::host($keyId);

        if (! $host || ! str_starts_with($keyId, 'https://')) {
            return $this->deny();
        }

        $key = $this->attestation->publicKeyFor($keyId);

        if (! $key || ! $incoming->verify($key)) {
            $key = $this->attestation->publicKeyFor($keyId, true);

            if (! $key || ! $incoming->verify($key)) {
                return $this->deny();
            }
        }

        $request->attributes->set('migration_signer', $host);

        return $next($request);
    }

    protected function deny(): Response
    {
        return response()->json(['error' => 'Resource not found'], 404, [
            'Content-Type' => 'application/activity+json',
        ]);
    }
}
