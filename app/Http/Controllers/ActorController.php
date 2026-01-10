<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;

class ActorController extends Controller
{
    public function show(Request $request, Profile $actor)
    {
        if (! $actor->local) {
            abort(404);
        }

        // Confirm profile is active (1)
        if ($actor->status != 1) {
            return $this->activityPubError('Record not available', 400);
        }

        if (! $this->acceptsActivityPub($request)) {
            return redirect($actor->getPublicUrl());
        }

        $actorData = $actor->toActivityPub();

        $actorData['endpoints'] = [
            'sharedInbox' => url('/ap/inbox'),
        ];

        return $this->activityPubResponse($actorData);
    }

    /**
     * Display the actor's outbox
     */
    public function outbox(Request $request, Profile $actor)
    {
        if (! $actor->local) {
            abort(404);
        }

        return $this->activityPubResponse([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getOutboxUrl(),
            'type' => 'OrderedCollection',
            'totalItems' => $actor->video_count,
        ]);
    }

    /**
     * Display the actor's followers collection
     */
    public function followers(Request $request, Profile $actor)
    {
        if (! $actor->local) {
            abort(404);
        }

        return $this->activityPubResponse([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getFollowersUrl(),
            'type' => 'OrderedCollection',
            'totalItems' => $actor->followers,
        ]);
    }

    /**
     * Display the actor's following collection
     */
    public function following(Request $request, Profile $actor)
    {
        if (! $actor->local) {
            abort(404);
        }

        return $this->activityPubResponse([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getFollowingUrl(),
            'type' => 'OrderedCollection',
            'totalItems' => $actor->following,
        ]);
    }
}
