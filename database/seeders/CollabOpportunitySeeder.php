<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CollabOpportunitySeeder extends Seeder
{
    /**
     * Legacy collab_opportunities are no longer seeded.
     */
    public function run(): void
    {
        $this->command?->warn('CollabOpportunitySeeder is disabled; seed canonical kolabs instead.');
    }
}
