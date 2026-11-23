<?php

namespace App\Support;

class IpRange
{
    public const EXACT = 'exact';

    public const CIDR = 'cidr';

    public const RANGE = 'range';

    protected const V4_PREFIX = '00000000000000000000ffff';

    public static function toHex(?string $ip): ?string
    {
        if (! is_string($ip)) {
            return null;
        }

        $ip = trim($ip);

        if ($ip === '' || strlen($ip) > 64) {
            return null;
        }

        if (str_contains($ip, '%')) {
            $ip = (string) strstr($ip, '%', true);
        }

        $packed = @inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        if (strlen($packed) === 4) {
            $packed = str_repeat("\0", 10)."\xff\xff".$packed;
        }

        if (strlen($packed) !== 16) {
            return null;
        }

        return bin2hex($packed);
    }

    public static function isV4Hex(string $hex): bool
    {
        return str_starts_with($hex, self::V4_PREFIX);
    }

    public static function overlapsV4(string $start, string $end): bool
    {
        return strcmp($start, self::V4_PREFIX.'ffffffff') <= 0 && strcmp($end, self::V4_PREFIX.'00000000') >= 0;
    }

    public static function fromHex(string $hex): string
    {
        $bin = (string) hex2bin($hex);

        if (self::isV4Hex($hex)) {
            return (string) inet_ntop(substr($bin, 12));
        }

        return (string) inet_ntop($bin);
    }

    public static function canonical(?string $ip): ?string
    {
        $hex = self::toHex($ip);

        return $hex ? self::fromHex($hex) : null;
    }

    public static function contains(string $start, string $end, ?string $ip): bool
    {
        $hex = self::toHex($ip);

        if (! $hex) {
            return false;
        }

        return strcmp($start, $hex) <= 0 && strcmp($hex, $end) <= 0;
    }

    public static function parse(?string $input): ?array
    {
        if (! is_string($input)) {
            return null;
        }

        $input = trim($input);

        if ($input === '' || strlen($input) > 100) {
            return null;
        }

        if (str_contains($input, '/')) {
            return self::parseCidr($input);
        }

        if (preg_match('/^([0-9a-fA-F:.]+)\s*-\s*([0-9a-fA-F:.]+)$/', $input, $m)) {
            return self::parseRange($m[1], $m[2]);
        }

        $hex = self::toHex($input);

        if (! $hex) {
            return null;
        }

        return self::result(self::EXACT, self::fromHex($hex), $hex, $hex);
    }

    protected static function parseCidr(string $input): ?array
    {
        [$ip, $bits] = array_pad(explode('/', $input, 2), 2, '');
        $ip = trim($ip);
        $bits = trim($bits);

        if ($bits === '' || ! ctype_digit($bits)) {
            return null;
        }

        $hex = self::toHex($ip);

        if (! $hex) {
            return null;
        }

        $v4Notation = ! str_contains($ip, ':');
        $bits = (int) $bits;
        $max = $v4Notation ? 32 : 128;

        if ($bits > $max) {
            return null;
        }

        [$start, $end] = self::maskRange($hex, $v4Notation ? $bits + 96 : $bits);

        if ($start === $end) {
            return self::result(self::EXACT, self::fromHex($start), $start, $end);
        }

        if ($v4Notation !== self::isV4Hex($start) || (! $v4Notation && self::overlapsV4($start, $end))) {
            return null;
        }

        return self::result(self::CIDR, self::fromHex($start).'/'.$bits, $start, $end, $max - $bits);
    }

    protected static function parseRange(string $a, string $b): ?array
    {
        $start = self::toHex($a);
        $end = self::toHex($b);

        if (! $start || ! $end || self::isV4Hex($start) !== self::isV4Hex($end)) {
            return null;
        }

        if (strcmp($start, $end) > 0) {
            [$start, $end] = [$end, $start];
        }

        if ($start === $end) {
            return self::result(self::EXACT, self::fromHex($start), $start, $end);
        }

        if (! self::isV4Hex($start) && self::overlapsV4($start, $end)) {
            return null;
        }

        return self::result(self::RANGE, self::fromHex($start).'-'.self::fromHex($end), $start, $end);
    }

    protected static function maskRange(string $hex, int $prefix): array
    {
        $bin = (string) hex2bin($hex);
        $start = '';
        $end = '';

        for ($i = 0; $i < 16; $i++) {
            $bitsInByte = max(0, min(8, $prefix - ($i * 8)));
            $mask = $bitsInByte === 0 ? 0 : (0xFF << (8 - $bitsInByte)) & 0xFF;
            $byte = ord($bin[$i]);
            $start .= chr($byte & $mask);
            $end .= chr(($byte & $mask) | (~$mask & 0xFF));
        }

        return [bin2hex($start), bin2hex($end)];
    }

    public static function hostBits(string $start, string $end): float
    {
        $i = 0;

        while ($i < 32 && $start[$i] === $end[$i]) {
            $i++;
        }

        if ($i === 32) {
            return 0.0;
        }

        $tail = 32 - $i;

        if ($tail <= 13) {
            $count = hexdec(substr($end, $i)) - hexdec(substr($start, $i)) + 1;

            return log((float) $count, 2);
        }

        return (float) ($tail * 4);
    }

    public static function sizeLabel(string $start, string $end): string
    {
        $bits = self::hostBits($start, $end);

        if ($bits <= 48) {
            $i = 0;

            while ($i < 32 && $start[$i] === $end[$i]) {
                $i++;
            }

            if ($i === 32) {
                return '1';
            }

            return number_format(hexdec(substr($end, $i)) - hexdec(substr($start, $i)) + 1);
        }

        return '2^'.(int) round($bits);
    }

    protected static function result(string $match, string $value, string $start, string $end, ?int $hostBits = null): array
    {
        return [
            'match_type' => $match,
            'value' => $value,
            'start' => $start,
            'end' => $end,
            'version' => self::isV4Hex($start) ? 4 : 6,
            'first' => self::fromHex($start),
            'last' => self::fromHex($end),
            'host_bits' => $hostBits !== null ? (float) $hostBits : self::hostBits($start, $end),
            'size' => self::sizeLabel($start, $end),
        ];
    }

    public static function v4LikePrefix(string $start, string $end): ?string
    {
        if (! self::isV4Hex($start) || ! self::isV4Hex($end)) {
            return null;
        }

        $a = explode('.', self::fromHex($start));
        $b = explode('.', self::fromHex($end));
        $common = [];

        for ($i = 0; $i < 4; $i++) {
            if ($a[$i] !== $b[$i]) {
                break;
            }

            $common[] = $a[$i];
        }

        if (count($common) === 4) {
            return implode('.', $common);
        }

        return count($common) ? implode('.', $common).'.%' : '%.%.%.%';
    }
}
