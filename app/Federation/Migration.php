<?php

namespace App\Federation;

use App\Services\SanitizeService;

class Migration
{
    public const NS = 'https://joinloops.org/ns#';

    public const VERSION = 1;

    public const OBJECT_TYPE = 'AccountMigration';

    public const INTENT_TYPE = 'MigrationIntent';

    public const RECEIPT_TYPE = 'MigrationReceipt';

    public const SEAL_TYPE = 'TransitSeal';

    public const JWS_TYPE = 'loops-migration+jws';

    public const MAX_ALIASES = 5;

    public static function actorContext(): array
    {
        return [
            'alsoKnownAs' => [
                '@id' => 'as:alsoKnownAs',
                '@type' => '@id',
            ],
            'movedTo' => [
                '@id' => 'as:movedTo',
                '@type' => '@id',
            ],
        ];
    }

    public static function contextTerms(): array
    {
        return array_merge(self::actorContext(), [
            'loops' => self::NS,
            self::OBJECT_TYPE => 'loops:'.self::OBJECT_TYPE,
            self::RECEIPT_TYPE => 'loops:'.self::RECEIPT_TYPE,
            'intent' => 'loops:intent',
            'receipt' => 'loops:receipt',
            'seal' => 'loops:seal',
            'manifest' => [
                '@id' => 'loops:manifest',
                '@type' => '@id',
            ],
            'transit' => [
                '@id' => 'loops:transit',
                '@type' => '@id',
            ],
            'attestation' => [
                '@id' => 'loops:attestation',
                '@type' => '@id',
            ],
        ]);
    }

    public static function context(): array
    {
        return [
            'https://www.w3.org/ns/activitystreams',
            self::contextTerms(),
        ];
    }

    public static function sanitizeAliases($value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        $sanitize = app(SanitizeService::class);
        $aliases = [];

        foreach ($value as $entry) {
            if (is_array($entry)) {
                $entry = $entry['id'] ?? null;
            }

            if (! is_string($entry) || strlen($entry) > 2048) {
                continue;
            }

            $url = $sanitize->url($entry, false);

            if ($url && ! in_array($entry, $aliases, true)) {
                $aliases[] = $entry;
            }

            if (count($aliases) >= 10) {
                break;
            }
        }

        return $aliases;
    }

    public static function urlHash(string $url): string
    {
        return hash('sha256', $url);
    }

    public static function isMigrationResponse(array $activity): bool
    {
        if (! in_array($activity['type'] ?? null, ['Accept', 'Reject'], true)) {
            return false;
        }

        $object = $activity['object'] ?? null;

        return is_array($object) && ($object['type'] ?? null) === 'Offer';
    }

    public static function host(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host ? strtolower($host) : null;
    }
}
