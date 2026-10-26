<?php

namespace App\Http\Controllers;

use App\Http\Middleware\FederationEnabled;
use App\Http\Middleware\HasHttpSignature;
use App\Jobs\Federation\ProcessInboxActivityWithVerification;
use App\Models\Profile;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function __construct()
    {
        $this->middleware(FederationEnabled::class);
        $this->middleware(HasHttpSignature::class);
    }

    public function userInbox(Request $request, Profile $actor)
    {
        if (! $actor->local) {
            return response()->json(['status' => 'accepted'], 202);
        }

        if ($actor->isPurged()) {
            return response()->json(['error' => 'Gone'], 410);
        }

        $activity = $request->json()->all();

        $headers = $request->attributes->get('raw_headers') ?? $request->headers->all();

        ProcessInboxActivityWithVerification::dispatch(
            $activity,
            $headers,
            $request->method(),
            $request->getRequestUri(),
            $request->getContent(),
            $actor,
            true
        )->onQueue('activitypub-in');

        return response()->json(['status' => 'accepted'], 202);
    }
}
