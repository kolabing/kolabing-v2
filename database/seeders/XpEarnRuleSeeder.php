<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PointEventType;
use App\Models\XpEarnRule;
use Illuminate\Database\Seeder;

/**
 * Seed one row per PointEventType case using the enum's defaultPoints() value
 * and a human-readable label. Idempotent: updateOrCreate by event_type.
 */
class XpEarnRuleSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            PointEventType::CollaborationComplete->value => ['label' => 'Complete a Kolab', 'position' => 10],
            PointEventType::FirstKolabBonus->value => ['label' => 'First Kolab bonus', 'position' => 20],
            PointEventType::ReviewPosted->value => ['label' => 'Post a review', 'position' => 30],
            PointEventType::UgcPosted->value => ['label' => 'Share content (UGC)', 'position' => 40],
            PointEventType::ReferralConversion->value => ['label' => 'Refer a partner', 'position' => 50],
            PointEventType::Withdrawal->value => ['label' => 'Withdrawal (deduction)', 'position' => 60],
        ];

        foreach ($defs as $eventType => $meta) {
            XpEarnRule::query()->updateOrCreate(
                ['event_type' => $eventType],
                [
                    'points' => PointEventType::from($eventType)->defaultPoints(),
                    'label' => $meta['label'],
                    'is_active' => true,
                    'position' => $meta['position'],
                ],
            );
        }
    }
}
