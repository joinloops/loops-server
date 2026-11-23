<?php

namespace App\Console\Commands;

use App\Models\AccessRule;
use App\Models\AdminAuditLog;
use App\Services\AccessRuleService;
use App\Support\IpRange;
use Illuminate\Console\Command;

class AccessRulesDelete extends Command
{
    protected $signature = 'access-rules:delete
        {id?* : One or more access rule ids to delete}
        {--ip= : Delete every rule that blocks sign in for this IP address}
        {--force : Delete without asking for confirmation}';

    protected $description = 'Delete access rules, for example to sign back in after an IP lockout';

    public function handle(AccessRuleService $service): int
    {
        $ids = collect($this->argument('id'))->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id);

        if ($ip = $this->option('ip')) {
            $hex = IpRange::toHex($ip);

            if (! $hex) {
                $this->error('That is not a valid IP address.');

                return self::FAILURE;
            }

            $ids = $ids->merge(
                AccessRule::query()
                    ->where('type', AccessRule::TYPE_IP)
                    ->where('blocks_login', true)
                    ->where('ip_start', '<=', $hex)
                    ->where('ip_end', '>=', $hex)
                    ->pluck('id')
            );
        }

        if ($ids->isEmpty()) {
            $this->error('Pass at least one rule id or --ip=<address>.');

            return self::FAILURE;
        }

        $rules = AccessRule::query()->whereIn('id', $ids->unique()->values())->get();

        if ($rules->isEmpty()) {
            $this->info('No matching access rules were found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Type', 'Match', 'Value', 'Sign in', 'Sign up', 'Note'],
            $rules->map(fn (AccessRule $rule) => [
                $rule->id,
                $rule->type,
                $rule->match_type,
                $rule->value,
                $rule->blocks_login ? 'yes' : 'no',
                $rule->blocks_registration ? 'yes' : 'no',
                $rule->note,
            ])->all()
        );

        if (! $this->option('force') && ! $this->confirm('Delete '.$rules->count().' rule(s)?', true)) {
            return self::SUCCESS;
        }

        foreach ($rules as $rule) {
            AdminAuditLog::create([
                'user_id' => null,
                'type' => 'access_rule:delete',
                'value' => $rule->only(['type', 'match_type', 'value', 'blocks_login', 'blocks_registration', 'note']) + ['source' => 'cli'],
                'activity_type' => AccessRule::class,
                'activity_id' => $rule->id,
                'action_msg' => null,
                'visibility' => 1,
            ]);

            $rule->delete();
        }

        $service->flush();

        $this->info('Deleted '.$rules->count().' rule(s).');

        return self::SUCCESS;
    }
}
