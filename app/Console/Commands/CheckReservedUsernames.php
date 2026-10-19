<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UsernameService;
use Illuminate\Console\Command;

class CheckReservedUsernames extends Command
{
    protected $signature = 'users:check-reserved
                          {--show-all : Show all checked usernames, not just reserved ones}
                          {--limit= : Limit the number of users to check}';

    protected $description = 'Check if any users have reserved usernames';

    public function handle(UsernameService $usernameService)
    {
        $this->info('Checking usernames against reserved list...');
        $this->newLine();

        $query = User::query();

        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $users = $query->get();
        $reservedUsers = collect();

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        foreach ($users as $user) {
            if ($usernameService->isReservedWord($user->username)) {
                $reservedUsers->push($user);
            } elseif ($this->option('show-all')) {
                $this->line("✓ {$user->username} (ID: {$user->id}) - OK");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($reservedUsers->isEmpty()) {
            $this->info('✓ No users found with reserved usernames!');

            return Command::SUCCESS;
        }

        $this->warn("Found {$reservedUsers->count()} user(s) with reserved usernames:");
        $this->newLine();

        $this->table(
            ['ID', 'Username', 'Email', 'Created At'],
            $reservedUsers->map(fn ($user) => [
                $user->id,
                $user->username,
                $user->email,
                $user->created_at->format('Y-m-d H:i:s'),
            ])
        );

        return Command::SUCCESS;
    }
}
