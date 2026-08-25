<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ChallengeAudience;
use App\Enums\ChallengeCategory;
use App\Enums\ChallengeDifficulty;
use App\Enums\ChallengeProofType;
use App\Models\Challenge;
use Illuminate\Database\Seeder;

/**
 * The challenge library communities pick from (kolabing-app#150).
 *
 * #150 built the shelf and left it empty. `challenges where is_system and
 * trigger_action is null` — the query that IS the library — returned **zero
 * rows** on the development database: the 49 seeded system challenges are all
 * trigger-driven missions, and the only peer-playable ones in existence were
 * scoped to a single event by the QA seeder. So a leader opening the curation
 * screen was told "there are no challenges to choose from", and a community that
 * had curated nothing fell back to a library of nothing.
 *
 * These are the content half of that feature. They are deliberately about
 * MEETING PEOPLE rather than about the app: the product model's goal for this
 * whole system is that it makes people socialise, and a challenge you can
 * complete without talking to anyone is a challenge that does not serve it.
 *
 * `proof_type` is set per challenge, not uniformly: "take a selfie" wants the
 * camera to open (kolabing-v2#217), and "find something you both..." is a
 * conversation that a photo would only interrupt.
 *
 * Idempotent on `slug`, so re-running is safe and editing a row here updates it.
 */
class PeerChallengeLibrarySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->library() as $row) {
            Challenge::query()->updateOrCreate(
                ['slug' => $row['slug']],
                $row + [
                    'is_system' => true,
                    'audience' => ChallengeAudience::Attendee,
                    // The thing that makes it a library challenge rather than a
                    // mission: nothing triggers it, a person does.
                    'trigger_action' => null,
                    'target_value' => 1,
                    'event_id' => null,
                    'app_visible' => true,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function library(): array
    {
        return [
            [
                'slug' => 'peer-selfie-together',
                'name' => 'Take a selfie together',
                'description' => 'Get both of you in one photo. Anywhere at the event.',
                'category' => ChallengeCategory::IceBreaker,
                'difficulty' => ChallengeDifficulty::Easy,
                'points' => 5,
                'proof_type' => ChallengeProofType::Photo,
            ],
            [
                'slug' => 'peer-swap-names-and-why',
                'name' => 'Names, and why you came',
                'description' => 'Tell each other your name and what brought you here tonight.',
                'category' => ChallengeCategory::IceBreaker,
                'difficulty' => ChallengeDifficulty::Easy,
                'points' => 5,
                'proof_type' => ChallengeProofType::Text,
            ],
            [
                'slug' => 'peer-find-something-in-common',
                'name' => 'Find something you both do',
                'description' => 'Keep going until you find something you have both done. Not work.',
                'category' => ChallengeCategory::IceBreaker,
                'difficulty' => ChallengeDifficulty::Easy,
                'points' => 10,
                'proof_type' => ChallengeProofType::Text,
            ],
            [
                'slug' => 'peer-introduce-to-someone-new',
                'name' => 'Introduce each other to someone new',
                'description' => 'Find a third person neither of you has met and introduce each other.',
                'category' => ChallengeCategory::Social,
                'difficulty' => ChallengeDifficulty::Medium,
                'points' => 15,
                'proof_type' => ChallengeProofType::Text,
            ],
            [
                'slug' => 'peer-local-recommendation',
                'name' => 'Swap a recommendation',
                'description' => 'Each name one place in the city the other should go. Somewhere you actually go.',
                'category' => ChallengeCategory::BarcelonaVibe,
                'difficulty' => ChallengeDifficulty::Easy,
                'points' => 10,
                'proof_type' => ChallengeProofType::Text,
            ],
            [
                'slug' => 'peer-teach-a-word',
                'name' => 'Teach each other a word',
                'description' => 'A word in a language the other does not speak. Make them say it back.',
                'category' => ChallengeCategory::CulturalExchange,
                'difficulty' => ChallengeDifficulty::Easy,
                'points' => 10,
                'proof_type' => ChallengeProofType::Text,
            ],
            [
                'slug' => 'peer-photo-of-the-event',
                'name' => 'One photo, together, of what is happening',
                'description' => 'Not a selfie. Get the event in the frame and both of you in it.',
                'category' => ChallengeCategory::CreativeFun,
                'difficulty' => ChallengeDifficulty::Medium,
                'points' => 15,
                'proof_type' => ChallengeProofType::Photo,
            ],
            [
                'slug' => 'peer-finish-together',
                'name' => 'Finish together',
                'description' => 'Whatever this event is — the route, the class, the game — finish it side by side.',
                'category' => ChallengeCategory::Engagement,
                'difficulty' => ChallengeDifficulty::Hard,
                'points' => 30,
                'proof_type' => ChallengeProofType::Photo,
            ],
        ];
    }
}
