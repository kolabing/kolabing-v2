<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IssueMaintainerApiToken extends Command
{
    protected $signature = 'admin:issue-api-token
        {email : Existing maintainer email}
        {--name=agent-api : Sanctum token name, for identifying/revoking it later}';

    protected $description = 'Issue a Sanctum API token for an existing maintainer, for the token-authenticated GET /api/v1/admin/* read API';

    public function handle(): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        if (! $user->isMaintainer()) {
            $this->error("{$user->email} is not a maintainer (is_maintainer=false) — the token would be issued but auth:sanctum + maintainer.token would still 403 it.");

            return self::FAILURE;
        }

        $token = $user->createToken((string) $this->option('name'));

        $this->newLine();
        $this->line('Token (shown once — copy it now, it is not recoverable after this):');
        $this->line($token->plainTextToken);
        $this->newLine();

        return self::SUCCESS;
    }
}
