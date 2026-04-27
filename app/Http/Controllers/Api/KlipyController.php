<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProfanityFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Klipy\Exceptions\KlipyApiException;
use Klipy\Laravel\Facades\Klipy;

class KlipyController extends Controller
{
    protected const TYPES = ['gifs', 'stickers', 'memes', 'clips'];

    public function trending(Request $request, string $type): JsonResponse
    {
        abort_unless(! empty(config('klipy.api_key')), 400, 'Klipy API key missing');
        $user = $request->user();
        abort_unless((bool) $user->can_comment, 400, 'Cannot access this resource');
        $type = $this->resolveType($type);

        $key = "loops:klipy:trending:{$type}:locale-en";

        $payload = Cache::remember($key, now()->addHours(2), function () use ($type) {
            $response = Klipy::$type()->trending(
                perPage: 50,
                page: 1,
                locale: 'en',
                customerId: 1,
            );

            return $this->normalize($response->raw);
        });

        return response()->json($payload);
    }

    public function search(Request $request, string $type, ProfanityFilterService $filter): JsonResponse
    {
        abort_unless(! empty(config('klipy.api_key')), 400, 'Klipy API key missing');
        $user = $request->user();
        abort_unless((bool) $user->can_comment, 400, 'Cannot access this resource');
        $type = $this->resolveType($type);

        $query = trim((string) $request->input('q', ''));
        if ($query === '') {
            return response()->json($this->emptyPayload());
        }

        if ($filter->contains($query)) {
            return response()->json($this->emptyPayload());
        }

        $userId = $request->user()->id;
        $queryHash = md5($query);

        $key = "loops:klipy:search:{$type}:{$userId}:{$queryHash}";

        try {
            $payload = Cache::remember($key, now()->addHours(12), function () use ($type, $userId, $query) {
                $response = Klipy::$type()->search(
                    query: $query,
                    perPage: 50,
                    page: 1,
                    locale: 'en',
                    customerId: $userId,
                );

                return $this->normalize($response->raw);
            });
        } catch (KlipyApiException $e) {
            return response()->json([
                'message' => 'Klipy search failed.',
                'status' => $e->getStatusCode(),
            ], 502);
        }

        return response()->json($payload);
    }

    protected function resolveType(string $type): string
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        return $type;
    }

    protected function normalize(array $raw): array
    {
        $data = $raw['data'] ?? [];
        $items = collect($data['data'] ?? [])
            ->map(fn ($item) => $this->mapItem($item))
            ->filter(fn ($i) => $i['slug'] !== null && $i['preview'] !== null)
            ->values()
            ->all();

        return [
            'items' => $items,
            'page' => (int) ($data['current_page'] ?? 1),
            'per_page' => (int) ($data['per_page'] ?? count($items)),
            'has_next' => (bool) ($data['has_next'] ?? false),
            'meta' => $data['meta'] ?? null,
        ];
    }

    protected function mapItem(array $item): array
    {
        if (($item['type'] ?? null) === 'clip') {
            return $this->mapClipItem($item);
        }

        $preview = $this->pickFormat($item, ['sm', 'md', 'xs', 'hd'], ['webp', 'gif']);
        $full = $this->pickFormat($item, ['md', 'hd', 'sm'], ['gif', 'webp']);
        $mp4 = $this->pickFormat($item, ['md', 'hd', 'sm'], ['mp4']);
        $webm = $this->pickFormat($item, ['md', 'hd', 'sm'], ['webm']);

        $w = $full['width'] ?? $preview['width'] ?? 0;
        $h = $full['height'] ?? $preview['height'] ?? 0;

        return [
            'id' => $item['id'] ?? null,
            'slug' => $item['slug'] ?? null,
            'title' => $item['title'] ?? '',
            'type' => $item['type'] ?? 'gif',
            'preview' => $preview,
            'full' => $full,
            'mp4' => $mp4,
            'webm' => $webm,
            'blur_preview' => $item['blur_preview'] ?? null,
            'width' => (int) $w,
            'height' => (int) $h,
            'is_ad' => (bool) ($item['is_ad'] ?? false),
        ];
    }

    protected function mapClipItem(array $item): array
    {
        $preview = $this->pickClipFormat($item, ['webp', 'gif']);
        $full = $this->pickClipFormat($item, ['gif', 'webp']);
        $mp4 = $this->pickClipFormat($item, ['mp4']);
        $webm = $this->pickClipFormat($item, ['webm']);

        $w = $mp4['width'] ?? $full['width'] ?? $preview['width'] ?? 0;
        $h = $mp4['height'] ?? $full['height'] ?? $preview['height'] ?? 0;

        return [
            'id' => $item['id'] ?? $item['slug'] ?? null,
            'slug' => $item['slug'] ?? null,
            'title' => $item['title'] ?? '',
            'type' => $item['type'] ?? 'clip',
            'preview' => $preview,
            'full' => $full,
            'mp4' => $mp4,
            'webm' => $webm,
            'blur_preview' => $item['blur_preview'] ?? null,
            'width' => (int) $w,
            'height' => (int) $h,
            'is_ad' => (bool) ($item['is_ad'] ?? false),
        ];
    }

    protected function pickClipFormat(array $item, array $formats): ?array
    {
        $file = $item['file'] ?? [];
        $meta = $item['file_meta'] ?? [];

        foreach ($formats as $format) {
            $url = $file[$format] ?? null;
            if (! is_string($url) || $url === '') {
                continue;
            }

            $dims = is_array($meta[$format] ?? null) ? $meta[$format] : [];

            return [
                'url' => $url,
                'width' => (int) ($dims['width'] ?? 0),
                'height' => (int) ($dims['height'] ?? 0),
                'size' => 0,
            ];
        }

        return null;
    }

    protected function pickFormat(array $item, array $sizes, array $formats): ?array
    {
        $file = $item['file'] ?? [];

        foreach ($sizes as $size) {
            $bucket = $file[$size] ?? null;
            if (! is_array($bucket)) {
                continue;
            }

            foreach ($formats as $format) {
                $entry = $bucket[$format] ?? null;
                if (is_array($entry) && ! empty($entry['url'])) {
                    return [
                        'url' => $entry['url'],
                        'width' => (int) ($entry['width'] ?? 0),
                        'height' => (int) ($entry['height'] ?? 0),
                        'size' => (int) ($entry['size'] ?? 0),
                    ];
                }
            }
        }

        return null;
    }

    protected function emptyPayload(): array
    {
        return ['items' => [], 'page' => 1, 'per_page' => 0, 'has_next' => false];
    }
}
