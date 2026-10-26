<?php

namespace App\Jobs\User;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserAccountDeleteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 80;

    public int $userId;

    public bool $forceDelete;

    public array $context;

    /**
     * @param  User|int  $user  The user to purge
     * @param  bool  $forceDelete  Kept for compatibility, purges are always permanent
     * @param  array  $context  Additional context (source, immediate, etc.)
     */
    public function __construct(User|int $user, bool $forceDelete = true, array $context = [])
    {
        $this->userId = $user instanceof User ? $user->id : $user;
        $this->forceDelete = $forceDelete;
        $this->context = array_merge([
            'queued_at' => now()->toISOString(),
            'deleted_by' => auth()->id(),
            'pass' => 1,
        ], $context);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(AccountDeletionService $service): void
    {
        $lock = Cache::lock('loops:account-purge:lock:'.$this->userId, $this->timeout + 10);

        if (! $lock->get()) {
            return;
        }

        try {
            $finished = $service->purge($this->userId);
        } finally {
            $lock->release();
        }

        if ($finished) {
            return;
        }

        $context = $this->context;
        $context['pass'] = ($context['pass'] ?? 1) + 1;

        self::dispatch($this->userId, $this->forceDelete, $context)
            ->onQueue($this->queue ?? 'default')
            ->delay(now()->addSeconds(5));
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('User account purge failed permanently', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'context' => $this->context,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'user-deletion',
            'user:'.$this->userId,
        ];
    }
}
