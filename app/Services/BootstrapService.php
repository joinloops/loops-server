<?php
namespace App\Services;

use RuntimeException;

class BootstrapService
{
    public static function ensureBoottimeEnvironment(): void
    {
        self::checkOAuthKeyPermissions();
    }

    protected static function checkOAuthKeyPermissions(): void
    {
        $privateKeyPath = storage_path('oauth-private.key');
        $publicKeyPath = storage_path('oauth-public.key');
        
        self::checkOneFile($privateKeyPath);
        self::checkOneFile($publicKeyPath);
    }

    protected static function checkOneFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException(
                "OAuth key file {$filePath} is missing. Please generate OAuth keys."
            );
        }

        $permissions = self::getPermissions($filePath);
        
        $isSafe = ($permissions === '600' || $permissions === '660');
        
        if ($isSafe) {
            return;
        }

        $fixed = @chmod($filePath, 0660);  // Try to fix it by setting to 660
        
        if ($fixed) {
            return;
        }

        throw new RuntimeException(
            "File {$filePath} has bad permissions ({$permissions}). " . "Should be 600 or 660. Run this command: chmod 660 {$filePath}"
        );
    }

    protected static function getPermissions(string $filePath): string
    {
        $permissionNumber = fileperms($filePath) & 0777;// fileperms() returns a number with extra info we don't need & 0777 removes that extra info, leaving just the permissions
        return decoct($permissionNumber);  // Convert to a readable string like "644" or "600" (decoct converts decimal to octal)
    }
}
