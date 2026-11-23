<?php

namespace App\Services;

use App\Models\AccessRule;
use App\Models\Comment;
use App\Models\Profile;
use App\Models\User;
use App\Support\AccessPattern;
use App\Support\IpRange;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AccessRuleService
{
    public const CONTEXT_LOGIN = 'login';

    public const CONTEXT_REGISTER = 'register';

    public const HITS_KEY = 'loops:access-rules:hits:';

    public const MATCH_COUNT_KEY = 'loops:access-rules:matches:';

    public const MATCH_GENERATION_KEY = 'loops:access-rules:matches:generation';

    public const MAX_V4_HOST_BITS = 24;

    public const MAX_V6_HOST_BITS = 112;

    public const BROAD_V4_HOST_BITS = 16;

    public const BROAD_V6_HOST_BITS = 80;

    public const MAX_BULK = 100;

    public const PROTECTED_EMAIL_DOMAINS = [
        'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        'yahoo.com', 'ymail.com', 'rocketmail.com', 'icloud.com', 'me.com', 'mac.com', 'aol.com',
        'proton.me', 'protonmail.com', 'protonmail.ch', 'pm.me', 'gmx.com', 'gmx.net', 'gmx.de',
        'web.de', 'mail.com', 'yandex.com', 'yandex.ru', 'zoho.com', 'fastmail.com', 'hey.com',
        'tutanota.com', 'tuta.io', 'qq.com', '163.com', '126.com', 'mail.ru', 'naver.com',
        'comcast.net', 'verizon.net', 'att.net', 'shaw.ca', 'telus.net', 'rogers.com',
        'sympatico.ca', 'btinternet.com', 'orange.fr', 'free.fr', 't-online.de', 'libero.it',
        'co.uk', 'org.uk', 'ac.uk', 'gov.uk', 'com.au', 'net.au', 'co.nz', 'co.jp', 'co.in',
        'com.br', 'com.mx', 'co.za', 'com.cn', 'com.tw', 'co.kr',
    ];

    protected const DOMAIN_REGEX = '/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/';

    protected const USERNAME_REGEX = '/^[\pL\pN._-]{1,64}$/u';

    protected ?array $lists = null;

    public function lists(): array
    {
        if ($this->lists !== null) {
            return $this->lists;
        }

        return $this->lists = Cache::rememberForever(AccessRule::CACHE_KEY, fn () => $this->buildLists());
    }

    public function flush(): void
    {
        $this->lists = null;
        AccessRule::flushCache();
    }

    protected function buildLists(): array
    {
        $lists = [
            'ip_exact' => [],
            'ip_ranges' => [],
            'email_exact' => [],
            'email_domains' => [],
            'email_patterns' => [],
            'username_exact' => [],
            'username_patterns' => [],
        ];

        AccessRule::query()->active()->orderBy('id')->each(function (AccessRule $rule) use (&$lists) {
            $meta = [
                'id' => (int) $rule->id,
                'l' => (bool) $rule->blocks_login,
                'r' => (bool) $rule->blocks_registration,
                'x' => $rule->expires_at?->getTimestamp(),
            ];

            if ($rule->type === AccessRule::TYPE_IP) {
                if (! $rule->ip_start || ! $rule->ip_end) {
                    return;
                }

                if ($rule->ip_start === $rule->ip_end) {
                    $lists['ip_exact'][$rule->ip_start] = $meta;
                } else {
                    $lists['ip_ranges'][] = $meta + ['s' => $rule->ip_start, 'e' => $rule->ip_end];
                }

                return;
            }

            $key = $rule->type.'_'.match ($rule->match_type) {
                AccessRule::MATCH_EXACT => 'exact',
                AccessRule::MATCH_DOMAIN => 'domains',
                default => 'patterns',
            };

            if (! array_key_exists($key, $lists)) {
                return;
            }

            if ($rule->match_type === AccessRule::MATCH_PATTERN) {
                $lists[$key][] = $meta + ['p' => AccessPattern::compile($rule->value)];
            } else {
                $lists[$key][mb_strtolower($rule->value)] = $meta;
            }
        }, 500);

        return $lists;
    }

    protected function applies(array $meta, string $context): bool
    {
        $flag = $context === self::CONTEXT_LOGIN ? 'l' : 'r';

        return $meta[$flag] && ($meta['x'] === null || $meta['x'] > time());
    }

    public function findIpRule(?string $ip, string $context): ?array
    {
        $hex = IpRange::toHex($ip);

        if (! $hex) {
            return null;
        }

        $lists = $this->lists();

        if (($meta = $lists['ip_exact'][$hex] ?? null) && $this->applies($meta, $context)) {
            return $meta;
        }

        foreach ($lists['ip_ranges'] as $meta) {
            if ($this->applies($meta, $context) && strcmp($meta['s'], $hex) <= 0 && strcmp($hex, $meta['e']) <= 0) {
                return $meta;
            }
        }

        return null;
    }

    public function findEmailRule(?string $email, string $context): ?array
    {
        $email = $this->normalizeEmailAddress($email);

        if (! $email) {
            return null;
        }

        $lists = $this->lists();

        if (($meta = $lists['email_exact'][$email] ?? null) && $this->applies($meta, $context)) {
            return $meta;
        }

        if (($meta = $this->findDomainRule($this->emailDomain($email))) && $this->applies($meta, $context)) {
            return $meta;
        }

        foreach ($lists['email_patterns'] as $meta) {
            if ($this->applies($meta, $context) && AccessPattern::test($meta['p'], $email)) {
                return $meta;
            }
        }

        return null;
    }

    public function findDomainRule(?string $domain): ?array
    {
        if (! $domain) {
            return null;
        }

        $domains = $this->lists()['email_domains'];
        $parts = explode('.', $domain);

        while (count($parts) >= 2) {
            $candidate = implode('.', $parts);

            if (($meta = $domains[$candidate] ?? null) && ($meta['x'] === null || $meta['x'] > time())) {
                return $meta;
            }

            array_shift($parts);
        }

        return null;
    }

    public function findUsernameRule(?string $username): ?array
    {
        if (! is_string($username)) {
            return null;
        }

        $username = mb_strtolower(ltrim(trim($username), '@'));

        if ($username === '') {
            return null;
        }

        $lists = $this->lists();

        if (($meta = $lists['username_exact'][$username] ?? null) && $this->applies($meta, self::CONTEXT_REGISTER)) {
            return $meta;
        }

        foreach ($lists['username_patterns'] as $meta) {
            if ($this->applies($meta, self::CONTEXT_REGISTER) && AccessPattern::test($meta['p'], $username)) {
                return $meta;
            }
        }

        return null;
    }

    public function isUsernameBlocked(?string $username, bool $record = false): bool
    {
        $rule = $this->findUsernameRule($username);

        if ($rule && $record) {
            $this->recordHit($rule['id'], self::CONTEXT_REGISTER);
        }

        return $rule !== null;
    }

    public function isEmailBlocked(?string $email, string $context = self::CONTEXT_REGISTER, bool $record = false): bool
    {
        $rule = $this->findEmailRule($email, $context);

        if ($rule && $record) {
            $this->recordHit($rule['id'], $context);
        }

        return $rule !== null;
    }

    public function denial(?string $ip, string $context, ?string $email = null, ?string $username = null, bool $record = true): ?array
    {
        if ($ip !== null && ($rule = $this->findIpRule($ip, $context))) {
            return $this->deny($rule, 'ip', $context, $record);
        }

        if ($email !== null && ($rule = $this->findEmailRule($email, $context))) {
            return $this->deny($rule, 'email', $context, $record);
        }

        if ($username !== null && $context === self::CONTEXT_REGISTER && ($rule = $this->findUsernameRule($username))) {
            return $this->deny($rule, 'username', $context, $record);
        }

        return null;
    }

    public function enforce(Request $request, string $context, ?string $email = null, ?string $username = null, string $errorKey = 'email'): void
    {
        $denial = $this->denial($request->ip(), $context, $email, $username);

        if (! $denial) {
            return;
        }

        $key = $denial['field'] === 'username' ? 'username' : $errorKey;

        throw ValidationException::withMessages([$key => [$denial['message']]]);
    }

    protected function deny(array $rule, string $field, string $context, bool $record): array
    {
        if ($record) {
            $this->recordHit($rule['id'], $context);
        }

        return [
            'field' => $field,
            'rule_id' => $rule['id'],
            'message' => $this->message($field, $context),
        ];
    }

    public function message(string $field, string $context): string
    {
        return match (true) {
            $field === 'ip' && $context === self::CONTEXT_LOGIN => 'Sign in is not available from your network. Please contact support if you think this is a mistake.',
            $field === 'ip' => 'Sign up is not available from your network. Please contact support if you think this is a mistake.',
            $field === 'email' && $context === self::CONTEXT_LOGIN => 'This account cannot sign in right now. Please contact support if you think this is a mistake.',
            $field === 'email' => 'This email address cannot be used to sign up.',
            default => 'This username is not available.',
        };
    }

    public function recordHit(int $ruleId, string $context): void
    {
        try {
            DB::table('access_rules')->where('id', $ruleId)->update([
                'hits' => DB::raw('hits + 1'),
                'last_hit_at' => now(),
            ]);

            $key = self::HITS_KEY.$context.':'.now()->format('Ymd');
            Cache::add($key, 0, now()->addDays(8));
            Cache::increment($key);
        } catch (\Throwable $e) {
            Log::warning('Access rule hit could not be recorded', ['rule_id' => $ruleId, 'error' => $e->getMessage()]);
        }
    }

    public function attemptStats(int $days = 7): array
    {
        $stats = [self::CONTEXT_LOGIN => 0, self::CONTEXT_REGISTER => 0];

        for ($i = 0; $i < $days; $i++) {
            $day = now()->subDays($i)->format('Ymd');

            foreach (array_keys($stats) as $context) {
                $stats[$context] += (int) Cache::get(self::HITS_KEY.$context.':'.$day, 0);
            }
        }

        return $stats;
    }

    public function normalizeEmailAddress(?string $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = mb_strtolower(trim($email));

        return str_contains($email, '@') ? $email : null;
    }

    public function emailDomain(?string $email): ?string
    {
        if (! is_string($email) || ! str_contains($email, '@')) {
            return null;
        }

        $domain = mb_strtolower(trim(substr($email, strrpos($email, '@') + 1)));

        return $domain !== '' ? $domain : null;
    }

    public function isProtectedDomain(?string $domain): bool
    {
        return $domain !== null && in_array(mb_strtolower($domain), self::PROTECTED_EMAIL_DOMAINS, true);
    }

    public function normalize(string $type, string $value, ?string $matchType = null): array
    {
        $value = trim($value);

        if ($value === '') {
            $this->fail('Enter a value for this rule.');
        }

        return match ($type) {
            AccessRule::TYPE_IP => $this->normalizeIp($value),
            AccessRule::TYPE_EMAIL => $this->normalizeEmailRule($value, $matchType),
            AccessRule::TYPE_USERNAME => $this->normalizeUsernameRule($value, $matchType),
            default => $this->fail('Unknown rule type.'),
        };
    }

    protected function normalizeIp(string $value): array
    {
        $parsed = IpRange::parse($value);

        if (! $parsed) {
            $this->fail('Enter a valid IP address, a CIDR range like 203.0.113.0/24 or a range like 192.0.2.10-192.0.2.80.');
        }

        $v4 = $parsed['version'] === 4;

        if ($parsed['host_bits'] > ($v4 ? self::MAX_V4_HOST_BITS : self::MAX_V6_HOST_BITS)) {
            $this->fail($v4 ? 'IPv4 ranges can be at most a /8.' : 'IPv6 ranges can be at most a /16.');
        }

        return [
            'match_type' => $parsed['match_type'],
            'value' => $parsed['value'],
            'ip_start' => $parsed['start'],
            'ip_end' => $parsed['end'],
            'details' => [
                'version' => $parsed['version'],
                'first' => $parsed['first'],
                'last' => $parsed['last'],
                'size' => $parsed['size'],
                'broad' => $parsed['host_bits'] > ($v4 ? self::BROAD_V4_HOST_BITS : self::BROAD_V6_HOST_BITS),
            ],
        ];
    }

    protected function normalizeEmailRule(string $value, ?string $matchType): array
    {
        $matchType = $matchType ?: (str_contains($value, '@') ? AccessRule::MATCH_EXACT : AccessRule::MATCH_DOMAIN);

        if ($matchType === AccessRule::MATCH_PATTERN) {
            if ($error = AccessPattern::validate($value)) {
                $this->fail($error);
            }

            return $this->normalized(AccessRule::MATCH_PATTERN, $value);
        }

        if ($matchType === AccessRule::MATCH_EXACT) {
            $email = mb_strtolower($value);

            if (mb_strlen($email) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->fail('Enter a valid email address.');
            }

            return $this->normalized(AccessRule::MATCH_EXACT, $email);
        }

        if ($matchType === AccessRule::MATCH_DOMAIN) {
            $domain = mb_strtolower(ltrim($value, '@*.'));

            if (! preg_match(self::DOMAIN_REGEX, $domain)) {
                $this->fail('Enter a valid domain like example.com.');
            }

            if ($this->isProtectedDomain($domain)) {
                $this->fail('This is a common email provider or public suffix. Block specific addresses or use a pattern instead.');
            }

            return $this->normalized(AccessRule::MATCH_DOMAIN, $domain);
        }

        $this->fail('Email rules can match an exact address, a domain or a pattern.');
    }

    protected function normalizeUsernameRule(string $value, ?string $matchType): array
    {
        $matchType = $matchType ?: AccessRule::MATCH_EXACT;

        if ($matchType === AccessRule::MATCH_PATTERN) {
            if ($error = AccessPattern::validate($value)) {
                $this->fail($error);
            }

            return $this->normalized(AccessRule::MATCH_PATTERN, $value);
        }

        if ($matchType === AccessRule::MATCH_EXACT) {
            $username = mb_strtolower(ltrim($value, '@'));

            if (! preg_match(self::USERNAME_REGEX, $username)) {
                $this->fail('Usernames can only contain letters, numbers, dots, dashes and underscores.');
            }

            return $this->normalized(AccessRule::MATCH_EXACT, $username);
        }

        $this->fail('Username rules can match an exact username or a pattern.');
    }

    protected function normalized(string $matchType, string $value): array
    {
        return [
            'match_type' => $matchType,
            'value' => $value,
            'ip_start' => null,
            'ip_end' => null,
            'details' => [],
        ];
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['value' => [$message]]);
    }

    public function matcher(AccessRule $rule): Closure
    {
        $value = (string) $rule->value;

        if ($rule->type === AccessRule::TYPE_IP) {
            $start = (string) $rule->ip_start;
            $end = (string) $rule->ip_end;

            return function ($user) use ($start, $end) {
                $register = ! empty($user->register_ip) && IpRange::contains($start, $end, $user->register_ip);
                $last = ! empty($user->last_ip) && IpRange::contains($start, $end, $user->last_ip);

                if ($register && $last) {
                    return 'both';
                }

                return $register ? 'register_ip' : ($last ? 'last_ip' : null);
            };
        }

        if ($rule->type === AccessRule::TYPE_EMAIL) {
            if ($rule->match_type === AccessRule::MATCH_EXACT) {
                return fn ($user) => ! empty($user->email) && mb_strtolower(trim($user->email)) === $value ? 'email' : null;
            }

            if ($rule->match_type === AccessRule::MATCH_DOMAIN) {
                return function ($user) use ($value) {
                    $domain = $this->emailDomain($user->email ?? null);

                    return $domain && ($domain === $value || str_ends_with($domain, '.'.$value)) ? 'email' : null;
                };
            }

            $regex = AccessPattern::compile($value);

            return fn ($user) => ! empty($user->email) && AccessPattern::test($regex, mb_strtolower(trim($user->email))) ? 'email' : null;
        }

        if ($rule->match_type === AccessRule::MATCH_EXACT) {
            return fn ($user) => ! empty($user->username) && mb_strtolower($user->username) === $value ? 'username' : null;
        }

        $regex = AccessPattern::compile($value);

        return fn ($user) => ! empty($user->username) && AccessPattern::test($regex, mb_strtolower($user->username)) ? 'username' : null;
    }

    protected function baseUserQuery(): Builder
    {
        return User::query()
            ->select(['id', 'profile_id', 'username', 'email', 'register_ip', 'last_ip', 'status', 'is_admin', 'created_at'])
            ->where('status', '!=', 8)
            ->where('username', 'not like', AccountDeletionService::TOMBSTONE_PREFIX.'%');
    }

    public function candidates(AccessRule $rule): Builder
    {
        $query = $this->baseUserQuery();
        $value = (string) $rule->value;

        if ($rule->type === AccessRule::TYPE_IP) {
            $start = (string) $rule->ip_start;
            $end = (string) $rule->ip_end;
            $like = IpRange::v4LikePrefix($start, $end);

            if ($like === null) {
                return $query->where(fn ($q) => $q->where('register_ip', 'like', '%:%')->orWhere('last_ip', 'like', '%:%'));
            }

            $operator = str_contains($like, '%') ? 'like' : '=';

            return $query->where(fn ($q) => $q->where('register_ip', $operator, $like)->orWhere('last_ip', $operator, $like));
        }

        if ($rule->type === AccessRule::TYPE_EMAIL) {
            return match ($rule->match_type) {
                AccessRule::MATCH_EXACT => $query->whereRaw('LOWER(email) = ?', [$value]),
                AccessRule::MATCH_DOMAIN => $query->where(fn ($q) => $q->where('email', 'like', '%@'.$value)->orWhere('email', 'like', '%.'.$value)),
                default => $query->whereNotNull('email'),
            };
        }

        if ($rule->match_type === AccessRule::MATCH_EXACT) {
            return $query->whereRaw('LOWER(username) = ?', [$value]);
        }

        return $query;
    }

    public function scan(AccessRule $rule, ?int $limit = 200): array
    {
        $matcher = $this->matcher($rule);
        $matches = [];
        $total = 0;
        $admins = 0;

        $this->candidates($rule)->chunkById(1000, function ($users) use ($matcher, $limit, &$matches, &$total, &$admins) {
            foreach ($users as $user) {
                $on = $matcher($user);

                if (! $on) {
                    continue;
                }

                if ($user->getAttribute('is_admin')) {
                    $admins++;

                    continue;
                }

                $total++;

                if ($limit === null || count($matches) < $limit) {
                    $matches[] = ['user' => $user, 'matched_on' => $on];
                }
            }
        });

        return ['matches' => $matches, 'total' => $total, 'admins' => $admins];
    }

    public function matchCounts(Collection $rules): array
    {
        $result = [];
        $narrow = [];
        $broad = [];

        foreach ($rules as $rule) {
            $cached = Cache::get($this->matchCountKey($rule));

            if ($cached !== null) {
                $result[$rule->id] = (int) $cached;

                continue;
            }

            if ($rule->type === AccessRule::TYPE_IP && IpRange::v4LikePrefix((string) $rule->ip_start, (string) $rule->ip_end) !== null) {
                $narrow[$rule->id] = $rule;
            } else {
                $broad[$rule->id] = $rule;
            }
        }

        foreach ($narrow as $id => $rule) {
            $result[$id] = $this->scan($rule, 0)['total'];
            Cache::put($this->matchCountKey($rule), $result[$id], now()->addMinutes(10));
        }

        if ($broad) {
            $matchers = array_map(fn ($rule) => $this->matcher($rule), $broad);
            $counts = array_fill_keys(array_keys($broad), 0);

            $this->baseUserQuery()
                ->where(fn ($q) => $q->where('is_admin', false)->orWhereNull('is_admin'))
                ->chunkById(1000, function ($users) use ($matchers, &$counts) {
                    foreach ($users as $user) {
                        foreach ($matchers as $id => $matcher) {
                            if ($matcher($user)) {
                                $counts[$id]++;
                            }
                        }
                    }
                });

            foreach ($counts as $id => $count) {
                $result[$id] = $count;
                Cache::put($this->matchCountKey($broad[$id]), $count, now()->addMinutes(10));
            }
        }

        return $result;
    }

    protected function matchCountKey(AccessRule $rule): string
    {
        $generation = (int) Cache::get(self::MATCH_GENERATION_KEY, 0);

        return self::MATCH_COUNT_KEY.$generation.':'.$rule->id.':'.($rule->updated_at?->getTimestamp() ?? 0);
    }

    public function forgetMatchCounts(): void
    {
        Cache::add(self::MATCH_GENERATION_KEY, 0);
        Cache::increment(self::MATCH_GENERATION_KEY);
    }

    public function presentMatches(array $matches): array
    {
        if (empty($matches)) {
            return [];
        }

        $profiles = Profile::query()
            ->whereIn('id', collect($matches)->pluck('user.profile_id')->filter()->unique()->values())
            ->get(['id', 'name', 'username', 'avatar', 'video_count', 'followers', 'status'])
            ->keyBy('id');

        return collect($matches)->map(function ($match) use ($profiles) {
            $user = $match['user'];
            $profile = $profiles->get($user->profile_id);

            return [
                'profile_id' => (string) $user->profile_id,
                'user_id' => (string) $user->id,
                'username' => $user->username,
                'name' => $profile?->name,
                'email' => $user->email,
                'avatar' => $profile->avatar ?? url('/storage/avatars/default.jpg'),
                'status' => $profile ? $profile->getStatusDescription() : 'unknown',
                'register_ip' => $user->register_ip,
                'last_ip' => $user->last_ip,
                'matched_on' => $match['matched_on'],
                'video_count' => (int) ($profile->video_count ?? 0),
                'followers' => (int) ($profile->followers ?? 0),
                'created_at' => $user->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    public function probe(AccessRule $rule, string $sample): bool
    {
        $sample = trim($sample);

        $subject = match ($rule->type) {
            AccessRule::TYPE_IP => (object) ['register_ip' => $sample, 'last_ip' => null],
            AccessRule::TYPE_EMAIL => (object) ['email' => $sample],
            default => (object) ['username' => ltrim($sample, '@')],
        };

        return (bool) $this->matcher($rule)($subject);
    }

    public function draft(string $type, array $normalized, bool $blocksLogin = true, bool $blocksRegistration = true): AccessRule
    {
        return new AccessRule([
            'type' => $type,
            'match_type' => $normalized['match_type'],
            'value' => $normalized['value'],
            'ip_start' => $normalized['ip_start'],
            'ip_end' => $normalized['ip_end'],
            'blocks_login' => $type === AccessRule::TYPE_USERNAME ? false : $blocksLogin,
            'blocks_registration' => $type === AccessRule::TYPE_USERNAME ? true : $blocksRegistration,
        ]);
    }

    public function adminConflict(AccessRule $rule, ?string $currentIp): ?string
    {
        if (! $rule->blocks_login || $rule->type === AccessRule::TYPE_USERNAME) {
            return null;
        }

        if ($rule->type === AccessRule::TYPE_IP && $currentIp && IpRange::contains((string) $rule->ip_start, (string) $rule->ip_end, $currentIp)) {
            return 'This rule would block sign in from your current IP address.';
        }

        $matcher = $this->matcher($rule);

        foreach (User::query()->where('is_admin', true)->get(['id', 'username', 'email', 'last_ip']) as $admin) {
            $subject = $rule->type === AccessRule::TYPE_IP
                ? (object) ['register_ip' => null, 'last_ip' => $admin->last_ip]
                : $admin;

            if ($matcher($subject)) {
                return 'This rule would block sign in for the admin account @'.$admin->username.'.';
            }
        }

        return null;
    }

    public function targetError(?User $user, User $admin): ?string
    {
        if (! $user) {
            return 'Account not found.';
        }

        if ($user->is_admin) {
            return 'Admin accounts can only be deleted through the CLI.';
        }

        if ((int) $user->id === (int) $admin->id) {
            return 'You cannot delete your own account from the dashboard.';
        }

        $profile = $user->profile;

        if (! $profile || ! $profile->local || $profile->isPurged()) {
            return 'Only active local accounts can be deleted.';
        }

        return null;
    }

    public function banPreview(User $user, bool $withDomainCount = false): array
    {
        $profile = $user->profile;
        $email = $this->normalizeEmailAddress($user->email);
        $domain = $this->emailDomain($email);
        $tombstoned = app(AccountDeletionService::class)->isTombstoneUsername($user->username);

        $preview = [
            'profile_id' => (string) $user->profile_id,
            'user_id' => (string) $user->id,
            'username' => $user->username,
            'name' => $profile?->name,
            'avatar' => $profile->avatar ?? url('/storage/avatars/default.jpg'),
            'status' => $profile ? $profile->getStatusDescription() : 'unknown',
            'email' => $user->email,
            'email_domain' => $domain,
            'domain_protected' => $this->isProtectedDomain($domain),
            'domain_blocked' => (bool) $this->findDomainRule($domain),
            'email_blocked' => $email ? (bool) $this->findEmailRule($email, self::CONTEXT_REGISTER) : false,
            'register_ip' => $user->register_ip,
            'register_ip_blocked' => $this->ipFullyBlocked($user->register_ip),
            'last_ip' => $user->last_ip,
            'last_ip_blocked' => $this->ipFullyBlocked($user->last_ip),
            'username_bannable' => ! $tombstoned,
            'username_blocked' => ! $tombstoned && $this->findUsernameRule($user->username) !== null,
            'video_count' => (int) ($profile->video_count ?? 0),
            'followers' => (int) ($profile->followers ?? 0),
            'comment_count' => $profile ? Comment::where('profile_id', $profile->id)->count() : 0,
            'domain_accounts' => null,
        ];

        if ($withDomainCount && $domain) {
            $preview['domain_accounts'] = User::query()
                ->where('id', '!=', $user->id)
                ->where('status', '!=', 8)
                ->where(fn ($q) => $q->where('email', 'like', '%@'.$domain)->orWhere('email', 'like', '%.'.$domain))
                ->count();
        }

        return $preview;
    }

    protected function ipFullyBlocked(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        return $this->findIpRule($ip, self::CONTEXT_LOGIN) !== null
            && $this->findIpRule($ip, self::CONTEXT_REGISTER) !== null;
    }

    public function banAndDelete(User $user, User $admin, array $options, ?string $currentIp = null): array
    {
        $snapshot = [
            'user_id' => (string) $user->id,
            'profile_id' => (string) $user->profile_id,
            'username' => $user->username,
            'email' => $user->email,
            'register_ip' => $user->register_ip,
            'last_ip' => $user->last_ip,
            'previous_status' => $user->profile?->getStatusDescription(),
        ];

        $note = trim((string) ($options['note'] ?? '')) ?: 'Banned with @'.$user->username;
        $attributes = ['note' => mb_substr($note, 0, 500), 'source_profile_id' => $user->profile_id];
        $created = [];
        $skipped = [];

        $ips = array_values(array_unique(array_filter([
            ! empty($options['register_ip']) ? IpRange::canonical($user->register_ip) : null,
            ! empty($options['last_ip']) ? IpRange::canonical($user->last_ip) : null,
        ])));

        foreach ($ips as $ip) {
            if ($this->ipFullyBlocked($ip)) {
                $skipped[] = ['value' => $ip, 'reason' => 'already_blocked'];

                continue;
            }

            $normalized = $this->normalize(AccessRule::TYPE_IP, $ip);
            $blocksLogin = $this->adminConflict($this->draft(AccessRule::TYPE_IP, $normalized), $currentIp) === null;

            if (! $blocksLogin) {
                $skipped[] = ['value' => $ip, 'reason' => 'sign_in_matches_admin'];
            }

            if ($rule = $this->ensureRule(AccessRule::TYPE_IP, $normalized, $attributes + ['blocks_login' => $blocksLogin, 'blocks_registration' => true], $admin)) {
                $created[] = $rule;
            }
        }

        $email = $this->normalizeEmailAddress($user->email);
        $emailMode = $options['email'] ?? 'exact';

        if ($email && $emailMode === 'domain') {
            $domain = $this->emailDomain($email);

            if ($this->isProtectedDomain($domain)) {
                $skipped[] = ['value' => $domain, 'reason' => 'protected_domain'];
                $emailMode = 'exact';
            } elseif ($this->findDomainRule($domain)) {
                $skipped[] = ['value' => $domain, 'reason' => 'already_blocked'];
                $emailMode = 'none';
            } else {
                $rule = $this->tryEnsure(AccessRule::TYPE_EMAIL, $domain, AccessRule::MATCH_DOMAIN, $attributes, $admin, $skipped);
                $emailMode = 'none';

                if ($rule) {
                    $created[] = $rule;
                }
            }
        }

        if ($email && $emailMode === 'exact') {
            if ($this->findEmailRule($email, self::CONTEXT_REGISTER)) {
                $skipped[] = ['value' => $email, 'reason' => 'already_blocked'];
            } elseif ($rule = $this->tryEnsure(AccessRule::TYPE_EMAIL, $email, AccessRule::MATCH_EXACT, $attributes, $admin, $skipped)) {
                $created[] = $rule;
            }
        }

        $username = $user->username;

        if (! empty($options['username']) && $username && ! app(AccountDeletionService::class)->isTombstoneUsername($username)) {
            if ($this->findUsernameRule($username)) {
                $skipped[] = ['value' => $username, 'reason' => 'already_blocked'];
            } elseif ($rule = $this->tryEnsure(AccessRule::TYPE_USERNAME, $username, AccessRule::MATCH_EXACT, $attributes, $admin, $skipped)) {
                $created[] = $rule;
            }
        }

        $this->flush();

        app(AccountDeletionService::class)->deleteByAdmin($user, $admin);

        $this->forgetMatchCounts();

        return [
            'snapshot' => $snapshot,
            'rules' => $created,
            'skipped' => $skipped,
        ];
    }

    protected function tryEnsure(string $type, string $value, string $matchType, array $attributes, User $admin, array &$skipped): ?AccessRule
    {
        try {
            $normalized = $this->normalize($type, $value, $matchType);
        } catch (ValidationException $e) {
            $skipped[] = ['value' => $value, 'reason' => 'invalid'];

            return null;
        }

        return $this->ensureRule($type, $normalized, $attributes + ['blocks_login' => false, 'blocks_registration' => true], $admin);
    }

    protected function ensureRule(string $type, array $normalized, array $attributes, User $admin): ?AccessRule
    {
        $rule = AccessRule::firstOrCreate(
            ['type' => $type, 'match_type' => $normalized['match_type'], 'value' => $normalized['value']],
            [
                'ip_start' => $normalized['ip_start'],
                'ip_end' => $normalized['ip_end'],
                'blocks_login' => (bool) ($attributes['blocks_login'] ?? false),
                'blocks_registration' => (bool) ($attributes['blocks_registration'] ?? true),
                'note' => $attributes['note'] ?? null,
                'source_profile_id' => $attributes['source_profile_id'] ?? null,
                'created_by' => $admin->id,
            ]
        );

        $this->lists = null;

        return $rule->wasRecentlyCreated ? $rule : null;
    }

    public function counts(): array
    {
        $counts = Cache::remember(AccessRule::COUNTS_CACHE_KEY, now()->addMinutes(10), function () {
            $out = [
                AccessRule::TYPE_IP => ['total' => 0],
                AccessRule::TYPE_EMAIL => ['total' => 0],
                AccessRule::TYPE_USERNAME => ['total' => 0],
            ];

            $rows = DB::table('access_rules')
                ->selectRaw('type, match_type, COUNT(*) as aggregate')
                ->groupBy('type', 'match_type')
                ->get();

            foreach ($rows as $row) {
                if (! isset($out[$row->type])) {
                    continue;
                }

                $out[$row->type][$row->match_type] = (int) $row->aggregate;
                $out[$row->type]['total'] += (int) $row->aggregate;
            }

            return $out;
        });

        $counts['suspended'] = User::query()
            ->where('status', 6)
            ->where(fn ($q) => $q->where('is_admin', false)->orWhereNull('is_admin'))
            ->count();

        $counts['attempts'] = $this->attemptStats();

        return $counts;
    }
}
