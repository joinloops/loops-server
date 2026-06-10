<?php

namespace App\Concerns;

use App\Models\Hashtag;

trait HasSyncHashtagsFromCaption
{
    public function syncHashtagsFromCaption()
    {
        if (! $this->caption) {
            return;
        }

        preg_match_all('/(^|[\s\p{P}])#([\p{L}\p{N}_]{1,100})/u', $this->caption, $matches);
        $hashtags = [];
        $normalizedTags = [];

        foreach ($matches[2] as $tag) {
            $normalized = strtolower($tag);
            $normalizedTags[] = $normalized;

            $hashtag = Hashtag::firstOrCreate(
                ['name_normalized' => $normalized],
                ['name' => $tag, 'can_autolink' => true]
            );

            if ($hashtag->can_autolink) {
                $hashtags[$hashtag->id] = ['visibility' => $this->visibility ?? 1];
            }
        }

        $this->hashtags()->sync($hashtags);
        $this->afterSyncHashtagsFromCaption(array_unique($normalizedTags));
    }

    protected function afterSyncHashtagsFromCaption(array $normalizedTags): void
    {
        // No-op by default. Override on models that need to react to parsed tags.
    }
}
