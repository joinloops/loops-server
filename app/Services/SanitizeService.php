<?php

namespace App\Services;

use App\Models\Instance;
use App\Models\Profile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Stevebauman\Purify\Facades\Purify;

class SanitizeService
{
    const BANNED_DOMAINS_KEY = 'loops:sanserv:banned:domains';

    const BANNED_DOMAINS_SET_KEY = 'loops:sanserv:banned:domains:set';

    /**
     * Get all banned domains as an array
     *
     * @param  bool  $flush
     * @return array
     */
    public function getBannedDomains($flush = false)
    {
        if ($flush) {
            $this->flushBannedDomainsCache();
        }

        if ($this->isBannedDomainsSetPopulated()) {
            return Redis::smembers(self::BANNED_DOMAINS_SET_KEY);
        }

        return $this->populateBannedDomainsSet();
    }

    /**
     * Check if a specific domain is banned
     */
    public function isDomainBanned(string $domain): bool
    {
        if (! $this->isBannedDomainsSetPopulated()) {
            $this->populateBannedDomainsSet();
        }

        return (bool) Redis::sismember(self::BANNED_DOMAINS_SET_KEY, strtolower($domain));
    }

    /**
     * Add a domain to the banned list
     *
     * @return bool True if domain was added, false if already existed
     */
    public function addBannedDomain(string $domain): bool
    {
        // Ensure the Redis Set is populated
        if (! $this->isBannedDomainsSetPopulated()) {
            $this->populateBannedDomainsSet();
        }

        $added = Redis::sadd(self::BANNED_DOMAINS_SET_KEY, strtolower($domain));

        // Clear the legacy cache
        Cache::forget(self::BANNED_DOMAINS_KEY);

        return $added > 0;
    }

    /**
     * Remove a domain from the banned list
     *
     * @return bool True if domain was removed, false if didn't exist
     */
    public function removeBannedDomain(string $domain): bool
    {
        // Ensure the Redis Set is populated
        if (! $this->isBannedDomainsSetPopulated()) {
            $this->populateBannedDomainsSet();
        }

        $removed = Redis::srem(self::BANNED_DOMAINS_SET_KEY, strtolower($domain));

        // Clear the legacy cache
        Cache::forget(self::BANNED_DOMAINS_KEY);

        return $removed > 0;
    }

    /**
     * Add multiple domains to the banned list
     *
     * @return int Number of domains actually added (excluding duplicates)
     */
    public function addBannedDomains(array $domains): int
    {
        if (empty($domains)) {
            return 0;
        }

        // Ensure the Redis Set is populated
        if (! $this->isBannedDomainsSetPopulated()) {
            $this->populateBannedDomainsSet();
        }

        $domains = array_map('strtolower', $domains);

        $added = Redis::sadd(self::BANNED_DOMAINS_SET_KEY, ...$domains);

        // Clear the legacy cache
        Cache::forget(self::BANNED_DOMAINS_KEY);

        return $added;
    }

    /**
     * Remove multiple domains from the banned list
     *
     * @return int Number of domains actually removed
     */
    public function removeBannedDomains(array $domains): int
    {
        if (empty($domains)) {
            return 0;
        }

        // Ensure the Redis Set is populated
        if (! $this->isBannedDomainsSetPopulated()) {
            $this->populateBannedDomainsSet();
        }

        $domains = array_map('strtolower', $domains);

        $removed = Redis::srem(self::BANNED_DOMAINS_SET_KEY, ...$domains);

        // Clear the legacy cache
        Cache::forget(self::BANNED_DOMAINS_KEY);

        return $removed;
    }

    /**
     * Get the count of banned domains
     */
    public function getBannedDomainsCount(): int
    {
        // Ensure the Redis Set is populated
        if (! $this->isBannedDomainsSetPopulated()) {
            $this->populateBannedDomainsSet();
        }

        return Redis::scard(self::BANNED_DOMAINS_SET_KEY);
    }

    /**
     * Flush all banned domains cache
     */
    public function flushBannedDomainsCache(): void
    {
        Cache::forget(self::BANNED_DOMAINS_KEY);
        Redis::del(self::BANNED_DOMAINS_SET_KEY);
        usleep(1000);
    }

