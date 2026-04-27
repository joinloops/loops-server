<?php

namespace App\Services;

use App\Models\BlockedTerm;
use Illuminate\Support\Str;

class ProfanityFilterService
{
    protected array $blocked;

    protected array $whitelist;

    public function __construct()
    {
        $lists = BlockedTerm::lists();

        $this->blocked = $lists[BlockedTerm::TYPE_BLOCK] ?? [];
        $this->whitelist = $lists[BlockedTerm::TYPE_ALLOW] ?? [];
    }

    public function isClean(string $input): bool
    {
        return ! $this->contains($input);
    }

    public function contains(string $input): bool
    {
        if (trim($input) === '' || empty($this->blocked)) {
            return false;
        }

        $normalized = $this->normalize($input);

        foreach ($this->whitelist as $safe) {
            if ($safe === '') {
                continue;
            }
            $normalized = str_ireplace($safe, str_repeat('_', mb_strlen($safe)), $normalized);
        }

        $compact = preg_replace('/[^a-z]/', '', $normalized);

        foreach ($this->blocked as $word) {
            if ($word === '') {
                continue;
            }

            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $normalized) === 1) {
                return true;
            }

            if (str_contains($compact, $word)) {
                return true;
            }
        }

        return false;
    }

    public function inspect(string $input): array
    {
        $normalized = $this->normalize($input);

        foreach ($this->whitelist as $safe) {
            if ($safe === '') {
                continue;
            }
            $normalized = str_ireplace($safe, str_repeat('_', mb_strlen($safe)), $normalized);
        }

        $compact = preg_replace('/[^a-z]/', '', $normalized);

        foreach ($this->blocked as $word) {
            if ($word === '') {
                continue;
            }

            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $normalized) === 1) {
                return [
                    'blocked' => true,
                    'matched_term' => $word,
                    'match_type' => 'word_boundary',
                    'normalized' => $normalized,
                ];
            }

            if (str_contains($compact, $word)) {
                return [
                    'blocked' => true,
                    'matched_term' => $word,
                    'match_type' => 'compact',
                    'normalized' => $normalized,
                ];
            }
        }

        return [
            'blocked' => false,
            'matched_term' => null,
            'match_type' => null,
            'normalized' => $normalized,
        ];
    }

    protected function normalize(string $input): string
    {
        $input = mb_strtolower($input);
        $input = Str::ascii($input);

        return strtr($input, [
            '@' => 'a', '4' => 'a',
            '3' => 'e',
            '1' => 'i', '!' => 'i', '|' => 'i',
            '0' => 'o',
            '$' => 's', '5' => 's',
            '7' => 't', '+' => 't',
        ]);
    }
}
