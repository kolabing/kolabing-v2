<?php

declare(strict_types=1);

use App\Enums\PointEventType;
use App\Models\XpEarnRule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data migration so the new event type is admin-editable immediately on
     * deploy without waiting for a manual db:seed (XpEarnRuleController only
     * supports edit, not create — the row must exist for admin to manage it).
     * Mirrors XpEarnRuleSeeder's updateOrCreate-by-event_type pattern.
     */
    public function up(): void
    {
        XpEarnRule::query()->updateOrCreate(
            ['event_type' => PointEventType::CollaborationCompletionConfirmed->value],
            [
                'points' => PointEventType::CollaborationCompletionConfirmed->defaultPoints(),
                'label' => 'Confirm Kolab completion',
                'is_active' => true,
                'position' => 5,
            ],
        );
    }

    public function down(): void
    {
        XpEarnRule::query()
            ->where('event_type', PointEventType::CollaborationCompletionConfirmed->value)
            ->delete();
    }
};
