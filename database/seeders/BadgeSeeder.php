<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BadgeMilestoneType;
use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Seed system badges.
     */
    public function run(): void
    {
        $badges = [
            [
                'milestone_type' => BadgeMilestoneType::FirstCheckin,
                'name' => 'Ilk Adim',
                'description' => 'Ilk etkinlige check-in yap',
                'icon' => 'badge-first-checkin',
                'milestone_value' => 1,
            ],
            [
                'milestone_type' => BadgeMilestoneType::FirstChallenge,
                'name' => 'Challenge Baslangic',
                'description' => 'Ilk challenge\'ini tamamla',
                'icon' => 'badge-first-challenge',
                'milestone_value' => 1,
            ],
            [
                'milestone_type' => BadgeMilestoneType::SocialButterfly,
                'name' => 'Sosyal Kelebek',
                'description' => '10 farkli kisiyle challenge yap',
                'icon' => 'badge-social-butterfly',
                'milestone_value' => 10,
            ],
            [
                'milestone_type' => BadgeMilestoneType::ChallengeMaster,
                'name' => 'Challenge Master',
                'description' => '50 challenge tamamla',
                'icon' => 'badge-challenge-master',
                'milestone_value' => 50,
            ],
            [
                'milestone_type' => BadgeMilestoneType::EventGuru,
                'name' => 'Etkinlik Gurusu',
                'description' => '10 etkinlige katil',
                'icon' => 'badge-event-guru',
                'milestone_value' => 10,
            ],
            [
                'milestone_type' => BadgeMilestoneType::PointHunter,
                'name' => 'Puan Avcisi',
                'description' => '500 toplam puan kazan',
                'icon' => 'badge-point-hunter',
                'milestone_value' => 500,
            ],
            [
                'milestone_type' => BadgeMilestoneType::Legend,
                'name' => 'Efsane',
                'description' => '2000 toplam puan kazan',
                'icon' => 'badge-legend',
                'milestone_value' => 2000,
            ],
            [
                'milestone_type' => BadgeMilestoneType::RewardCollector,
                'name' => 'Odul Koleksiyoncusu',
                'description' => '10 odul kazan',
                'icon' => 'badge-reward-collector',
                'milestone_value' => 10,
            ],
            [
                'milestone_type' => BadgeMilestoneType::LoyalAttendee,
                'name' => 'Sadik Katilimci',
                'description' => '5 etkinlige katil',
                'icon' => 'badge-loyal-attendee',
                'milestone_value' => 5,
            ],
        ];

        foreach ($badges as $badgeData) {
            Badge::query()->updateOrCreate(
                ['milestone_type' => $badgeData['milestone_type']],
                $badgeData
            );
        }
    }
}
