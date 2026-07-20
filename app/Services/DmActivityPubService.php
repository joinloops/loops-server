<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Profile;

class DmActivityPubService
{
    public function objectUri(Message $message): string
    {
        return url("/ap/dm/{$message->id}");
    }

    public function actorUri(Profile $profile): string
    {
        return $profile->getActorId();
    }

    public function inboxUrl(Profile $profile): string
    {
        return $profile->inbox_url;
    }

    public function buildNote(Message $message): array
    {
        $conversation = $message->conversation;
        $sender = $message->sender;
        $recipient = $conversation->otherParticipant($sender->id)?->profile;

        $senderUri = $this->actorUri($sender);
        $recipientUri = $this->actorUri($recipient);

        $note = [
            'id' => $message->ap_object_uri,
            'type' => 'Note',
            'attributedTo' => $senderUri,
            'to' => [$recipientUri],
            'cc' => [],
            'published' => $message->created_at->toAtomString(),
            'context' => $conversation->context_uri,
            'conversation' => $conversation->context_uri,
            'content' => $this->renderContent($message),
            'tag' => [
                [
                    'type' => 'Mention',
                    'href' => $recipientUri,
                    'name' => '@'.$this->webfinger($recipient),
                ],
            ],
        ];

        $attachments = $message->media->map->toApAttachment()->values()->all();
        if ($attachments) {
            $note['attachment'] = $attachments;
        }

        return $note;
    }

    public function buildCreate(Message $message): array
    {
        $conversation = $message->conversation;
        $sender = $message->sender;
        $recipient = $conversation->otherParticipant($sender->id)?->profile;

        $senderUri = $this->actorUri($sender);
        $recipientUri = $this->actorUri($recipient);

        $note = $this->buildNote($message);

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $message->ap_object_uri.'#activity',
            'type' => 'Create',
            'actor' => $senderUri,
            'to' => [$recipientUri],
            'cc' => [],
            'published' => $note['published'],
            'object' => $note,
        ];
    }

    public function buildDelete(Message $message): array
    {
        $conversation = $message->conversation;
        $sender = $message->sender;
        $recipient = $conversation->otherParticipant($sender->id)?->profile;

        $senderUri = $this->actorUri($sender);
        $recipientUri = $this->actorUri($recipient);

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $message->ap_object_uri.'#delete',
            'type' => 'Delete',
            'actor' => $senderUri,
            'to' => [$recipientUri],
            'object' => [
                'id' => $message->ap_object_uri,
                'type' => 'Tombstone',
            ],
        ];
    }

    protected function renderContent(Message $message): string
    {
        $parts = [];

        if (filled($message->body)) {
            $parts[] = e($message->body);
        }

        if ($message->type === Message::TYPE_LOOP_SHARE && $message->video) {
            $url = $message->video->shareUrl();
            $parts[] = '<a href="'.$url.'" rel="noopener noreferrer">'.$url.'</a>';
        }

        return '<p>'.implode('<br />', $parts).'</p>';
    }

    protected function webfinger(Profile $profile): string
    {
        $domain = $profile->domain ?? parse_url(config('app.url'), PHP_URL_HOST);

        return $profile->username.'@'.$domain;
    }
}
