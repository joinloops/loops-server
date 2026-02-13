<?php

namespace App\Services;

class InstanceActorService
{
    public function getActor()
    {
        $domain = parse_url($this->permalink(), PHP_URL_HOST);
        $publicKey = app(SigningService::class)->getPublicKey();

        return [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id' => $this->permalink(),
            'type' => 'Application',
            'inbox' => $this->permalink('/inbox'),
            'outbox' => $this->permalink('/outbox'),
            'endpoints' => [
                'sharedInbox' => url('/ap/inbox'),
            ],
            'preferredUsername' => $domain,
            'name' => config('app.name').' (Instance Actor)',
            'summary' => 'Instance actor for '.config('app.url'),
            'publicKey' => [
                'id' => $this->getKeyId(),
                'owner' => $this->permalink(),
                'publicKeyPem' => $publicKey,
            ],
            'manuallyApprovesFollowers' => false,
            'url' => url('/about?instance_actor=true'),
        ];
    }

    public function permalink($suffix = '')
    {
        return url('/ap/actor').$suffix;
    }

    public function getActorId()
    {
        return $this->permalink();
    }

    public function getKeyId()
    {
        return $this->permalink('#main-key');
    }

    public function getActorOutbox()
    {
        return [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id' => $this->permalink('/outbox'),
            'type' => 'OrderedCollection',
            'totalItems' => 0,
            'first' => $this->permalink('/outbox?page=true'),
            'last' => $this->permalink('/outbox?min_id=0&page=true'),
        ];
    }

    /**
     * Get the inbox URL for the instance actor
     */
    public function getInboxUrl()
    {
        return $this->permalink('/inbox');
    }

    /**
     * Get the shared inbox URL
     */
    public function getSharedInboxUrl()
    {
        return url('/ap/sharedInbox');
    }
}
