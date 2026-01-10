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

        foreach ($matches[2] as $tag) {
            $hashtag = Hashtag::firstOrCreate([
                'name' => $tag,
            ]);
            if ($hashtag->can_autolink) {
                $hashtags[$hashtag->id] = ['visibility' => $this->visibility];
            }
        }

        $this->hashtags()->sync($hashtags);
    }
}
