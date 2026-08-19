<?php

declare(strict_types=1);

use App\Models\CrmAccount;
use App\Services\CrmScoreService;
use Illuminate\Database\Migrations\Migration;

/**
 * Recompute community scores with the new verified-community FIT model
 * (audience / collabs / recency / confidence / locality) so the verified set
 * stops scoring 0 and becomes sortable. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $svc = app(CrmScoreService::class);
        CrmAccount::query()->where('type', 'community')->chunkById(200, function ($rows) use ($svc): void {
            foreach ($rows as $r) {
                $svc->recalculate($r);
            }
        });
    }

    public function down(): void
    {
        // No-op.
    }
};
