<?php

namespace App\Services;

use InvalidArgumentException;

class HashidService
{
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_';

    private const BASE = 64;

    private const MAX_ENCODED_LENGTH = 10;

    private static ?array $charMap = null;

    private static ?array $allowedChars = null;

    /**
     * Initialize character mappings
     */
    private static function init(): void
    {
        if (self::$charMap !== null) {
            return;
        }

        self::$charMap = array_flip(str_split(self::ALPHABET));
        self::$allowedChars = array_fill_keys(str_split(self::ALPHABET), true);
    }

    /**
     * Encode a snowflake ID to a hash string
     */
    public static function encode(int|string $snowflakeId): string
    {
        self::init();

        if (! ctype_digit($snowflakeId)) {
            throw new InvalidArgumentException('Input must be a numeric string');
        }

        $num = (int) $snowflakeId;

        if ($num === 0) {
            return self::ALPHABET[0];
        }

        $encoded = '';

        do {
            $encoded = self::ALPHABET[$num % self::BASE].$encoded;
            $num = intdiv($num, self::BASE);
        } while ($num > 0);

        return $encoded;
    }

    /**
     * Decode a hash string back to snowflake ID
     */
    public static function decode(string $encoded): string
    {
        self::init();

        if ($encoded === '') {
            throw new InvalidArgumentException('Encoded string cannot be empty');
        }

        if (strlen($encoded) > self::MAX_ENCODED_LENGTH) {
            throw new InvalidArgumentException('Encoded string too long');
        }

        if (! self::isValidEncoded($encoded)) {
            throw new InvalidArgumentException('Invalid characters in encoded string');
        }

        $decoded = 0;
        $length = strlen($encoded);

        for ($i = 0; $i < $length; $i++) {
            $decoded = $decoded * self::BASE + self::$charMap[$encoded[$i]];
        }

        return (string) $decoded;
    }

    /**
     * Validate if a string contains only allowed characters
     */
    private static function isValidEncoded(string $input): bool
    {
        $length = strlen($input);

        for ($i = 0; $i < $length; $i++) {
            if (! isset(self::$allowedChars[$input[$i]])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Public validation method with length constraints
     */
    public static function validateString(?string $input, int $minLength = 1, int $maxLength = self::MAX_ENCODED_LENGTH): bool
    {
        if ($input === null) {
            return false;
        }

        $length = strlen($input);

        if ($length < $minLength || $length > $maxLength) {
            return false;
        }

        self::init();

        return self::isValidEncoded($input);
    }

    /**
     * Safely encode snowflake ID
     */
    public static function safeEncode(int|string $snowflakeId): string|int|null
    {
        try {
            return self::encode($snowflakeId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Safely decode snowflake ID
     */
    public static function safeDecode(int|string $encoded): string|int|null
    {
        try {
            return self::decode($encoded);
        } catch (\Exception $e) {
            return null;
        }
    }
}