    /**
     * Sync the Redis Set with the database
     * This should be called when Instance models are updated
     */
    public function syncBannedDomainsFromDatabase(): array
    {
        $domains = Instance::whereIsBlocked(true)->pluck('domain')->toArray();

        // Clear existing Redis Set
        Redis::del(self::BANNED_DOMAINS_SET_KEY);

        // Populate with fresh data
        if (! empty($domains)) {
            $domains = array_map('strtolower', $domains);
            Redis::sadd(self::BANNED_DOMAINS_SET_KEY, ...$domains);
        }

        // Clear legacy cache
        Cache::forget(self::BANNED_DOMAINS_KEY);

        return $domains;
    }

    /**
     * Check if the Redis Set is populated
     */
    private function isBannedDomainsSetPopulated(): bool
    {
        return Redis::exists(self::BANNED_DOMAINS_SET_KEY);
    }

    /**
     * Populate the Redis Set from database
     */
    private function populateBannedDomainsSet(): array
    {
        $domains = Instance::whereIsBlocked(true)->pluck('domain')->toArray();

        if (! empty($domains)) {
            Redis::sadd(self::BANNED_DOMAINS_SET_KEY, ...$domains);
        }

        return $domains;
    }

    private function localDomain(): string
    {
        $app = parse_url(config('app.url'));
        $appHost = strtolower(data_get($app, 'host'));

        return $appHost;
    }

    public function isLocalObject($url): bool
    {
        if (empty($url)) {
            return false;
        }

        $parsed = parse_url($url);
        if (! $parsed || ! isset($parsed['scheme']) || $parsed['scheme'] !== 'https') {
            return false;
        }

        $app = parse_url(config('app.url'));
        $appHost = strtolower(data_get($app, 'host'));
        $urlHost = strtolower(data_get($parsed, 'host'));

        return $appHost === $urlHost;
    }

    /**
     * Validate and normalize a URL for safe outbound use.
     *
     * - Requires HTTPS (port 443 only)
     * - No credentials in authority
     * - Blocks localhost/private/reserved IPs (IPv4/IPv6)
     * - Optionally verifies DNS and blocks records resolving to private/reserved IPs
     * - Normalizes IDNs to ASCII (punycode) when possible
     * - Allows configured local domains (same-server instances)
     *
     * @param  string|array  $input
     * @return string|false Normalized safe URL or false
     */
    public function url($input, bool $verifyDns = false, bool $bypassAppHost = true)
    {
        if (is_array($input)) {
            $input = reset($input);
        }

        if (! is_string($input)) {
            return false;
        }

        $url = trim($input);
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        if (strncasecmp($url, 'https://', 8) !== 0) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || strcasecmp($parts['scheme'], 'https') !== 0) {
            return false;
        }
        if (empty($parts['host'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return false;
        }

        $host = strtolower($parts['host']);

        $isTrustedLocal = false;
        if ($bypassAppHost) {
            $trustedDomains = $this->getTrustedLocalDomains();
            foreach ($trustedDomains as $trustedDomain) {
                if ($host === $trustedDomain) {
                    $isTrustedLocal = true;
                    break;
                }
            }
        }

        if ($isTrustedLocal) {
            $path = $parts['path'] ?? '';
            if ($path !== '' && strpos($path, '\\') !== false) {
                return false;
            }

            $authority = (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) ? '['.$host.']' : $host;
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';
            $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
            $normalized = 'https://'.$authority.$path.$query.$fragment;

            if (! filter_var($normalized, FILTER_VALIDATE_URL)) {
                return false;
            }

            return $normalized;
        }

        $isIpv4 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isIpv6 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        if ($isIpv4 || $isIpv6) {
            if ($this->isPrivateOrReservedIp($host)) {
                return false;
            }
        } else {
            $asciiHost = $this->idnToAscii($host);
            if ($asciiHost === false || ! $this->isValidHostname($asciiHost)) {
                return false;
            }
            $host = $asciiHost;

            $banned = $this->isDomainBanned($host);
            if ($banned) {
                return false;
            }

            if ($verifyDns) {
                $ips = $this->resolveAllIps($host);
                if (empty($ips)) {
                    return false;
                }
                foreach ($ips as $ip) {
                    if ($this->isPrivateOrReservedIp($ip)) {
                        return false;
                    }
                }
            }
        }

        $path = $parts['path'] ?? '';
        if ($path !== '' && strpos($path, '\\') !== false) {
            return false;
        }

        $authority = (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) ? '['.$host.']' : $host;
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
        $normalized = 'https://'.$authority.$path.$query.$fragment;

        if (! filter_var($normalized, FILTER_VALIDATE_URL)) {
            return false;
        }

        return $normalized;
    }

    /**
     * Get list of trusted local domains (same-server instances)
     */
    private function getTrustedLocalDomains(): array
    {
        $domains = [$this->localDomain()];

        $configDomains = config('loops.local_domains');
        if ($configDomains) {
            $additional = array_map('trim', explode(',', $configDomains));
            $additional = array_filter($additional, fn ($d) => $d !== '');
            $domains = array_merge($domains, $additional);
        }

        return array_unique(array_map('strtolower', $domains));
    }

    private function idnToAscii(string $host)
    {
        if (preg_match('/[^\x20-\x7E]/', $host)) {
            if (function_exists('idn_to_ascii')) {
                $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
                $ascii = idn_to_ascii($host, 0, $variant);

                return $ascii ?: false;
            }

            return false;
        }

        return $host;
    }

    private function isValidHostname(string $host): bool
    {
        if ($host === '' || strlen($host) > 253 || strpos($host, '.') === false) {
            return false;
        }
        foreach (explode('.', $host) as $label) {
            if ($label === '' || strlen($label) > 63) {
                return false;
            }
            if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/i', $label)) {
                return false;
            }
        }

        return true;
    }

