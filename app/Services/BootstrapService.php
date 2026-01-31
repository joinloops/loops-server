<?php

namespace App\Services;

use RuntimeException;

class BootstrapService
{
    /**
     * Run all boot-time environment checks.
     */
    public static function ensureBoottimeEnvironment(): void
    {
        self::ensureOAuthKeyPermissions();
    }

    protected static function ensureOAuthKeyPermissions(): void
    {
        $keys = [
            storage_path('oauth-private.key'),
            storage_path('oauth-public.key'),
        ];

        foreach ($keys as $keyPath) {
            if (! file_exists($keyPath)) {
                continue;
            }

            $perms = fileperms($keyPath) & 0777;

            if ($perms <= 0660) {
                continue;
            }

            if (@chmod($keyPath, 0660)) {
                continue;
            }

            throw new RuntimeException(
                "OAuth key file \"{$keyPath}\" has insecure permissions (" . self::formatPerms($perms) . "). " .
                "Expected 600 or 660. Please run: chmod 660 {$keyPath}"
            );
        }
    }

    protected static function formatPerms(int $perms): string
    {
        return sprintf('%04o', $perms);
    }
}
