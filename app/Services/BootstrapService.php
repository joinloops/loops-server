<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class BootstrapService
{
    public static function ensureBoottimeEnvironment(): void
    {
        self::checkOAuthKeyPermissions();
        self::ensureAvatarTempDirectory();
    }

    protected static function ensureAvatarTempDirectory(): void
    {
        $path = storage_path('app/avatar-temp');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);

            return;
        }

        $perms = fileperms($path) & 0777;

        if ($perms === 0755) {
            return;
        }

        if (@chmod($path, 0755)) {
            return;
        }

        throw new RuntimeException(
            "Avatar temp directory \"{$path}\" has incorrect permissions (".self::formatPerms($perms).'). '.
            "Expected 0755. Please run: chmod 755 {$path}"
        );
    }

    protected static function checkOAuthKeyPermissions(): void
    {
        $privateKeyPath = storage_path('oauth-private.key');
        $publicKeyPath = storage_path('oauth-public.key');

        self::checkOAuthFile($privateKeyPath);
        self::checkOAuthFile($publicKeyPath);
    }

    protected static function checkOAuthFile(string $filePath): void
    {
        if (app()->environment('production') && ! file_exists($filePath)) {
            throw new RuntimeException(
                "OAuth key file {$filePath} is missing. Please generate OAuth keys."
            );
        }

        $permissions = self::getPermissions($filePath);

        $isSafe = ($permissions === '600' || $permissions === '660');

        if ($isSafe) {
            return;
        }

        $fixed = @chmod($filePath, 0660);

        if ($fixed) {
            return;
        }

        throw new RuntimeException(
            "File {$filePath} has bad permissions ({$permissions}). "."Should be 600 or 660. Run this command: chmod 660 {$filePath}"
        );
    }

    protected static function getPermissions(string $filePath): string
    {
        $permissionNumber = fileperms($filePath) & 0777;

        return decoct($permissionNumber);
    }

    protected static function formatPerms(int $perms): string
    {
        return sprintf('%04o', $perms);
    }
}