    private function resolveAllIps(string $host): array
    {
        $ips = [];

        $a = @dns_get_record($host, DNS_A);
        if (is_array($a)) {
            foreach ($a as $r) {
                if (! empty($r['ip'])) {
                    $ips[] = $r['ip'];
                }
            }
        }

        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $r) {
                if (! empty($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPrivateOrReservedIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public function purify($html)
    {
        $cleaned = Purify::clean($html);

        return $cleaned;
    }

    public function processCaption($html)
    {
        $cleanedCaption = $this->cleanHtmlWithSpacing($html);

        $mentions = $this->extractMentions($cleanedCaption);

        return [
            'caption' => $cleanedCaption,
            'mentions' => $mentions,
        ];
    }

    public function extractMentions($text)
    {
        if (! is_string($text) || empty($text)) {
            return [];
        }

        $mentions = [];
        $pattern = '/@([a-zA-Z0-9_.-]+)(?:@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,}))?(?=\s|$|[^\w@.-])/u';

        if (! preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $localDomain = $this->localDomain();
        $localUsernames = [];
        $remoteHandles = [];
        $mentionData = [];

        foreach ($matches[0] as $index => $match) {
            $fullMatch = $match[0];
            $username = $matches[1][$index][0];
            $domain = isset($matches[2][$index][0]) ? $matches[2][$index][0] : null;

            // Convert byte offset to JavaScript-compatible character offset
            $byteOffset = $match[1];
            $textBeforeMatch = substr($text, 0, $byteOffset);

            // Count characters the way JavaScript does (UTF-16 code units)
            // Convert to UTF-16LE and count bytes, then divide by 2
            $startIndex = strlen(mb_convert_encoding($textBeforeMatch, 'UTF-16LE', 'UTF-8')) / 2;
            $endIndex = $startIndex + strlen(mb_convert_encoding($fullMatch, 'UTF-16LE', 'UTF-8')) / 2;

            $mentionData[$index] = [
                'username' => $username,
                'domain' => $domain,
                'start_index' => (int) $startIndex,
                'end_index' => (int) $endIndex,
            ];

            if ($domain) {
                if (strtolower($domain) === $localDomain) {
                    $localUsernames[] = strtolower($username);
                } else {
                    $remoteHandles[] = strtolower($username.'@'.$domain);
                }
            } else {
                $localUsernames[] = strtolower($username);
            }
        }

        $localProfiles = [];
        if (! empty($localUsernames)) {
            $localProfiles = Profile::whereIn('username', array_unique($localUsernames))
                ->where('is_hidden', false)
                ->where('is_suspended', false)
                ->get()
                ->keyBy(function ($profile) {
                    return strtolower($profile->username);
                });
        }

        $remoteProfiles = [];
        if (! empty($remoteHandles)) {
            $remoteProfiles = Profile::whereIn('username', array_unique($remoteHandles))
                ->where('is_hidden', false)
                ->where('is_suspended', false)
                ->get()
                ->keyBy(function ($profile) {
                    return strtolower($profile->username);
                });
        }

        foreach ($mentionData as $index => $data) {
            $username = $data['username'];
            $domain = $data['domain'];
            $user = null;

            if ($domain) {
                $webfingerHandle = $username.'@'.$domain;

                if (strtolower($domain) === $localDomain) {
                    $user = $localProfiles[strtolower($username)] ?? null;
                } else {
                    $user = $remoteProfiles[strtolower($webfingerHandle)] ?? null;

                    if (! $user) {
                        $user = $this->resolveRemoteActor($webfingerHandle, $domain);
                    }
                }
            } else {
                $user = $localProfiles[strtolower($username)] ?? null;
            }

            if ($user) {
                $mentions[] = [
                    'uri' => $user->getActorId(),
                    'username' => $user->username,
                    'start_index' => $data['start_index'],
                    'end_index' => $data['end_index'],
                    'profile_id' => (string) $user->id,
                    'is_remote' => ! $user->local,
                    'is_local' => $user->local,
                ];
            }
        }

        return $mentions;
    }

    protected function resolveRemoteActor($webfingerHandle, $domain)
    {
        $blockCacheKey = 'sanitize:mention:blocked:'.sha1(strtolower($webfingerHandle));

        if (Cache::has($blockCacheKey)) {
            return;
        }

        $actorCacheKey = 'sanitize:mention:actor:'.sha1(strtolower($webfingerHandle));

        $cachedActorId = Cache::get($actorCacheKey);
        if ($cachedActorId) {
            return Profile::where('id', $cachedActorId)
                ->where('is_hidden', false)
                ->where('is_suspended', false)
                ->first();
        }

        $validDomain = $this->url('https://'.$domain, true);
        if (! $validDomain) {
            Cache::put($blockCacheKey, true, 3600);

            return;
        }

        try {
            $user = app(WebfingerService::class)->findOrCreateRemoteActor($webfingerHandle);

            if (! $user || $user->is_hidden || $user->is_suspended) {
                Cache::put($blockCacheKey, true, 3600);

                return;
            }

            Cache::put($actorCacheKey, $user->id, 21600);

            return $user;
        } catch (\Exception $e) {
            Cache::put($blockCacheKey, true, 1800);

            return;
        }
    }

    public function cleanPlainText($text)
    {
        if (empty($text)) {
            return;
        }

        $cleaned = strip_tags($text);
        $cleaned = str_replace(["\r\n", "\r"], "\n", $cleaned);
        $cleaned = preg_replace("/\n{3,}/", "\n\n", $cleaned);

        return trim($cleaned);
    }

    public function cleanPlainTextWithoutLineBreaks($text)
    {
        if (empty($text)) {
            return '';
        }

        $cleaned = strip_tags($text);
        $cleaned = str_replace(["\r\n", "\r", "\n"], ' ', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return trim($cleaned);
    }

    public function cleanHtmlWithSpacing($html)
    {
        $blockTags = ['a', 'b', 'blockquote', 'br', 'code', 'del', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'img', 'li', 'ol', 'p', 'pre', 's', 'strike', 'strong', 'u', 'ul'];

        foreach ($blockTags as $tag) {
            $html = preg_replace("/<\/{$tag}>/i", "</{$tag}> ", $html);
        }

        $html = preg_replace("/<br\s*\/?>/i", '<br /> ', $html);

        $cleaned = Purify::clean($html);

        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);

        return $cleaned;
    }

    /**
     * Match a URL against one or more path templates and allowed hosts.
     *
     * @param  string|array  $templates  e.g. '/ap/users/{profileId}/video/{videoId}' or array of templates
     * @param  array<string>  $allowedHosts  optional: array of allowed hosts
     * @param  bool  $allowSubdomains  if true, foo.bar.allowedHost is accepted
     * @param  array<string,string>  $constraints  optional: param => 'regex without delimiters' (e.g. ['videoId' => '\d+'])
     * @return array|null ['_template' => matchedTemplate, '_host' => host, ...params] or null if no match
     */
    public function matchUrlTemplate(
        string $url,
        string|array $templates,
        array $allowedHosts = [],
        bool $allowSubdomains = false,
        array $constraints = [],
        bool $useAppHost = false
    ): ?array {
        $parts = parse_url($url);
        if (! $parts || ! isset($parts['host']) || ! isset($parts['path'])) {
            return null;
        }
        $host = strtolower($parts['host']);
        $fragment = $parts['fragment'] ?? null;

        if ($useAppHost) {
            $appUrl = config('app.url');
            $baseDomain = parse_url($appUrl, PHP_URL_HOST);
            $allowedHosts = [$baseDomain];
        }

        $allowed = array_map('strtolower', (array) $allowedHosts);

        $hostOk = false;
        foreach ($allowed as $base) {
            if ($host === $base) {
                $hostOk = true;
                break;
            }
            if ($allowSubdomains && str_ends_with('.'.$host, '.'.$base)) {
                $hostOk = true;
                break;
            }
            if ($allowSubdomains && str_ends_with($host, '.'.$base)) {
                $hostOk = true;
                break;
            }
        }
        if (! $hostOk) {
            return null;
        }

        $path = rtrim($parts['path'], '/');
        $seg = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));

        foreach ((array) $templates as $tpl) {
            // Split template into path and fragment parts
            $tplParts = explode('#', $tpl, 2);
            $tplPath = rtrim($tplParts[0], '/');
            $tplFragment = $tplParts[1] ?? null;

            // Parse path segments
            $tplSeg = array_values(array_filter(explode('/', $tplPath), fn ($s) => $s !== ''));

            // Check if path segment counts match
            if (count($tplSeg) !== count($seg)) {
                continue;
            }

            $params = [];
            $ok = true;

            // Match path segments
            foreach ($tplSeg as $i => $chunk) {
                if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $chunk, $m)) {
                    $name = $m[1];
                    $val = $seg[$i];

                    if (isset($constraints[$name])) {
                        if (! preg_match('~^'.$constraints[$name].'$~', $val)) {
                            $ok = false;
                            break;
                        }
                    }
                    $params[$name] = $val;
                } else {
                    if ($chunk !== $seg[$i]) {
                        $ok = false;
                        break;
                    }
                }
            }

            if (! $ok) {
                continue;
            }

            // If template has a fragment pattern, match it
            if ($tplFragment !== null) {
                // Template requires a fragment, but URL doesn't have one
                if ($fragment === null) {
                    continue;
                }

                // Parse fragment segments
                $fragmentPath = trim($fragment, '/');
                $fragmentSeg = array_values(array_filter(explode('/', $fragmentPath), fn ($s) => $s !== ''));
                $tplFragmentSeg = array_values(array_filter(explode('/', $tplFragment), fn ($s) => $s !== ''));

                // Check if fragment segment counts match
                if (count($tplFragmentSeg) !== count($fragmentSeg)) {
                    continue;
                }

                // Match fragment segments
                foreach ($tplFragmentSeg as $i => $chunk) {
                    if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $chunk, $m)) {
                        $name = $m[1];
                        $val = $fragmentSeg[$i];

                        if (isset($constraints[$name])) {
                            if (! preg_match('~^'.$constraints[$name].'$~', $val)) {
                                $ok = false;
                                break;
                            }
                        }
                        $params[$name] = $val;
                    } else {
                        if ($chunk !== $fragmentSeg[$i]) {
                            $ok = false;
                            break;
                        }
                    }
                }

                if (! $ok) {
                    continue;
                }
            } elseif ($fragment !== null) {
                // URL has a fragment, but template doesn't expect one
                // Skip without failing
            }

            return ['_template' => $tpl, '_host' => $host] + $params;
        }

        return null;
    }
}
