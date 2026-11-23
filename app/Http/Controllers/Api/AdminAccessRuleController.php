<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminAccessRuleResource;
use App\Models\AccessRule;
use App\Models\AdminAuditLog;
use App\Models\Comment;
use App\Models\Profile;
use App\Models\User;
use App\Services\AccessRuleService;
use App\Services\AdminAuditLogService;
use App\Support\IpRange;
use Carbon\CarbonInterface;
use Closure;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ExcludeAllRoutesFromDocs]
class AdminAccessRuleController extends Controller
{
    use ApiHelpers;

    protected const MATCH_TYPES = [
        AccessRule::MATCH_EXACT,
        AccessRule::MATCH_CIDR,
        AccessRule::MATCH_RANGE,
        AccessRule::MATCH_DOMAIN,
        AccessRule::MATCH_PATTERN,
    ];

    public function __construct(protected AccessRuleService $service) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(AccessRule::TYPES)],
            'match_type' => ['nullable', Rule::in(self::MATCH_TYPES)],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:newest,oldest,hits'],
        ]);

        $query = AccessRule::query()->where('type', $validated['type'] ?? AccessRule::TYPE_IP);

        if (! empty($validated['match_type'])) {
            $query->where('match_type', $validated['match_type']);
        }

        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(fn ($q) => $q->where('value', 'like', '%'.$search.'%')->orWhere('note', 'like', '%'.$search.'%'));
        }

        match ($validated['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('id'),
            'hits' => $query->orderByDesc('hits')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };

        return AdminAccessRuleResource::collection($query->cursorPaginate(20)->withQueryString());
    }

    public function counts(Request $request)
    {
        return response()->json($this->service->counts() + ['current_ip' => $request->ip()]);
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(AccessRule::TYPES)],
            'value' => ['required', 'string', 'max:255'],
            'match_type' => ['nullable', Rule::in(self::MATCH_TYPES)],
            'blocks_login' => ['sometimes', 'boolean'],
            'ignore_id' => ['nullable', 'integer'],
            'samples' => ['sometimes', 'array', 'max:20'],
            'samples.*' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $normalized = $this->service->normalize($validated['type'], $validated['value'], $validated['match_type'] ?? null);
        } catch (ValidationException $e) {
            return response()->json([
                'valid' => false,
                'error' => collect($e->errors())->flatten()->first(),
            ]);
        }

        $draft = $this->service->draft($validated['type'], $normalized, $request->boolean('blocks_login', true));
        $scan = $this->service->scan($draft, 5);

        $existing = AccessRule::query()
            ->where('type', $validated['type'])
            ->where('match_type', $normalized['match_type'])
            ->where('value', $normalized['value'])
            ->when(! empty($validated['ignore_id']), fn ($q) => $q->where('id', '!=', $validated['ignore_id']))
            ->value('id');

        $samples = collect($validated['samples'] ?? [])
            ->filter(fn ($sample) => is_string($sample) && trim($sample) !== '')
            ->map(fn ($sample) => ['value' => $sample, 'matched' => $this->service->probe($draft, $sample)])
            ->values();

        return response()->json([
            'valid' => true,
            'type' => $validated['type'],
            'match_type' => $normalized['match_type'],
            'value' => $normalized['value'],
            'details' => $normalized['details'],
            'matches' => $scan['total'],
            'admins' => $scan['admins'],
            'sample' => $this->service->presentMatches($scan['matches']),
            'includes_current_ip' => $validated['type'] === AccessRule::TYPE_IP
                && IpRange::contains((string) $normalized['ip_start'], (string) $normalized['ip_end'], $request->ip()),
            'admin_conflict' => $this->service->adminConflict($draft, $request->ip()),
            'existing_rule_id' => $existing ? (string) $existing : null,
            'samples' => $samples,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRule($request);
        $type = $validated['type'];
        $normalized = $this->service->normalize($type, $validated['value'], $validated['match_type'] ?? null);

        $this->assertUnique($type, $normalized);

        $rule = $this->service->draft(
            $type,
            $normalized,
            (bool) ($validated['blocks_login'] ?? $type === AccessRule::TYPE_IP),
            (bool) ($validated['blocks_registration'] ?? true)
        );

        $rule->fill([
            'note' => $validated['note'] ?? null,
            'expires_at' => $this->expiry($validated, null),
            'created_by' => $request->user()->id,
        ]);

        $this->assertSafe($rule, $normalized, $request);

        $rule->save();

        app(AdminAuditLogService::class)->logAccessRuleCreate($request->user(), $rule);
        $this->service->flush();

        return new AdminAccessRuleResource($rule->fresh());
    }

    public function update(Request $request, AccessRule $accessRule)
    {
        $validated = $this->validateRule($request, true);
        $matchType = $validated['match_type'] ?? ($accessRule->type === AccessRule::TYPE_IP ? null : $accessRule->match_type);
        $normalized = $this->service->normalize($accessRule->type, $validated['value'], $matchType);

        $this->assertUnique($accessRule->type, $normalized, $accessRule->id);

        $old = $this->snapshot($accessRule);
        $valueChanged = $normalized['value'] !== $accessRule->value;
        $isUsername = $accessRule->type === AccessRule::TYPE_USERNAME;

        $accessRule->fill([
            'match_type' => $normalized['match_type'],
            'value' => $normalized['value'],
            'ip_start' => $normalized['ip_start'],
            'ip_end' => $normalized['ip_end'],
            'blocks_login' => $isUsername ? false : (bool) ($validated['blocks_login'] ?? $accessRule->blocks_login),
            'blocks_registration' => $isUsername ? true : (bool) ($validated['blocks_registration'] ?? $accessRule->blocks_registration),
            'note' => array_key_exists('note', $validated) ? $validated['note'] : $accessRule->note,
            'expires_at' => $this->expiry($validated, $accessRule),
        ]);

        $this->assertSafe($accessRule, $normalized, $request, $valueChanged);

        $accessRule->save();

        app(AdminAuditLogService::class)->logAccessRuleUpdate($request->user(), $accessRule, [
            'old' => $old,
            'new' => $this->snapshot($accessRule),
        ]);

        $this->service->flush();

        return new AdminAccessRuleResource($accessRule->fresh());
    }

    public function destroy(Request $request, AccessRule $accessRule)
    {
        app(AdminAuditLogService::class)->logAccessRuleDelete($request->user(), $accessRule, $this->snapshot($accessRule) + [
            'id' => (string) $accessRule->id,
            'hits' => (int) $accessRule->hits,
            'created_by' => $accessRule->created_by,
        ]);

        $accessRule->delete();
        $this->service->flush();

        return response()->noContent();
    }

    public function matchCounts(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer'],
        ]);

        $counts = $this->service->matchCounts(AccessRule::query()->whereIn('id', $validated['ids'])->get());

        return response()->json([
            'counts' => collect($counts)->mapWithKeys(fn ($count, $id) => [(string) $id => $count]),
        ]);
    }

    public function matches(Request $request, AccessRule $accessRule)
    {
        $scan = $this->service->scan($accessRule, 200);

        return response()->json([
            'data' => $this->service->presentMatches($scan['matches']),
            'meta' => [
                'total' => $scan['total'],
                'admins_skipped' => $scan['admins'],
                'limited' => $scan['total'] > count($scan['matches']),
            ],
            'rule' => (new AdminAccessRuleResource($accessRule))->resolve($request),
        ]);
    }

    public function purge(Request $request, AccessRule $accessRule)
    {
        $validated = $request->validate([
            'profile_ids' => ['required', 'array', 'min:1', 'max:'.AccessRuleService::MAX_BULK],
            'profile_ids.*' => ['required', 'integer'],
            'ban_emails' => ['sometimes', 'boolean'],
            'ban_usernames' => ['sometimes', 'boolean'],
            'confirm' => ['required', 'string', 'in:DELETE'],
        ]);

        $matcher = $this->service->matcher($accessRule);
        $admin = $request->user();
        $options = [
            'register_ip' => false,
            'last_ip' => false,
            'email' => $request->boolean('ban_emails') ? 'exact' : 'none',
            'username' => $request->boolean('ban_usernames'),
            'note' => 'Matched '.$accessRule->type.' rule '.$accessRule->value,
        ];

        $results = $this->processAccounts(
            $validated['profile_ids'],
            $admin,
            function (User $user) use ($matcher, $admin, $options, $request) {
                if (! $matcher($user)) {
                    return 'This account no longer matches the rule.';
                }

                return $this->service->banAndDelete($user, $admin, $options, $request->ip());
            },
            'access_rule:'.$accessRule->id
        );

        return response()->json($results);
    }

    public function suspended(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = User::query()
            ->select(['id', 'profile_id', 'username', 'email', 'register_ip', 'last_ip', 'status', 'created_at'])
            ->where('status', 6)
            ->where(fn ($q) => $q->where('is_admin', false)->orWhereNull('is_admin'))
            ->orderByDesc('id');

        if (! empty($validated['q'])) {
            $search = ltrim(trim($validated['q']), '@');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('register_ip', $search)
                    ->orWhere('last_ip', $search);
            });
        }

        $page = $query->cursorPaginate(20)->withQueryString();
        $users = collect($page->items());
        $profileIds = $users->pluck('profile_id')->filter()->values();

        $profiles = Profile::query()
            ->whereIn('id', $profileIds)
            ->get(['id', 'name', 'avatar', 'video_count', 'followers', 'admin_notes'])
            ->keyBy('id');

        $suspendedAt = AdminAuditLog::query()
            ->where('type', 'profile:suspend')
            ->where('activity_type', Profile::class)
            ->whereIn('activity_id', $profileIds)
            ->selectRaw('activity_id, MAX(created_at) as suspended_at')
            ->groupBy('activity_id')
            ->pluck('suspended_at', 'activity_id');

        $spamAt = AdminAuditLog::query()
            ->where('type', 'spam:suspended')
            ->where('activity_type', User::class)
            ->whereIn('activity_id', $users->pluck('id'))
            ->selectRaw('activity_id, MAX(created_at) as suspended_at')
            ->groupBy('activity_id')
            ->pluck('suspended_at', 'activity_id');

        $comments = Comment::query()
            ->whereIn('profile_id', $profileIds)
            ->selectRaw('profile_id, COUNT(*) as aggregate')
            ->groupBy('profile_id')
            ->pluck('aggregate', 'profile_id');

        $data = $users->map(function (User $user) use ($profiles, $suspendedAt, $spamAt, $comments) {
            $profile = $profiles->get($user->profile_id);
            $manual = $suspendedAt[$user->profile_id] ?? null;
            $spam = $spamAt[$user->id] ?? null;
            $at = $manual ?? $spam;

            return [
                'profile_id' => (string) $user->profile_id,
                'user_id' => (string) $user->id,
                'username' => $user->username,
                'name' => $profile?->name,
                'email' => $user->email,
                'avatar' => $profile->avatar ?? url('/storage/avatars/default.jpg'),
                'register_ip' => $user->register_ip,
                'last_ip' => $user->last_ip,
                'suspended_at' => $at ? Carbon::parse($at)->toIso8601String() : null,
                'suspended_by_spam_check' => ! $manual && (bool) $spam,
                'admin_notes' => $profile?->admin_notes,
                'video_count' => (int) ($profile->video_count ?? 0),
                'followers' => (int) ($profile->followers ?? 0),
                'comment_count' => (int) ($comments[$user->profile_id] ?? 0),
                'created_at' => $user->created_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'next_cursor' => $page->nextCursor()?->encode(),
                'prev_cursor' => $page->previousCursor()?->encode(),
            ],
        ]);
    }

    public function banPreview(Request $request)
    {
        $validated = $request->validate([
            'profile_ids' => ['required', 'array', 'min:1', 'max:'.AccessRuleService::MAX_BULK],
            'profile_ids.*' => ['required', 'integer'],
        ]);

        $ids = array_values(array_unique(array_map('strval', $validated['profile_ids'])));
        $profiles = Profile::query()->whereIn('id', $ids)->get()->keyBy('id');
        $single = count($ids) === 1;
        $accounts = [];
        $skipped = [];

        foreach ($ids as $id) {
            $profile = $profiles->get($id);
            $user = $profile?->user_id ? User::find($profile->user_id) : null;

            if ($error = $this->service->targetError($user, $request->user())) {
                $skipped[] = ['profile_id' => $id, 'username' => $profile?->username, 'reason' => $error];

                continue;
            }

            $accounts[] = $this->service->banPreview($user, $single);
        }

        return response()->json([
            'data' => $accounts,
            'skipped' => $skipped,
        ]);
    }

    public function banAndDelete(Request $request)
    {
        $validated = $request->validate([
            'profile_ids' => ['required', 'array', 'min:1', 'max:'.AccessRuleService::MAX_BULK],
            'profile_ids.*' => ['required', 'integer'],
            'register_ip' => ['sometimes', 'boolean'],
            'last_ip' => ['sometimes', 'boolean'],
            'email' => ['required', 'string', 'in:exact,domain,none'],
            'username' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
            'confirm' => ['required', 'string', 'in:DELETE'],
        ]);

        $admin = $request->user();
        $options = [
            'register_ip' => $request->boolean('register_ip'),
            'last_ip' => $request->boolean('last_ip'),
            'email' => $validated['email'],
            'username' => $request->boolean('username'),
            'note' => $validated['note'] ?? null,
        ];

        $results = $this->processAccounts(
            $validated['profile_ids'],
            $admin,
            fn (User $user) => $this->service->banAndDelete($user, $admin, $options, $request->ip()),
            'ban_delete'
        );

        return response()->json($results);
    }

    protected function processAccounts(array $profileIds, User $admin, Closure $action, string $source): array
    {
        $ids = array_values(array_unique(array_map('strval', $profileIds)));
        $profiles = Profile::query()->whereIn('id', $ids)->get()->keyBy('id');
        $deleted = [];
        $skipped = [];
        $rulesCreated = 0;

        foreach ($ids as $id) {
            $profile = $profiles->get($id);
            $user = $profile?->user_id ? User::find($profile->user_id) : null;

            if ($error = $this->service->targetError($user, $admin)) {
                $skipped[] = ['profile_id' => $id, 'username' => $profile?->username, 'reason' => $error];

                continue;
            }

            try {
                $result = $action($user);
            } catch (\Throwable $e) {
                Log::error('Access rule ban and delete failed', ['profile_id' => $id, 'error' => $e->getMessage()]);
                $skipped[] = ['profile_id' => $id, 'username' => $profile->username, 'reason' => 'This account could not be deleted, check the logs.'];

                continue;
            }

            if (is_string($result)) {
                $skipped[] = ['profile_id' => $id, 'username' => $profile->username, 'reason' => $result];

                continue;
            }

            $rulesCreated += count($result['rules']);

            app(AdminAuditLogService::class)->logProfileBanDelete($admin, $profile, $result['snapshot'] + [
                'source' => $source,
                'rules' => collect($result['rules'])->map(fn (AccessRule $rule) => $rule->type.':'.$rule->value)->values()->all(),
                'skipped' => $result['skipped'],
            ]);

            $deleted[] = ['profile_id' => $id, 'username' => $result['snapshot']['username']];
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
            'rules_created' => $rulesCreated,
        ];
    }

    protected function validateRule(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'type' => [$updating ? 'sometimes' : 'required', Rule::in(AccessRule::TYPES)],
            'value' => ['required', 'string', 'max:255'],
            'match_type' => ['nullable', Rule::in(self::MATCH_TYPES)],
            'blocks_login' => ['sometimes', 'boolean'],
            'blocks_registration' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date'],
            'confirm_broad' => ['sometimes', 'boolean'],
        ]);
    }

    protected function expiry(array $validated, ?AccessRule $rule): ?CarbonInterface
    {
        if (! array_key_exists('expires_at', $validated)) {
            return $rule?->expires_at;
        }

        if (empty($validated['expires_at'])) {
            return null;
        }

        $expires = Carbon::parse($validated['expires_at']);

        if ($expires->isPast() && ! ($rule?->expires_at && $rule->expires_at->equalTo($expires))) {
            throw ValidationException::withMessages(['expires_at' => ['Pick an expiry date in the future.']]);
        }

        return $expires;
    }

    protected function assertUnique(string $type, array $normalized, ?int $ignoreId = null): void
    {
        $exists = AccessRule::query()
            ->where('type', $type)
            ->where('match_type', $normalized['match_type'])
            ->where('value', $normalized['value'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['value' => ['A rule for this value already exists.']]);
        }
    }

    protected function assertSafe(AccessRule $rule, array $normalized, Request $request, bool $checkBreadth = true): void
    {
        if (! $rule->blocks_login && ! $rule->blocks_registration) {
            throw ValidationException::withMessages(['blocks_login' => ['Choose sign in, sign up or both.']]);
        }

        if ($checkBreadth && ! empty($normalized['details']['broad']) && ! $request->boolean('confirm_broad')) {
            throw ValidationException::withMessages([
                'value' => ['This range covers '.$normalized['details']['size'].' addresses. Confirm the broad range to save it.'],
            ]);
        }

        if ($conflict = $this->service->adminConflict($rule, $request->ip())) {
            throw ValidationException::withMessages(['value' => [$conflict]]);
        }
    }

    protected function snapshot(AccessRule $rule): array
    {
        return [
            'type' => $rule->type,
            'match_type' => $rule->match_type,
            'value' => $rule->value,
            'blocks_login' => (bool) $rule->blocks_login,
            'blocks_registration' => (bool) $rule->blocks_registration,
            'note' => $rule->note,
            'expires_at' => $rule->expires_at?->toIso8601String(),
        ];
    }
}
