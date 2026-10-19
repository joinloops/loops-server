<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\User;
use App\Models\UsernameHistory;
use App\Services\UsernameChangeService;
use App\Services\UsernameService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateReservedUsernames extends Command
{
    protected $signature = 'users:update-reserved
                          {--auto-suffix : Automatically add 4 random numbers to all reserved usernames}
                          {--auto-user : Automatically change all to user + 10 random digits}';

    protected $description = 'Check and update users with reserved usernames';

    protected UsernameService $usernameService;

    public function handle(UsernameService $usernameService)
    {
        $this->usernameService = $usernameService;

        $this->info('Checking for users with reserved usernames...');
        $this->newLine();

        $users = User::with('profile')->get();
        $reservedUsers = $users->filter(fn ($user) => $usernameService->isReservedWord($user->username)
        );

        if ($reservedUsers->isEmpty()) {
            $this->info('✓ No users found with reserved usernames!');

            return Command::SUCCESS;
        }

        $this->warn("Found {$reservedUsers->count()} user(s) with reserved usernames.");
        $this->newLine();

        $autoMode = $this->option('auto-suffix') ? 'suffix' :
                   ($this->option('auto-user') ? 'user' : null);

        $updated = 0;
        $skipped = 0;

        foreach ($reservedUsers as $user) {
            $result = $this->processUser($user, $autoMode);

            if ($result === 'updated') {
                $updated++;
            } elseif ($result === 'skipped') {
                $skipped++;
            }

            $this->newLine();
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Updated: {$updated}");
        $this->line("  Skipped: {$skipped}");

        return Command::SUCCESS;
    }

    protected function processUser(User $user, ?string $autoMode): string
    {
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->warn('Reserved username found:');
        $this->line("  ID:       {$user->id}");
        $this->line("  Username: {$user->username}");
        $this->line("  Email:    {$user->email}");
        $this->newLine();

        if ($autoMode) {
            $newUsername = $autoMode === 'suffix'
                ? $this->generateSuffixUsername($user->username)
                : $this->generateUserUsername();

            $this->updateUsername($user, $newUsername);
            $this->info("✓ Updated to: {$newUsername}");

            return 'updated';
        }

        $choice = $this->choice(
            'What would you like to do?',
            [
                'skip' => 'Skip this user',
                'auto-suffix' => 'Auto update (add 4 random numbers)',
                'auto-user' => 'Auto change (user + 10 random digits)',
                'manual' => 'Manual change',
            ],
            'skip'
        );

        switch ($choice) {
            case 'skip':
                $this->line('Skipped.');

                return 'skipped';

            case 'auto-suffix':
                $newUsername = $this->generateSuffixUsername($user->username);
                $this->updateUsername($user, $newUsername);
                $this->info("✓ Updated to: {$newUsername}");

                return 'updated';

            case 'auto-user':
                $newUsername = $this->generateUserUsername();
                $this->updateUsername($user, $newUsername);
                $this->info("✓ Updated to: {$newUsername}");

                return 'updated';

            case 'manual':
                return $this->handleManualChange($user);
        }

        return 'skipped';
    }

    protected function handleManualChange(User $user): string
    {
        while (true) {
            $newUsername = $this->ask('Enter new username (or "cancel" to skip)');

            if (strtolower($newUsername) === 'cancel') {
                $this->line('Cancelled.');

                return 'skipped';
            }

            if (empty($newUsername)) {
                $this->error('Username cannot be empty.');

                continue;
            }

            if (! preg_match('/^[a-zA-Z0-9._-]+$/', $newUsername)) {
                $this->error('Username can only contain letters, numbers, dots, underscores, and hyphens.');

                continue;
            }

            if ($this->usernameService->isReserved($newUsername)) {
                $this->error('This username is also reserved. Please choose another.');

                continue;
            }

            if (User::where('username', $newUsername)->where('id', '!=', $user->id)->exists()) {
                $this->error('This username is already taken. Please choose another.');

                continue;
            }

            if ($this->confirm("Update username to '{$newUsername}'?", true)) {
                $this->updateUsername($user, $newUsername);
                $this->info("✓ Updated to: {$newUsername}");

                return 'updated';
            }
        }
    }

    protected function generateSuffixUsername(string $currentUsername): string
    {
        do {
            $suffix = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $newUsername = $currentUsername.$suffix;
        } while (
            User::where('username', $newUsername)->exists() ||
            $this->usernameService->isReserved($newUsername)
        );

        return $newUsername;
    }

    protected function generateUserUsername(): string
    {
        do {
            $randomDigits = '';
            for ($i = 0; $i < 10; $i++) {
                $randomDigits .= random_int(0, 9);
            }
            $newUsername = 'user'.$randomDigits;
        } while (
            User::where('username', $newUsername)->exists() ||
            $this->usernameService->isReserved($newUsername)
        );

        return $newUsername;
    }

    protected function updateUsername(User $user, string $newUsername): void
    {
        $profile = $user->profile_id ? Profile::find($user->profile_id) : null;

        if ($profile) {
            app(UsernameChangeService::class)->apply($profile, $newUsername, UsernameHistory::SOURCE_ADMIN);

            return;
        }

        DB::transaction(function () use ($user, $newUsername) {
            $user->username = $newUsername;
            $user->save();
        });
    }
}
