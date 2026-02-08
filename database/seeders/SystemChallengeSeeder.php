<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ChallengeCategory;
use App\Enums\ChallengeDifficulty;
use App\Models\Challenge;
use Illuminate\Database\Seeder;

class SystemChallengeSeeder extends Seeder
{
    /**
     * Seed system challenges across all categories.
     */
    public function run(): void
    {
        $challenges = [
            /*
            |------------------------------------------------------------------
            | ICE BREAKER & SOCIAL
            |------------------------------------------------------------------
            */

            // Easy
            [
                'name' => 'Give each other a genuine compliment',
                'description' => 'Say something nice about each other — be sincere!',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::IceBreaker,
            ],
            [
                'name' => 'Share your favorite travel story in 30 seconds',
                'description' => 'Pick your best travel moment and tell it in half a minute.',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::IceBreaker,
            ],
            [
                'name' => 'Show each other a photo from your hometown',
                'description' => 'Open your camera roll and share a glimpse of where you come from.',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::IceBreaker,
            ],
            [
                'name' => 'Exchange social media handles',
                'description' => 'Follow each other on social media.',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::IceBreaker,
            ],
            [
                'name' => 'Introduce yourself in a creative way',
                'description' => 'High-five and introduce yourself without saying your job title.',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::IceBreaker,
            ],

            // Medium
            [
                'name' => 'Find 3 things you have in common',
                'description' => 'Discover what you share — hobbies, places, taste in music…',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::IceBreaker,
            ],
            [
                'name' => 'Play Two Truths and a Lie',
                'description' => 'Each of you shares 3 statements — guess which one is the lie!',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::IceBreaker,
            ],
            [
                'name' => 'Describe your dream life in 10 years',
                'description' => 'Tell each other where you see yourself in a decade.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::IceBreaker,
            ],
            [
                'name' => 'Share the story behind your name',
                'description' => 'Tell each other why your parents chose your name or what it means.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::IceBreaker,
            ],
            [
                'name' => 'Have a 2-minute conversation without mentioning work',
                'description' => 'Talk about anything except your job for 2 full minutes.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::IceBreaker,
            ],

            // Hard
            [
                'name' => 'Have a 5-minute deep conversation phone-free',
                'description' => 'Put your phones away and talk about something meaningful for 5 minutes.',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::IceBreaker,
            ],
            [
                'name' => 'Tell your life story in exactly 60 seconds',
                'description' => 'Each of you has exactly one minute — summarize your whole life!',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::IceBreaker,
            ],

            /*
            |------------------------------------------------------------------
            | CULTURAL EXCHANGE
            |------------------------------------------------------------------
            */

            // Easy
            [
                'name' => 'Teach each other how to say cheers in your language',
                'description' => 'Learn how to toast in each other\'s native tongue. Cheers!',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::CulturalExchange,
            ],
            [
                'name' => 'Share a fun fact about your home country',
                'description' => 'Tell each other something surprising about where you\'re from.',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::CulturalExchange,
            ],
            [
                'name' => 'Greet each other in your native language',
                'description' => 'Say hello the way you do back home and teach the pronunciation.',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::CulturalExchange,
            ],
            [
                'name' => 'Show each other a traditional gesture from your culture',
                'description' => 'A hand sign, a bow, a cheek kiss — share how your culture greets.',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::CulturalExchange,
            ],

            // Medium
            [
                'name' => 'Teach each other 5 useful words in your language',
                'description' => 'Pick 5 everyday words and teach them with proper pronunciation.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::CulturalExchange,
            ],
            [
                'name' => 'Describe a traditional dish and make them crave it',
                'description' => 'Talk about a dish from your country so passionately they want to try it.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::CulturalExchange,
            ],
            [
                'name' => 'Teach each other a dance move from your culture',
                'description' => 'Show a traditional dance step and help each other learn it.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::CulturalExchange,
            ],
            [
                'name' => 'Compare a tradition from your cultures and find similarities',
                'description' => 'Pick a tradition (weddings, holidays, food) and see what you share.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::CulturalExchange,
            ],

            // Hard
            [
                'name' => 'Learn to count to 10 in each other\'s language',
                'description' => 'Teach and learn — you both must count to 10 correctly!',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::CulturalExchange,
            ],
            [
                'name' => 'Sing or hum a melody from your culture and explain it',
                'description' => 'Share a song that means something to you and tell the story behind it.',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::CulturalExchange,
            ],
            [
                'name' => 'Teach each other how to write your name in your native script',
                'description' => 'Arabic, Cyrillic, Chinese, Korean, Greek — show your alphabet!',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::CulturalExchange,
            ],
            [
                'name' => 'Explain a cultural holiday and answer 3 questions about it',
                'description' => 'Describe a special celebration from your country in detail.',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::CulturalExchange,
            ],

            /*
            |------------------------------------------------------------------
            | BARCELONA & SPAIN VIBE
            |------------------------------------------------------------------
            */

            // Easy
            [
                'name' => 'Say Salud and clink glasses together',
                'description' => 'The classic Spanish toast — clink your glasses and celebrate!',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],
            [
                'name' => 'Take a selfie together',
                'description' => 'Capture the moment with your new friend!',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],
            [
                'name' => 'Share your favorite Barcelona spot',
                'description' => 'Tell each other about a hidden gem or favorite place in the city.',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],
            [
                'name' => 'High-five and shout Vamos together',
                'description' => 'Channel your inner Spanish energy with a loud Vamos!',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],

            // Medium
            [
                'name' => 'Teach each other a Spanish phrase you learned recently',
                'description' => 'Share a useful phrase you picked up while in Spain.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],
            [
                'name' => 'Debate Barca or Real Madrid for 1 minute',
                'description' => 'Pick a side and defend it with passion! No wrong answers.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],
            [
                'name' => 'Plan a dream Barcelona tapas crawl together',
                'description' => 'Pick 3 tapas spots for the ultimate Barcelona food tour.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],
            [
                'name' => 'Strike your best flamenco pose and hold it for 5 seconds',
                'description' => 'Channel your inner flamenco dancer — dramatic hands required!',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],
            [
                'name' => 'Recommend one must-do experience before leaving Barcelona',
                'description' => 'Give each other your number one Barcelona recommendation.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],

            // Hard
            [
                'name' => 'Dance together on stage',
                'description' => 'Hit the stage and show your moves together!',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],
            [
                'name' => 'Sing a few lines of a Spanish song together',
                'description' => 'Macarena, Despacito, Bamboleo — pick one and perform it!',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],
            [
                'name' => 'Create a 30-second Barcelona travel vlog together',
                'description' => 'Film a mini travel vlog like you\'re professional content creators.',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::BarcelonaVibe,
            ],

            /*
            |------------------------------------------------------------------
            | CREATIVE & FUN
            |------------------------------------------------------------------
            */

            // Easy
            [
                'name' => 'Make each other laugh in under 30 seconds',
                'description' => 'You have 30 seconds — joke, face, story — whatever it takes!',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::CreativeFun,
            ],
            [
                'name' => 'Do a funny pose and take a photo together',
                'description' => 'Get creative with your pose — the sillier, the better!',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::CreativeFun,
            ],
            [
                'name' => 'Do your best impression of each other',
                'description' => 'Observe and imitate — laughter guaranteed!',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::CreativeFun,
            ],
            [
                'name' => 'Invent a unique handshake together',
                'description' => 'Create a custom handshake that\'s yours alone.',
                'difficulty' => ChallengeDifficulty::Easy,
                'category' => ChallengeCategory::CreativeFun,
            ],

            // Medium
            [
                'name' => 'Create a 15-second Reel together',
                'description' => 'Film a quick Reel or TikTok — make it entertaining!',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::CreativeFun,
            ],
            [
                'name' => 'Draw a portrait of each other in 60 seconds',
                'description' => 'Grab a napkin and pen — speed-sketch each other\'s face!',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::CreativeFun,
            ],
            [
                'name' => 'Invent a new cocktail and name it',
                'description' => 'Create a cocktail concept — name, ingredients, and vibe.',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::CreativeFun,
            ],
            [
                'name' => 'Create a secret handshake with at least 5 moves',
                'description' => 'Design an elaborate handshake — fist bumps, snaps, the works!',
                'difficulty' => ChallengeDifficulty::Medium,
                'category' => ChallengeCategory::CreativeFun,
            ],

            // Hard
            [
                'name' => 'Perform a 30-second improv scene about meeting in Barcelona',
                'description' => 'Act out a dramatic scene of how you "first met" in Barcelona.',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::CreativeFun,
            ],
            [
                'name' => 'Do a 1-minute dance battle',
                'description' => 'Face off in a friendly dance battle — crowd picks the winner!',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::CreativeFun,
            ],
            [
                'name' => 'Freestyle rap or sing about the event together',
                'description' => 'Make up lyrics about tonight\'s event and perform them!',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::CreativeFun,
            ],
            [
                'name' => 'Create and perform a mini comedy sketch',
                'description' => 'Write a 30-second comedy sketch and act it out together.',
                'difficulty' => ChallengeDifficulty::Hard,
                'category' => ChallengeCategory::CreativeFun,
            ],
        ];

        foreach ($challenges as $challengeData) {
            Challenge::query()->updateOrCreate(
                ['name' => $challengeData['name'], 'is_system' => true],
                [
                    'description' => $challengeData['description'],
                    'difficulty' => $challengeData['difficulty'],
                    'points' => $challengeData['difficulty']->points(),
                    'is_system' => true,
                    'category' => $challengeData['category'],
                    'event_id' => null,
                ]
            );
        }
    }
}
