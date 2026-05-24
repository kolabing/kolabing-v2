<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateMaintainer extends Command
{
    protected $signature = 'admin:create-maintainer
        {name : Maintainer full name}
        {email : Maintainer email}
        {password : Maintainer password}';

    protected $description = 'Create or promote a maintainer account for the admin panel';

    public function handle(): int
    {
        $user = User::query()->updateOrCreate(
            ['email' => (string) $this->argument('email')],
            [
                'name' => (string) $this->argument('name'),
                'password' => (string) $this->argument('password'),
                'is_maintainer' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->info("Maintainer ready: {$user->email}");

        return self::SUCCESS;
    }
}
