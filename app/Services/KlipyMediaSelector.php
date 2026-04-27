<?php

namespace App\Services;

use RuntimeException;

class KlipyMediaSelector
{
    public function pick(array $item, string $type): array
    {
        $chain = $this->chainFor($type, $item);

        foreach ($chain as $key) {
            $asset = data_get($item, $key);
            if (! $asset || empty($asset['url'])) {
                continue;
            }

            return [
                'url' => $asset['url'],
                'mime_type' => $this->mimeFromUrl($asset['url']),
                'width' => $asset['width'] ?? data_get($item, 'width'),
                'height' => $asset['height'] ?? data_get($item, 'height'),
                'size' => $asset['size'] ?? null,
                'source_key' => $key,
            ];
        }

        throw new RuntimeException('No usable Klipy format for type: '.$type);
    }

    private function chainFor(string $type, array $item): array
    {
        return match ($type) {
            'gifs' => ['webm', 'mp4', 'full'],
            'stickers' => $this->isAnimatedSticker($item)
                            ? ['webm', 'mp4', 'preview', 'full']
                            : ['preview', 'full'],
            'memes' => ['preview', 'full'],
            'clips' => ['mp4', 'webm', 'full', 'preview'],
            default => throw new RuntimeException("Unknown Klipy type: {$type}"),
        };
    }

    private function isAnimatedSticker(array $item): bool
    {
        return ! empty(data_get($item, 'webm.url')) || ! empty(data_get($item, 'mp4.url'));
    }

    private function mimeFromUrl(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return match ($ext) {
            'webm' => 'video/webm',
            'mp4' => 'video/mp4',
            'webp' => 'image/webp',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }
}
