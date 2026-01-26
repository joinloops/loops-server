<?php

namespace App\Http\Controllers;

use App\Http\Middleware\FederationEnabled;
use App\Http\Middleware\VerifyHttpSignature;
use App\Jobs\Federation\ProcessSharedInboxActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SharedInboxController extends Controller
{
    public function __construct()
    {
        $this->middleware(FederationEnabled::class);
        $this->middleware(VerifyHttpSignature::class);
    }

    public function sharedInbox(Request $request)
    {
        $activity = $request->json()->all();

        if (isset($activity['type']) && $activity['type'] === 'Delete') {
            if (! $this->validateDeleteOrigin($activity, $request)) {
                if (config('logging.dev_log')) {
                    Log::warning('Rejected Delete activity with mismatched origin', [
                        'activity_id' => $activity['id'] ?? null,
                        'signed_host' => $request->header('Host'),
                        'object' => is_array($activity['object']) ? ($activity['object']['id'] ?? 'unknown') : $activity['object'],
                    ]);
                }

                return response()->json(['error' => 'Delete origin mismatch'], 403);
            }
        }

        ProcessSharedInboxActivity::dispatch($activity, $request->attributes->get('activitypub_actor'))->onQueue('activitypub-in');

        return response()->json(['status' => 'accepted'], 202);
    }

    /**
     * Validate that a Delete activity's object matches the signed Host header
     */
    protected function validateDeleteOrigin(array $activity, Request $request): bool
    {
        if (! isset($activity['object'])) {
            return false;
        }

        $objectUrl = is_array($activity['object'])
            ? data_get($activity, 'object.id', null)
            : data_get($activity, 'object', null);

        if (! $objectUrl) {
            return false;
        }

        $signedOriginDomain = $request->attributes->get('activitypub_origin_domain');

        if (! $signedOriginDomain) {
            return false;
        }

        $objectDomain = parse_url($objectUrl, PHP_URL_HOST);

        if (! $objectDomain) {
            return false;
        }

        return strtolower($signedOriginDomain) === strtolower($objectDomain);
    }
}
