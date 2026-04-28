<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OfferStatus;
use App\Enums\UserType;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\City;
use App\Models\CollabOpportunity;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealisticDataSeeder extends Seeder
{
    private const QA_PASSWORD = 'password123';

    /**
     * Global counters for unique picsum seeds.
     */
    private int $eventPhotoCounter = 1;

    private int $galleryPhotoCounter = 1;

    private int $offerPhotoCounter = 1;

    /**
     * Seed realistic demo data for the Kolabing platform.
     */
    public function run(): void
    {
        $cities = $this->resolveCities();

        $businessProfiles = $this->seedBusinessProfiles($cities);
        $communityProfiles = $this->seedCommunityProfiles($cities);

        $allProfiles = array_merge($businessProfiles, $communityProfiles);

        $this->seedPastEvents($allProfiles, $businessProfiles, $communityProfiles);
        $this->seedGalleryPhotos($allProfiles);
        $this->seedBusinessOpportunities($businessProfiles, $cities);
        $this->seedCommunityOpportunities($communityProfiles, $cities);

        $this->command->info('Realistic data seeded:');
        $this->command->info('  Profiles: '.Profile::query()->count());
        $this->command->info('  Events: '.Event::query()->count());
        $this->command->info('  Event Photos: '.EventPhoto::query()->count());
        $this->command->info('  Gallery Photos: '.ProfileGalleryPhoto::query()->count());
        $this->command->info('  Opportunities: '.CollabOpportunity::query()->count());
    }

    /**
     * Resolve city IDs by name.
     *
     * @return array<string, string>
     */
    private function resolveCities(): array
    {
        $cityNames = ['Sevilla', 'Malaga', 'Cadiz', 'Granada', 'Barcelona', 'Madrid', 'Valencia', 'Cordoba'];
        $cities = [];

        foreach ($cityNames as $name) {
            $city = City::query()->where('name', $name)->first();
            if ($city) {
                $cities[$name] = $city->id;
            }
        }

        return $cities;
    }

    /**
     * Seed 8 realistic business profiles.
     *
     * @param  array<string, string>  $cities
     * @return array<int, array{profile: Profile, business: BusinessProfile, data: array<string, mixed>}>
     */
    private function seedBusinessProfiles(array $cities): array
    {
        $businesses = [
            [
                'name' => 'Cafe Botanico',
                'email' => 'hola@cafebotanico.es',
                'business_type' => 'cafeteria',
                'city' => 'Sevilla',
                'about' => 'Specialty coffee and brunch spot in the heart of Sevilla. We source single-origin beans from local roasters and pair them with seasonal dishes made from Andalucian produce.',
                'instagram' => '@cafebotanico',
                'website' => 'https://cafebotanico.es',
            ],
            [
                'name' => 'Gimnasio FitZone',
                'email' => 'info@fitzone-malaga.com',
                'business_type' => 'gimnasio',
                'city' => 'Malaga',
                'about' => 'Premium fitness center on the Costa del Sol with state-of-the-art equipment, group classes, and personal training. Open 6am to midnight, seven days a week.',
                'instagram' => '@fitzonemalaga',
                'website' => 'https://fitzone-malaga.com',
            ],
            [
                'name' => 'La Taberna del Puerto',
                'email' => 'reservas@tabernadeelpuerto.es',
                'business_type' => 'restaurante',
                'city' => 'Cadiz',
                'about' => 'Traditional Andalucian seafood tavern overlooking the port of Cadiz. Fresh catch every morning, paired with local sherry wines and a sunset terrace that seats 80.',
                'instagram' => '@tabernadeelpuerto',
                'website' => 'https://tabernadeelpuerto.es',
            ],
            [
                'name' => 'Hotel Casa Flamenca',
                'email' => 'recepcion@casaflamenca.com',
                'business_type' => 'hotel',
                'city' => 'Granada',
                'about' => 'Boutique hotel in the Albaicin quarter with views of the Alhambra. 22 individually designed rooms, a rooftop bar, and weekly flamenco performances for guests.',
                'instagram' => '@casaflamenca',
                'website' => 'https://casaflamenca.com',
            ],
            [
                'name' => 'SportLife Sevilla',
                'email' => 'tienda@sportlifesevilla.es',
                'business_type' => 'tienda-de-deportes',
                'city' => 'Sevilla',
                'about' => 'Your go-to sports shop in Sevilla for running, cycling, and outdoor gear. We sponsor local run clubs and organize monthly trail running events in Sierra Norte.',
                'instagram' => '@sportlifesevilla',
                'website' => 'https://sportlifesevilla.es',
            ],
            [
                'name' => 'Cowork Hub Barcelona',
                'email' => 'hello@coworkhub-bcn.com',
                'business_type' => 'coworking',
                'city' => 'Barcelona',
                'about' => 'Modern coworking space in El Born with 120 desks, private offices, and event spaces. We host weekly networking events, tech talks, and startup pitch nights.',
                'instagram' => '@coworkhub_bcn',
                'website' => 'https://coworkhub-bcn.com',
            ],
            [
                'name' => 'Restaurante Mar y Tierra',
                'email' => 'mesa@marytierra.es',
                'business_type' => 'restaurante',
                'city' => 'Malaga',
                'about' => 'Contemporary Andalucian cuisine where the mountains meet the sea. Chef Lucia Romero serves tasting menus that celebrate the best of Malaga province, from Ronda to the coast.',
                'instagram' => '@marytierramalaga',
                'website' => 'https://marytierra.es',
            ],
            [
                'name' => 'Salon de Belleza Luna',
                'email' => 'citas@salonluna.es',
                'business_type' => 'centro-de-belleza',
                'city' => 'Sevilla',
                'about' => 'Award-winning beauty salon in Triana offering hair styling, makeup, skincare treatments, and bridal packages. Trusted by Sevilla influencers since 2019.',
                'instagram' => '@salonlunasevilla',
                'website' => 'https://salonluna.es',
            ],
        ];

        $results = [];

        foreach ($businesses as $biz) {
            $profile = Profile::query()->create([
                'email' => $biz['email'],
                'password' => self::QA_PASSWORD,
                'user_type' => UserType::Business,
                'google_id' => 'google_'.Str::random(21),
                'avatar_url' => 'https://picsum.photos/seed/profile-'.Str::slug($biz['name']).'/400/400',
                'email_verified_at' => now(),
            ]);

            $businessProfile = BusinessProfile::query()->create([
                'profile_id' => $profile->id,
                'name' => $biz['name'],
                'about' => $biz['about'],
                'business_type' => $biz['business_type'],
                'categories' => [$biz['business_type']],
                'city_id' => $cities[$biz['city']] ?? null,
                'instagram' => $biz['instagram'],
                'website' => $biz['website'],
                'profile_photo' => 'https://picsum.photos/seed/biz-'.Str::slug($biz['name']).'/400/400',
            ]);

            BusinessSubscription::factory()->create([
                'profile_id' => $profile->id,
            ]);

            $results[] = [
                'profile' => $profile,
                'business' => $businessProfile,
                'data' => $biz,
            ];
        }

        return $results;
    }

    /**
     * Seed 8 realistic community profiles.
     *
     * @param  array<string, string>  $cities
     * @return array<int, array{profile: Profile, community: CommunityProfile, data: array<string, mixed>}>
     */
    private function seedCommunityProfiles(array $cities): array
    {
        $communities = [
            [
                'name' => 'Sevilla Run Club',
                'email' => 'hola@sevillarunclub.com',
                'community_type' => 'run-club',
                'city' => 'Sevilla',
                'about' => 'Weekly running group exploring Sevilla one kilometer at a time. Tuesday 7pm intervals at Parque Maria Luisa, Saturday 9am long runs along the Guadalquivir. All paces welcome.',
                'instagram' => '@sevillarunclub',
                'tiktok' => '@sevillarunclub',
                'website' => 'https://sevillarunclub.com',
                'is_featured' => true,
            ],
            [
                'name' => 'Malaga Fitness Tribe',
                'email' => 'info@malagafitnesstribe.com',
                'community_type' => 'fitness-community',
                'city' => 'Malaga',
                'about' => 'Outdoor fitness community bringing the Costa del Sol together through beach workouts, HIIT sessions, and functional training. 300+ members strong and growing every week.',
                'instagram' => '@malagafitnesstribe',
                'tiktok' => '@malagafitness',
                'website' => 'https://malagafitnesstribe.com',
                'is_featured' => true,
            ],
            [
                'name' => 'Yoga y Bienestar Sevilla',
                'email' => 'namaste@yogasevilla.es',
                'community_type' => 'wellness-community',
                'city' => 'Sevilla',
                'about' => 'Mindful movement and wellness community hosting outdoor yoga sessions, breathwork circles, and meditation meetups at iconic Sevillano locations. Inner peace, al aire libre.',
                'instagram' => '@yogabienestar_sevilla',
                'tiktok' => '@yogasevilla',
                'website' => null,
                'is_featured' => false,
            ],
            [
                'name' => 'Tech Meetup BCN',
                'email' => 'organizers@techmeetupbcn.dev',
                'community_type' => 'tech-startup-community',
                'city' => 'Barcelona',
                'about' => 'Barcelona\'s largest tech and startup community. Monthly talks on AI, product design, and growth. We connect founders, developers, and investors over craft beer and code.',
                'instagram' => '@techmeetupbcn',
                'tiktok' => null,
                'website' => 'https://techmeetupbcn.dev',
                'is_featured' => true,
            ],
            [
                'name' => 'Foodies Andalucia',
                'email' => 'hola@foodiesandalucia.es',
                'community_type' => 'food-community',
                'city' => 'Sevilla',
                'about' => 'We eat our way through Andalucia so you don\'t have to guess. Tapas tours, market visits, cooking workshops, and restaurant pop-ups. 500+ food lovers and counting.',
                'instagram' => '@foodiesandalucia',
                'tiktok' => '@foodiesandalucia',
                'website' => 'https://foodiesandalucia.es',
                'is_featured' => false,
            ],
            [
                'name' => 'Sevilla Dance Collective',
                'email' => 'baila@sevilladance.es',
                'community_type' => 'dance-community',
                'city' => 'Sevilla',
                'about' => 'From flamenco to bachata, hip-hop to contemporary. We unite dancers of all styles for workshops, jams, and public performances across Sevilla. Come move with us.',
                'instagram' => '@sevilladance',
                'tiktok' => '@sevilladancecollective',
                'website' => null,
                'is_featured' => false,
            ],
            [
                'name' => 'Malaga Photo Walk',
                'email' => 'shoot@malagaphotowalk.com',
                'community_type' => 'photography-community',
                'city' => 'Malaga',
                'about' => 'Monthly photography walks through Malaga\'s streets, markets, and coastline. Beginners to pros, phone or DSLR, everyone is welcome. We see the city through a lens.',
                'instagram' => '@malagaphotowalk',
                'tiktok' => '@malagaphotowalk',
                'website' => 'https://malagaphotowalk.com',
                'is_featured' => false,
            ],
            [
                'name' => 'GreenLife Community',
                'email' => 'hola@greenlifegranada.org',
                'community_type' => 'sustainability-community',
                'city' => 'Granada',
                'about' => 'Sustainability-focused community in Granada organizing beach clean-ups, zero-waste workshops, urban gardening projects, and eco-conscious networking events. Small actions, big impact.',
                'instagram' => '@greenlifegranada',
                'tiktok' => '@greenlifegranada',
                'website' => 'https://greenlifegranada.org',
                'is_featured' => true,
            ],
        ];

        $results = [];

        foreach ($communities as $comm) {
            $profile = Profile::query()->create([
                'email' => $comm['email'],
                'password' => self::QA_PASSWORD,
                'user_type' => UserType::Community,
                'google_id' => 'google_'.Str::random(21),
                'avatar_url' => 'https://picsum.photos/seed/profile-'.Str::slug($comm['name']).'/400/400',
                'email_verified_at' => now(),
            ]);

            $communityProfile = CommunityProfile::query()->create([
                'profile_id' => $profile->id,
                'name' => $comm['name'],
                'about' => $comm['about'],
                'community_type' => $comm['community_type'],
                'city_id' => $cities[$comm['city']] ?? null,
                'instagram' => $comm['instagram'],
                'tiktok' => $comm['tiktok'],
                'website' => $comm['website'],
                'profile_photo' => 'https://picsum.photos/seed/comm-'.Str::slug($comm['name']).'/400/400',
                'is_featured' => $comm['is_featured'],
            ]);

            $results[] = [
                'profile' => $profile,
                'community' => $communityProfile,
                'data' => $comm,
            ];
        }

        return $results;
    }

    /**
     * Seed past events for all profiles.
     *
     * @param  array<int, array<string, mixed>>  $allProfiles
     * @param  array<int, array<string, mixed>>  $businessProfiles
     * @param  array<int, array<string, mixed>>  $communityProfiles
     */
    private function seedPastEvents(array $allProfiles, array $businessProfiles, array $communityProfiles): void
    {
        $businessEventNames = [
            'Wine & Tapas Night',
            'Grand Opening Weekend',
            'Summer Terrace Party',
            'Chef\'s Table Experience',
            'Brunch Launch Event',
            'Happy Hour Networking',
            'Live Music Friday',
            'Seasonal Menu Tasting',
            'Christmas Market Pop-Up',
            'Valentine\'s Dinner Special',
            'Local Producer Showcase',
            'Anniversary Celebration',
        ];

        $communityEventNames = [
            'Morning Run at Parque Maria Luisa',
            'HIIT in the Park - Spring Edition',
            'Sunset Yoga Session at Plaza de Espana',
            'Tech Talk: AI in Spain',
            'Tapas Tour Through Triana',
            'Golden Hour Photo Walk',
            'Beach Cleanup & Brunch',
            'Saturday Long Run - River Route',
            'Dance Jam at Alameda',
            'Monthly Meetup & Networking',
            'Outdoor Bootcamp by the Sea',
            'Community Potluck Dinner',
            'Flamenco Fusion Workshop',
            'Sunrise Meditation by the River',
            'Street Photography Challenge',
            'Urban Garden Workshop',
        ];

        foreach ($allProfiles as $profileData) {
            /** @var Profile $profile */
            $profile = $profileData['profile'];
            $isBusiness = $profile->user_type === UserType::Business;

            $eventCount = rand(2, 4);
            $namePool = $isBusiness ? $businessEventNames : $communityEventNames;

            $chosenNames = (array) array_rand(array_flip($namePool), min($eventCount, count($namePool)));

            for ($i = 0; $i < $eventCount; $i++) {
                $eventName = $chosenNames[$i] ?? $namePool[array_rand($namePool)];

                $partnerName = $isBusiness
                    ? $communityProfiles[array_rand($communityProfiles)]['data']['name']
                    : $businessProfiles[array_rand($businessProfiles)]['data']['name'];

                $partnerType = $isBusiness ? 'community' : 'business';

                $event = Event::query()->create([
                    'profile_id' => $profile->id,
                    'name' => $eventName,
                    'partner_name' => $partnerName,
                    'partner_type' => $partnerType,
                    'event_date' => now()->subDays(rand(14, 180))->toDateString(),
                    'attendee_count' => rand(15, 200),
                    'location_lat' => $this->randomLatForSpain(),
                    'location_lng' => $this->randomLngForSpain(),
                    'address' => $this->randomSpanishAddress(),
                    'is_active' => false,
                ]);

                $photoCount = rand(2, 4);
                for ($j = 0; $j < $photoCount; $j++) {
                    EventPhoto::query()->create([
                        'event_id' => $event->id,
                        'url' => 'https://picsum.photos/seed/event-'.$this->eventPhotoCounter.'/800/600',
                        'thumbnail_url' => 'https://picsum.photos/seed/event-'.$this->eventPhotoCounter.'/400/300',
                        'sort_order' => $j,
                    ]);
                    $this->eventPhotoCounter++;
                }
            }
        }
    }

    /**
     * Seed gallery photos for all profiles.
     *
     * @param  array<int, array<string, mixed>>  $allProfiles
     */
    private function seedGalleryPhotos(array $allProfiles): void
    {
        $businessCaptions = [
            'Our beautiful terrace at golden hour',
            'Behind the scenes in the kitchen',
            'Happy customers enjoying the weekend',
            'New seasonal menu launch',
            'The team that makes it happen',
            'Interior design details we love',
            'Morning prep ritual',
            'Award ceremony night',
        ];

        $communityCaptions = [
            'Group photo after our last event',
            'Action shot from Saturday\'s session',
            'Our community keeps growing',
            'Celebrating 1 year together',
            'The view from our favorite spot',
            'New members welcome day',
            'Post-workout vibes',
            'Event setup behind the scenes',
        ];

        foreach ($allProfiles as $profileData) {
            /** @var Profile $profile */
            $profile = $profileData['profile'];
            $isBusiness = $profile->user_type === UserType::Business;
            $captions = $isBusiness ? $businessCaptions : $communityCaptions;

            $photoCount = rand(2, 5);
            $chosenCaptions = array_slice($captions, 0, $photoCount);
            shuffle($chosenCaptions);

            for ($i = 0; $i < $photoCount; $i++) {
                ProfileGalleryPhoto::query()->create([
                    'profile_id' => $profile->id,
                    'url' => 'https://picsum.photos/seed/gallery-'.$this->galleryPhotoCounter.'/800/600',
                    'caption' => $chosenCaptions[$i] ?? $captions[array_rand($captions)],
                    'sort_order' => $i,
                ]);
                $this->galleryPhotoCounter++;
            }
        }
    }

    /**
     * Seed 3 published opportunities per business profile.
     *
     * @param  array<int, array<string, mixed>>  $businessProfiles
     * @param  array<string, string>  $cities
     */
    private function seedBusinessOpportunities(array $businessProfiles, array $cities): void
    {
        $opportunitySets = [
            // Cafe Botanico
            [
                [
                    'title' => 'Launch Party - New Brunch Menu',
                    'description' => 'We are launching our new seasonal brunch menu and looking for a community to co-host the event. Free brunch for your members plus exclusive discount codes to share with your audience. Perfect for food, wellness, or lifestyle communities.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'discount' => ['enabled' => true, 'percentage' => 15]],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 30],
                    'categories' => ['Food & Drink', 'Lifestyle'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Sevilla',
                ],
                [
                    'title' => 'Weekly Coffee & Cowork Mornings',
                    'description' => 'Host your community meetup at Cafe Botanico every Wednesday morning. We provide complimentary coffee and pastries for up to 20 people. Ideal for professional networking or creative communities.',
                    'business_offer' => ['venue' => true, 'food_drink' => true],
                    'community_deliverables' => ['instagram_story' => true, 'attendee_count' => 20, 'recurring_attendance' => true],
                    'categories' => ['Networking', 'Food & Drink'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Sevilla',
                ],
                [
                    'title' => 'Latte Art Workshop for Communities',
                    'description' => 'Our baristas will teach latte art to your group in a private 90-minute session. Great content opportunity with photogenic drinks and a fun, hands-on experience for your members.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'experience' => 'Latte art workshop'],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'tiktok' => true, 'attendee_count' => 15],
                    'categories' => ['Food & Drink', 'Lifestyle', 'Art & Creative'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Sevilla',
                ],
            ],
            // Gimnasio FitZone
            [
                [
                    'title' => 'Fitness Challenge Weekend at FitZone',
                    'description' => 'Open our doors for a full weekend fitness challenge. Your community gets free gym access, branded team t-shirts, and prizes for the top 3 finishers. We handle everything inside the gym.',
                    'business_offer' => ['venue' => true, 'free_access' => true, 'merchandise' => 'Branded team t-shirts', 'prizes' => true],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 50, 'video_content' => true],
                    'categories' => ['Sports', 'Fitness', 'Wellness'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Malaga',
                ],
                [
                    'title' => 'Free Trial Month for Community Members',
                    'description' => 'We are offering 30 free monthly memberships for your community members. In exchange, we ask for authentic content showing their FitZone experience on social media.',
                    'business_offer' => ['free_access' => true, 'duration' => '1 month'],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 30, 'user_generated_content' => true],
                    'categories' => ['Fitness', 'Wellness'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Malaga',
                ],
                [
                    'title' => 'Outdoor HIIT Session on the Beach',
                    'description' => 'Our trainers will lead a branded outdoor HIIT session on Malagueta beach for your community. Equipment, hydration, and a post-workout smoothie included.',
                    'business_offer' => ['venue' => false, 'food_drink' => true, 'experience' => 'Trainer-led HIIT session', 'equipment' => true],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 40, 'tiktok' => true],
                    'categories' => ['Fitness', 'Sports', 'Wellness'],
                    'venue_mode' => 'outdoor',
                    'preferred_city' => 'Malaga',
                ],
            ],
            // La Taberna del Puerto
            [
                [
                    'title' => 'Sunset Tapas & Wine on the Terrace',
                    'description' => 'Host your community gathering on our oceanfront terrace with a curated tapas menu and local wine selection. Capacity for 40 guests with dedicated service staff.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'discount' => ['enabled' => true, 'percentage' => 20]],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 40],
                    'categories' => ['Food & Drink', 'Lifestyle'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Cadiz',
                ],
                [
                    'title' => 'Seafood Cooking Class Experience',
                    'description' => 'Our head chef opens the kitchen for a hands-on seafood cooking class. Your community learns to prepare three traditional Gaditano dishes. All ingredients and aprons included.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'experience' => 'Cooking class with head chef'],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'tiktok' => true, 'attendee_count' => 16],
                    'categories' => ['Food & Drink', 'Art & Creative'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Cadiz',
                ],
                [
                    'title' => 'Private Wine Tasting Evening',
                    'description' => 'Exclusive sherry and wine tasting with our sommelier for your community. Five local wines paired with small bites, plus a 10% discount card for future visits.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'discount' => ['enabled' => true, 'percentage' => 10]],
                    'community_deliverables' => ['instagram_story' => true, 'attendee_count' => 25, 'review' => true],
                    'categories' => ['Food & Drink', 'Lifestyle', 'Networking'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Cadiz',
                ],
            ],
            // Hotel Casa Flamenca
            [
                [
                    'title' => 'Rooftop Networking with Alhambra Views',
                    'description' => 'Our rooftop bar is the perfect backdrop for your community event. We provide the venue, welcome drinks, and a DJ for sunset networking with panoramic views of the Alhambra.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'entertainment' => 'DJ set'],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 60, 'event_coverage' => true],
                    'categories' => ['Networking', 'Lifestyle', 'Music'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Granada',
                ],
                [
                    'title' => 'Flamenco Night - Exclusive Community Access',
                    'description' => 'Bring your community to our intimate flamenco show, normally reserved for hotel guests. Welcome cocktail, 45-minute performance, and tapas included.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'experience' => 'Flamenco show'],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 30],
                    'categories' => ['Art & Creative', 'Lifestyle', 'Music'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Granada',
                ],
                [
                    'title' => 'Weekend Retreat Package for Communities',
                    'description' => 'Two-night stay for up to 10 community leaders at a discounted rate. Includes breakfast, spa access, and a meeting room. Perfect for planning retreats or content creation weekends.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'accommodation' => true, 'discount' => ['enabled' => true, 'percentage' => 30]],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'tiktok' => true, 'blog_post' => true, 'attendee_count' => 10],
                    'categories' => ['Wellness', 'Lifestyle', 'Travel'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Granada',
                ],
            ],
            // SportLife Sevilla
            [
                [
                    'title' => 'Run Club x SportLife Gear Testing',
                    'description' => 'We provide the latest running shoes and gear for your community to test during a group run. Keep-what-you-love deals afterwards, plus branded water bottles for all participants.',
                    'business_offer' => ['free_access' => true, 'merchandise' => 'Running gear testing + water bottles', 'discount' => ['enabled' => true, 'percentage' => 25]],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 35, 'user_generated_content' => true],
                    'categories' => ['Sports', 'Fitness'],
                    'venue_mode' => 'outdoor',
                    'preferred_city' => 'Sevilla',
                ],
                [
                    'title' => 'Trail Running Event Sponsorship',
                    'description' => 'Full sponsorship for your community trail run in Sierra Norte. We supply hydration packs, energy gels, and finisher medals. Your event, our backing.',
                    'business_offer' => ['merchandise' => 'Hydration packs, gels, medals', 'sponsorship' => true],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 50, 'logo_placement' => true, 'video_content' => true],
                    'categories' => ['Sports', 'Fitness', 'Nature'],
                    'venue_mode' => 'outdoor',
                    'preferred_city' => 'Sevilla',
                ],
                [
                    'title' => 'In-Store Community Night',
                    'description' => 'After-hours access to our store for your community. Expert fitting advice, exclusive discounts, snacks, and a fun atmosphere. Limited to 30 guests per session.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'discount' => ['enabled' => true, 'percentage' => 20]],
                    'community_deliverables' => ['instagram_story' => true, 'attendee_count' => 30],
                    'categories' => ['Sports', 'Networking'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Sevilla',
                ],
            ],
            // Cowork Hub Barcelona
            [
                [
                    'title' => 'Free Event Space for Tech Communities',
                    'description' => 'We open our 80-person event space for free to tech and startup communities hosting talks, hackathons, or workshops. Projector, sound, Wi-Fi, and coffee included.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'equipment' => true],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 80, 'event_coverage' => true],
                    'categories' => ['Tech', 'Networking', 'Education'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Barcelona',
                ],
                [
                    'title' => 'Startup Pitch Night - Monthly Series',
                    'description' => 'Co-host our monthly pitch night. Five startups pitch, your community votes. We provide the space, drinks, and a panel of investors. You bring the audience and energy.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'network' => 'Investor panel access'],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 60, 'recurring_attendance' => true],
                    'categories' => ['Tech', 'Networking'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Barcelona',
                ],
                [
                    'title' => 'Co-Working Day Pass for Community Members',
                    'description' => 'Offer your community members a free day pass to Cowork Hub. Great perk for remote workers. We get visibility, your members get a premium workspace.',
                    'business_offer' => ['free_access' => true, 'food_drink' => true],
                    'community_deliverables' => ['instagram_story' => true, 'attendee_count' => 20, 'user_generated_content' => true],
                    'categories' => ['Networking', 'Tech', 'Lifestyle'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Barcelona',
                ],
            ],
            // Restaurante Mar y Tierra
            [
                [
                    'title' => 'Tasting Menu Experience for Influencers',
                    'description' => 'Chef Lucia Romero invites your community to a private 7-course tasting menu evening. Wine pairing included. All we ask is honest social media coverage of the experience.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'experience' => '7-course tasting menu with wine pairing'],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'tiktok' => true, 'attendee_count' => 12],
                    'categories' => ['Food & Drink', 'Lifestyle'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Malaga',
                ],
                [
                    'title' => 'Farm to Table Market Tour & Lunch',
                    'description' => 'Join our chef on a guided tour of Atarazanas market, then return to the restaurant for a collaborative lunch using what we bought. Ideal for food and photography communities.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'experience' => 'Market tour with chef'],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 20, 'video_content' => true],
                    'categories' => ['Food & Drink', 'Art & Creative'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Malaga',
                ],
                [
                    'title' => 'Private Dining Room for Special Events',
                    'description' => 'Our private dining room seats 24 guests for your community celebrations, awards dinners, or meetups. Set menu starting at a discounted community rate.',
                    'business_offer' => ['venue' => true, 'food_drink' => true, 'discount' => ['enabled' => true, 'percentage' => 15]],
                    'community_deliverables' => ['instagram_story' => true, 'attendee_count' => 24, 'review' => true],
                    'categories' => ['Food & Drink', 'Networking'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Malaga',
                ],
            ],
            // Salon de Belleza Luna
            [
                [
                    'title' => 'Pamper Day for Community Leaders',
                    'description' => 'Treat your community\'s top members to a complimentary styling session at Salon Luna. Hair, makeup, or skincare treatment included. Perfect for content creation and community rewards.',
                    'business_offer' => ['venue' => true, 'experience' => 'Full styling session'],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'tiktok' => true, 'attendee_count' => 8],
                    'categories' => ['Beauty', 'Lifestyle'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Sevilla',
                ],
                [
                    'title' => 'Beauty Workshop - Skincare Basics',
                    'description' => 'Our lead aesthetician hosts a 2-hour skincare workshop for your community. Product demos, personalized tips, and a goodie bag for every attendee.',
                    'business_offer' => ['venue' => true, 'experience' => 'Skincare workshop', 'merchandise' => 'Goodie bags'],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 20],
                    'categories' => ['Beauty', 'Wellness', 'Lifestyle'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Sevilla',
                ],
                [
                    'title' => 'Content Creation Day at Salon Luna',
                    'description' => 'Use our salon as your backdrop for a full day of content creation. Professional lighting, beautiful interiors, and complimentary blowouts for your creators.',
                    'business_offer' => ['venue' => true, 'experience' => 'Complimentary blowouts for creators'],
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'tiktok' => true, 'attendee_count' => 10, 'video_content' => true],
                    'categories' => ['Beauty', 'Art & Creative', 'Lifestyle'],
                    'venue_mode' => 'our_venue',
                    'preferred_city' => 'Sevilla',
                ],
            ],
        ];

        foreach ($businessProfiles as $index => $profileData) {
            $opportunities = $opportunitySets[$index] ?? $opportunitySets[0];

            foreach ($opportunities as $opp) {
                $cityName = $opp['preferred_city'];

                CollabOpportunity::query()->create([
                    'creator_profile_id' => $profileData['profile']->id,
                    'creator_profile_type' => UserType::Business,
                    'title' => $opp['title'],
                    'description' => $opp['description'],
                    'status' => OfferStatus::Published,
                    'business_offer' => $opp['business_offer'],
                    'community_deliverables' => $opp['community_deliverables'],
                    'categories' => $opp['categories'],
                    'availability_mode' => 'flexible',
                    'availability_start' => now()->addDays(rand(3, 14))->toDateString(),
                    'availability_end' => now()->addDays(rand(30, 60))->toDateString(),
                    'venue_mode' => $opp['venue_mode'],
                    'address' => $this->randomSpanishAddress(),
                    'preferred_city' => $cityName,
                    'offer_photo' => 'https://picsum.photos/seed/offer-'.$this->offerPhotoCounter++.'/800/600',
                    'published_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }
    }

    /**
     * Seed 2 published opportunities per community profile.
     *
     * @param  array<int, array<string, mixed>>  $communityProfiles
     * @param  array<string, string>  $cities
     */
    private function seedCommunityOpportunities(array $communityProfiles, array $cities): void
    {
        $opportunitySets = [
            // Sevilla Run Club
            [
                [
                    'title' => 'Run Club x Local Brand Collab',
                    'description' => 'Sevilla Run Club reaches 400+ active runners weekly. We are looking for a sports or nutrition brand to sponsor our Saturday long runs with hydration stations and branded merchandise.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 60, 'logo_placement' => true, 'weekly_mention' => true],
                    'categories' => ['Sports', 'Fitness'],
                    'preferred_city' => 'Sevilla',
                ],
                [
                    'title' => 'Post-Run Brunch Venue Wanted',
                    'description' => 'We need a cafe or restaurant near the river to host our Saturday post-run brunch. 20-30 runners, guaranteed weekly footfall. We offer social media coverage and a loyal, recurring audience.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_story' => true, 'attendee_count' => 30, 'recurring_attendance' => true],
                    'categories' => ['Sports', 'Food & Drink'],
                    'preferred_city' => 'Sevilla',
                ],
            ],
            // Malaga Fitness Tribe
            [
                [
                    'title' => 'Outdoor Workout Sponsor Needed',
                    'description' => 'Our beach workouts draw 50+ people every weekend. We are looking for a gym, sports brand, or wellness business to co-brand the sessions. Equipment, hydration, or venue support welcome.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 50, 'logo_placement' => true, 'video_content' => true],
                    'categories' => ['Fitness', 'Sports', 'Wellness'],
                    'preferred_city' => 'Malaga',
                ],
                [
                    'title' => 'Recovery Session Venue - Costa del Sol',
                    'description' => 'Looking for a spa or wellness center to host monthly recovery sessions for our members after intensive training cycles. Yoga, cold plunge, or massage options all work.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 25],
                    'categories' => ['Wellness', 'Fitness'],
                    'preferred_city' => 'Malaga',
                ],
            ],
            // Yoga y Bienestar Sevilla
            [
                [
                    'title' => 'Outdoor Yoga Venue Partner',
                    'description' => 'Our sunset yoga sessions need a beautiful outdoor space. A hotel rooftop, restaurant terrace, or park venue would be ideal. We bring 25+ yogis and professional instructors.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 25, 'peaceful_atmosphere' => true],
                    'categories' => ['Wellness', 'Lifestyle'],
                    'preferred_city' => 'Sevilla',
                ],
                [
                    'title' => 'Wellness Retreat Co-Host Wanted',
                    'description' => 'We are planning a weekend wellness retreat and need a rural hotel or finca partner. Accommodation, meals, and yoga space for 15 participants. Social media reach: 8K followers.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'tiktok' => true, 'attendee_count' => 15, 'blog_post' => true],
                    'categories' => ['Wellness', 'Travel', 'Lifestyle'],
                    'preferred_city' => 'Sevilla',
                ],
            ],
            // Tech Meetup BCN
            [
                [
                    'title' => 'Monthly Tech Talk Venue Needed',
                    'description' => 'Barcelona\'s biggest tech meetup needs a recurring venue for 80-100 attendees. Projector, decent sound, and space for networking afterwards. We promote to our 5K+ member base.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 80, 'logo_placement' => true, 'recurring_attendance' => true],
                    'categories' => ['Tech', 'Networking'],
                    'preferred_city' => 'Barcelona',
                ],
                [
                    'title' => 'Hackathon Sponsor & Space',
                    'description' => 'We are organizing a 48-hour hackathon and need a space with desks, Wi-Fi, and power outlets for 60 developers. Catering sponsor also welcome. Massive social media exposure.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'tiktok' => true, 'attendee_count' => 60, 'video_content' => true, 'logo_placement' => true],
                    'categories' => ['Tech', 'Networking', 'Education'],
                    'preferred_city' => 'Barcelona',
                ],
            ],
            // Foodies Andalucia
            [
                [
                    'title' => 'Foodie Tour Through Triana',
                    'description' => 'We want to partner with 3-4 bars and restaurants in Triana for a guided food tour. Each stop gets featured on our channels. 500+ engaged foodie followers. Let\'s taste together.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'tiktok' => true, 'attendee_count' => 20, 'review' => true],
                    'categories' => ['Food & Drink', 'Lifestyle'],
                    'preferred_city' => 'Sevilla',
                ],
                [
                    'title' => 'Restaurant Pop-Up Dinner Series',
                    'description' => 'Seeking restaurants to host our monthly pop-up dinner series. We curate the menu theme, you execute. Tickets sell out in hours. 30 seats, premium audience, guaranteed content.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 30, 'video_content' => true, 'review' => true],
                    'categories' => ['Food & Drink', 'Art & Creative'],
                    'preferred_city' => 'Sevilla',
                ],
            ],
            // Sevilla Dance Collective
            [
                [
                    'title' => 'Dance Jam Venue - Monthly',
                    'description' => 'Our monthly dance jams bring 40+ dancers together for freestyle sessions. We need an indoor space with good flooring and sound system. Bar or drinks service a big plus.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'tiktok' => true, 'attendee_count' => 40, 'video_content' => true],
                    'categories' => ['Dance', 'Music', 'Art & Creative'],
                    'preferred_city' => 'Sevilla',
                ],
                [
                    'title' => 'Flamenco Fusion Workshop Space',
                    'description' => 'Looking for a cultural venue or hotel to host our flamenco fusion workshops. 2-hour sessions with live guitar. Your guests are welcome to join. Cross-cultural magic guaranteed.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 20],
                    'categories' => ['Dance', 'Art & Creative', 'Lifestyle'],
                    'preferred_city' => 'Sevilla',
                ],
            ],
            // Malaga Photo Walk
            [
                [
                    'title' => 'Photo Walk Sponsor - Monthly Series',
                    'description' => 'Our photo walks attract 30+ photographers each month. We are looking for a camera shop, cafe, or creative space to sponsor the walks with prizes, meeting points, or exhibition space.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 30, 'user_generated_content' => true, 'logo_placement' => true],
                    'categories' => ['Art & Creative', 'Lifestyle'],
                    'preferred_city' => 'Malaga',
                ],
                [
                    'title' => 'Photography Exhibition Space Wanted',
                    'description' => 'We want to exhibit our community\'s best shots in a public space. A cafe, hotel lobby, or gallery that can host 20-30 framed prints for a month. Opening night event included.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 50, 'event_coverage' => true],
                    'categories' => ['Art & Creative', 'Lifestyle', 'Networking'],
                    'preferred_city' => 'Malaga',
                ],
            ],
            // GreenLife Community
            [
                [
                    'title' => 'Eco Workshop Venue Partner',
                    'description' => 'We run monthly zero-waste and sustainability workshops for 20-30 attendees. Looking for a venue in Granada that aligns with our values. Cafes, coworking spaces, or community centers welcome.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 25, 'educational_content' => true],
                    'categories' => ['Sustainability', 'Education', 'Lifestyle'],
                    'preferred_city' => 'Granada',
                ],
                [
                    'title' => 'Beach Cleanup & Brunch Partner',
                    'description' => 'Our quarterly beach cleanups draw 40+ volunteers. We need a restaurant or cafe near the coast to sponsor a post-cleanup brunch. Great visibility with an environmentally conscious audience.',
                    'business_offer' => null,
                    'community_deliverables' => ['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 40, 'video_content' => true],
                    'categories' => ['Sustainability', 'Food & Drink'],
                    'preferred_city' => 'Granada',
                ],
            ],
        ];

        foreach ($communityProfiles as $index => $profileData) {
            $opportunities = $opportunitySets[$index] ?? $opportunitySets[0];

            foreach ($opportunities as $opp) {
                CollabOpportunity::query()->create([
                    'creator_profile_id' => $profileData['profile']->id,
                    'creator_profile_type' => UserType::Community,
                    'title' => $opp['title'],
                    'description' => $opp['description'],
                    'status' => OfferStatus::Published,
                    'business_offer' => $opp['business_offer'],
                    'community_deliverables' => $opp['community_deliverables'],
                    'categories' => $opp['categories'],
                    'availability_mode' => 'flexible',
                    'availability_start' => now()->addDays(rand(3, 14))->toDateString(),
                    'availability_end' => now()->addDays(rand(30, 60))->toDateString(),
                    'venue_mode' => 'partner_venue',
                    'address' => null,
                    'preferred_city' => $opp['preferred_city'],
                    'offer_photo' => 'https://picsum.photos/seed/offer-'.$this->offerPhotoCounter++.'/800/600',
                    'published_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }
    }

    /**
     * Generate a random latitude within Spain.
     */
    private function randomLatForSpain(): float
    {
        return round(36.0 + (mt_rand(0, 40000) / 10000), 7);
    }

    /**
     * Generate a random longitude within Spain.
     */
    private function randomLngForSpain(): float
    {
        return round(-6.0 + (mt_rand(0, 50000) / 10000), 7);
    }

    /**
     * Generate a realistic Spanish street address.
     */
    private function randomSpanishAddress(): string
    {
        $streets = [
            'Calle San Fernando',
            'Avenida de la Constitucion',
            'Calle Betis',
            'Plaza Nueva',
            'Calle Sierpes',
            'Paseo de la Alameda',
            'Calle Feria',
            'Avenida de Andalucia',
            'Calle Tetuan',
            'Plaza de Espana',
            'Calle Larios',
            'Paseo del Parque',
            'Rambla de Catalunya',
            'Carrer de Valencia',
            'Calle Gran Via',
            'Calle Reyes Catolicos',
        ];

        return $streets[array_rand($streets)].', '.rand(1, 120);
    }
}
