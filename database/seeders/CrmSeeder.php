<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CrmAccount;
use App\Models\CrmScoreWeight;
use App\Services\CrmScoreService;
use Illuminate\Database\Seeder;

/**
 * Seeds CRM scoring weights + business prospects from the two CRM sheets
 * (Kolabing_CRM pipeline + the partnership/event-history list). Idempotent.
 */
class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedWeights();
        $this->seedPipelineBusinesses();
        $this->seedPartnershipBusinesses();
        $this->seedCommunities();

        $svc = app(CrmScoreService::class);
        CrmAccount::query()->each(fn (CrmAccount $a) => $svc->recalculate($a));
    }

    private function seedWeights(): void
    {
        foreach (CrmScoreService::DEFAULTS as $type => $factors) {
            foreach ($factors as $key => [$label, $points]) {
                CrmScoreWeight::query()->updateOrCreate(
                    ['applies_to' => $type, 'key' => $key],
                    ['label' => $label, 'points' => $points],
                );
            }
        }
    }

    /** Communities CRM sheet — supply side, Health Score factors. */
    private function seedCommunities(): void
    {
        // [name, category, location, ig, ig_followers, wa_members, discord, avg_attendance,
        //  founder_name, founder_ig, status, owner, last_contact, next_action,
        //  [events_weekly,strong_attendance,active_ig,engaged_founder,good_vibes],
        //  ambassador_potential, founding_partner, notes]
        $rows = [
            ['All Barcelona', 'Social / Events', 'Barcelona', '@all.barcelona', 4200, 350, 0, 50, 'Organizer', '@all.barcelona', 'Active', 'Maria', '2026-06-01', 'Propose new event Kolab', [1, 1, 1, 1, 1], 'High', 'Yes', 'Bachata singles night × Zaatar — 50 people. Very engaged organizer. Recurring.'],
            ['The Junction', 'Wellness / Events', 'Born, Barcelona', '@thejunctionbcn', 2800, 120, 0, 400, 'Founder', '@thejunctionbcn', 'Active', 'Maria', '2026-06-03', 'Lock in next Kolab location', [1, 1, 1, 1, 1], 'High', 'Yes', 'Wellness event, 400 people in Born. Flagship community. High production quality.'],
            ['SamFit', 'Fitness / Running', 'Barceloneta', '@samfit_bcn', 1500, 80, 0, 30, 'Sam', '@samfit_bcn', 'Onboarded', 'Maria', '2026-05-28', 'First Kolab in progress', [1, 1, 1, 1, 1], 'High', 'Yes', 'Active fitness community in Barceloneta. First Kolab in progress.'],
            ['Good Ripple', 'Creative / Arts', 'Barcelona', '@goodripplebcn', 800, 60, 0, 25, 'Founder', '@goodripplebcn', 'Interested', 'Maria', '2026-05-30', 'Match with venue for artsy afterwork', [0, 1, 1, 1, 1], 'Medium', 'No', 'Artsy afterwork craft event. Seeking venue.'],
            ['Yoga Barceloneta', 'Yoga / Wellness', 'Barceloneta, Barcelona', '@yogabarceloneta', 1200, 45, 0, 15, 'Founder', '@yogabarceloneta', 'Active', 'Maria', '2026-05-15', 'Propose next yoga breakfast', [1, 1, 1, 1, 1], 'High', 'Yes', 'Yoga breakfast Kolab — 7am yoga + breakfast, 15 people. Recurring.'],
            ['Barcelona Run Club', 'Running', 'Barcelona', '@barcelonarunclub', 3500, 200, 0, 60, 'Founder', '@barcelonarunclub', 'Interested', 'Maria', '2026-06-02', 'Coffee sponsorship deal (Datirs)', [1, 1, 1, 1, 1], 'High', 'Yes', 'Coffee party with Datirs ongoing. 60+ runners/event. Major community.'],
            ['BCN Digital Nomads', 'Digital Nomads', 'Barcelona', '@bcndigitalnomads', 2100, 300, 150, 40, 'Organizer', '@bcndigitalnomads', 'Contacted', 'Maria', '2026-05-20', 'Present Kolab options', [0, 1, 1, 1, 1], 'High', 'No', 'Discord + WhatsApp. Coworking Kolabs ideal.'],
            ['Mujeres que Corren', 'Running / Women', 'Barcelona', '@mujeresquecorren', 900, 80, 0, 25, 'Founder', '@mujeresquecorren', 'Target', 'Maria', null, 'Initial outreach DM', [1, 1, 1, 1, 1], 'High', 'No', "Women's running community. Post-run brunch Kolabs ideal."],
            ['BCN Book Club', 'Book Clubs', 'Barcelona', '@bcnbookclub', 600, 45, 0, 20, 'Organizer', '@bcnbookclub', 'Target', 'Maria', null, 'Initial outreach', [0, 1, 0, 1, 1], 'Medium', 'No', 'Monthly meetings. Café/coworking venue Kolabs. Lower frequency.'],
            ['Cycling Barcelona', 'Cycling', 'Barcelona', '@cyclingbarcelona', 4500, 120, 0, 60, 'Founder', '@cyclingbarcelona', 'Target', 'Maria', null, 'Initial outreach', [1, 1, 1, 0, 1], 'Medium', 'No', 'Large cycling crew. Brewery/café Kolabs. Organic photo potential (60+ riders).'],
        ];

        $hf = ['events_weekly', 'strong_attendance', 'active_ig', 'engaged_founder', 'good_vibes'];
        foreach ($rows as [$name, $cat, $loc, $ig, $followers, $wa, $disc, $attend, $founder, $fIg, $status, $owner, $last, $next, $factors, $amb, $fp, $notes]) {
            CrmAccount::query()->updateOrCreate(
                ['type' => 'community', 'name' => $name],
                [
                    'status' => $status, 'owner' => $owner, 'instagram_handle' => $ig,
                    'next_action' => $next, 'notes' => $notes, 'last_activity_at' => $last,
                    'metrics' => array_merge(
                        array_combine($hf, array_map(static fn ($v) => (bool) $v, $factors)),
                        [
                            'category' => $cat, 'location' => $loc, 'ig_followers' => $followers,
                            'whatsapp_members' => $wa, 'discord_members' => $disc, 'avg_attendance' => $attend,
                            'founder_name' => $founder, 'founder_instagram' => $fIg,
                            'ambassador_potential' => $amb, 'founding_partner' => $fp,
                        ],
                    ),
                ],
            );
        }
    }

    /** Kolabing_CRM sheet — full pipeline + Y/N fit factors. */
    private function seedPipelineBusinesses(): void
    {
        // [name, category, ig, status, owner, last_contact, next_action,
        //  [active_ig,hosts_events,community_friendly,multiple_locations,good_fit,responsive], potential, notes]
        $rows = [
            ['Zaatar', 'Restaurant / Bar', '@zaatarbarcelona', 'Active', 'Maria', '2026-06-01', 'Plan next bachata event', [1, 1, 1, 0, 1, 1], 'Bachata nights · Singles events', 'Bachata singles night, recurring partner.'],
            ['Node Coworking', 'Coworking', '@nodecoworking', 'Active', 'Maria', '2026-06-05', 'Schedule next Kolab', [1, 1, 1, 0, 1, 1], 'Digital Nomads · Book Clubs', 'Matched with Ugo\'s Corner.'],
            ['Ugo\'s Corner', 'Café', '@ugoscorner', 'Active', 'Maria', '2026-06-05', 'New Kolab proposal', [1, 1, 1, 0, 1, 1], 'Run Clubs · Morning communities', 'Owner very engaged.'],
            ['Maui Coworking', 'Coworking', '@mauicoworking', 'Active', 'Maria', '2026-05-20', 'Propose Q3 Kolab', [1, 1, 1, 0, 1, 1], 'Digital Nomads · Remote workers', 'Strong digital nomad community.'],
            ['Coco Social House', 'Venue / Bar', '@cocosocialhouse', 'Active', 'Maria', '2026-05-25', 'Plan summer event Kolab', [1, 1, 1, 0, 1, 1], 'Social events · Nightlife', 'Great event space.'],
            ['Lilo\'s Brunch', 'Café / Brunch', '@lilosbrunch', 'Active', 'Maria', '2026-05-28', 'Propose run club breakfast', [1, 1, 1, 0, 1, 1], 'Run Clubs · Brunch groups', 'Post-run breakfast Kolabs.'],
            ['Miners', 'Bar / Venue', '@minersbarcelona', 'Active', 'Maria', '2026-05-15', 'New Kolab idea for summer', [1, 1, 1, 0, 1, 1], 'Nightlife · Social groups', 'Evening / nightlife Kolabs.'],
            ['Datirs', 'Café', '@datirscafe', 'Interested', 'Maria', '2026-06-03', 'Close the barter deal this week', [1, 0, 1, 0, 1, 1], 'Run Clubs · Digital Nomads', 'Coffee sponsorship (barter) in progress.'],
            ['Bellus Barcelona', 'Wellness Studio', '@bellusbarcelona', 'Contacted', 'Maria', '2026-06-01', 'Send Kolab examples', [1, 1, 1, 0, 1, 0], 'Yoga · Wellness groups', 'Strong Instagram.'],
            ['Pankofi', 'Café', '@pankofi', 'Contacted', 'Maria', '2026-05-28', 'Follow up — no reply yet', [1, 0, 1, 0, 1, 0], 'Foodie communities', 'Asian-inspired café. Went quiet.'],
            ['Guma Coffee', 'Specialty Café', '@gumacoffee', 'Contacted', 'Maria', '2026-05-30', 'Send proposal deck', [1, 0, 1, 0, 1, 0], 'Run Clubs · Specialty coffee', 'Run club + coffee fit.'],
            ['Tropicool', 'Bar / Venue', '@tropicool', 'Target', 'Maria', null, 'Send initial outreach DM', [1, 1, 1, 0, 1, 0], 'Summer events · Social groups', 'Tropical theme.'],
            ['Café Menssana', 'Wellness Café', '@cafemenssana', 'Target', 'Maria', null, 'Send initial outreach', [1, 0, 1, 0, 1, 0], 'Yoga · Meditation groups', 'Wellness-focused café.'],
            ['Laboyana', 'Restaurant', '@laboyana', 'Target', 'Maria', null, 'Send initial outreach', [1, 0, 1, 0, 1, 0], 'Food communities · Latin culture', 'Check community events first.'],
            ['Origo Bakery', 'Bakery / Café', '@origobakery', 'Target', 'Maria', null, 'Send initial outreach', [1, 0, 1, 0, 1, 0], 'Run Clubs · Book Clubs', 'Artisan bakery, post-run stops.'],
            ['Origin', 'Café / Studio', '@origincafe', 'Target', 'Maria', null, 'Research first, then outreach', [1, 0, 1, 0, 1, 0], 'Creative communities · Artists', 'Concept unclear — check IG.'],
        ];

        $f = ['active_ig', 'hosts_events', 'community_friendly', 'multiple_locations', 'good_fit', 'responsive'];
        foreach ($rows as [$name, $cat, $ig, $status, $owner, $last, $next, $factors, $potential, $notes]) {
            CrmAccount::query()->updateOrCreate(
                ['type' => 'business', 'name' => $name],
                [
                    'status' => $status, 'owner' => $owner, 'instagram_handle' => $ig,
                    'next_action' => $next, 'notes' => $notes, 'last_activity_at' => $last,
                    'metrics' => array_merge(
                        array_combine($f, array_map(static fn ($v) => (bool) $v, $factors)),
                        ['category' => $cat, 'potential_kolabs' => $potential],
                    ),
                ],
            );
        }
    }

    /** Partnership / event-history sheet — cold prospects (status Target). */
    private function seedPartnershipBusinesses(): void
    {
        // [name, business_type, ig, ig_language, followers, neighborhood, address, email, event_potential]
        $rows = [
            ['Kubik Barcelona', 'Coworking + Community Space', '@kubikbcn', 'Not in English', 1265, 'Gràcia', 'Carrer de Luis Antúnez 6, 08006 Barcelona', 'info@kubikbcn.com', 'Confirmed'],
            ['MOB Sants', 'Coworking + Event Space + Gallery', '@mob_bcn', 'English', 6083, 'Les Corts', 'Carrer del Taquígraf Serra 7, 08029 Barcelona', 'hello@mob-barcelona.com', 'Confirmed'],
            ['Nestra', 'Wellness Hub (Yoga/Sound Healing)', '@nestra.bcn', 'English', 8268, 'Ciutat Vella', 'Carrer del Paradís 5, 08002 Barcelona', 'info@nestracenter.com', 'Confirmed'],
            ['Amauta', 'Café', '@Amauta__coffee', 'Not in English', 7706, 'Gràcia', 'Travessera de Gràcia 90, Sarrià-Sant Gervasi', 'hola@amautacoffee.com', 'Confirmed'],
            ['Centro Sarana', 'Yoga + Wellness Studio', '@centrosarana', 'English & Spanish', 6301, 'Gràcia', 'Carrer de la Indústria 18, Gràcia', 'hello@centrosarana.com', 'Confirmed'],
            ['Hidden Coffee Roasters', 'Coffee Roastery + Café', '@hiddencoffeeroasters', 'Not in English', 36700, 'Gràcia', 'Travessera de Gràcia 101, 08006 Barcelona', 'info@hiddencoffeeroasters.com', 'Confirmed'],
            ['Lemontree Studio', 'Yoga + Fitness Studio', '@lemontreestudio_', 'English', 2016, 'Sant Martí (Poblenou)', 'Carrer de la Ciutat de Granada 20, 08005 Barcelona', 'lemontreestudiobcn@gmail.com', 'Confirmed'],
            ['Tangent Projects', 'Artist Collective + Studios', '@tangent_projects', 'English', 6955, 'L\'Hospitalet de Llobregat', 'Carrer Martí Codolar 41, 08902', 'info@tangent-projects.com', 'Confirmed'],
            ['True Roots', 'Healthy Bistro', '@trueroots.bcn', 'English', 5944, 'Sant Martí (Poblenou)', 'Carrer de Pere IV 313, 08020 Barcelona', null, 'Confirmed'],
            ['Vertical Barcelona', 'Fine Wine Bar', '@verticalbarcelona', 'Not in English', 3862, 'Eixample', 'Carrer del Rosselló 197, 08036 Barcelona', null, 'Medium'],
            ['Sin Mala Uva', 'Natural Wine Bar', '@sinmalauva.winebar', 'Not in English', 1497, 'Eixample', 'Carrer de Provença 369, 08025 Barcelona', 'sinmalauva.winebar@gmail.com', 'Medium'],
            ['Ikenocha Fusion', 'Matcha Café', '@ikenocha', 'English & Spanish', 3849, 'Eixample', 'Av. Diagonal 353, 08037 Barcelona', 'info@ikenocha.com', 'Medium'],
            ['Another Hipster Bakery & Coffee', 'Coffee + Bakery', '@another_hipster_bakery', 'Not in English', 124, 'Sant Martí', 'Carrer d\'Àvila 117, 08018 Barcelona', null, 'Medium'],
            ['Otsu', 'Cafe + Test Kitchen', '@otsu.bcn', 'English', 9577, 'Poblenou', 'Carrer Ciutat de Granada 20', 'hello@otsu.es', 'High'],
            ['Tope at The Hoxton', 'Restaurant + Rooftop Bar', '@tope_bcn', 'English and Spanish', 13600, 'Poblenou', 'Rooftop, The Hoxton Hotel', 'book.poblenou@thehox.com', 'High'],
            ['Garage Beer Co', 'Brewery + Pizzeria', '@garagepoblenou', 'Spanish', 5400, 'Poblenou', 'Passeig de Calvell 45', 'info@garagebeer.co', 'High'],
            ['Masa Vins', 'Wine Bar + Restaurant', '@masa.vins', 'Spanish and English', 33000, 'Poblenou', 'Carrer Pallars 154', 'hello@masavins.com', 'High'],
            ['Cavina', 'Wine Bar + Restaurant', '@cavina_bcn', 'English & Spanish', 2012, 'Poblenou', 'Plaça Julio González', 'restaurant@cavina.es', 'High'],
            ['Mesdvi', 'Wine Bar + Tapas', '@mesdevi', 'Spanish', 16500, 'Poblenou', 'Castanys 2 / Marià Aguiló 123', 'poblenou@restaurantemesdevi.es', 'High'],
            ['Finca Nebot', 'Catalan Cuisine', '@fincanebot', 'Spanish', 1355, 'Poblenou', 'Carrer Pujades 133', 'hola@fincanebot.com', 'High'],
            ['Metl', 'Mexican + Cocktails', '@metl.cocinamexicana', 'Spanish', 10700, 'Poblenou', 'Carrer Llull 57', 'metl.cocina@gmail.com', 'Medium'],
            ['Cacho Burgers', 'Burgers / Restaurant', '@cachoburgers', 'Spanish', 18600, 'Poblenou', 'Pujades 195', 'cachoburgers@gmail.com', 'Medium'],
            ['Casa Taos', 'Coffee + Sustainable Shop', '@casa.taos', 'Spanish', 15300, 'Poblenou', 'Poblenou (Apocapoc BCN)', 'hello@taosliving.com', 'Medium'],
            ['Sin Amor No', 'Coffee + Pastries', '@sin.amor.no', 'Spanish and English', 3664, 'Poblenou', 'Pujades 127', 'hola@sinamorno.com', 'Medium'],
            ['Nomad Coffee', 'Coffee Roastery', '@nomadcoffee', 'Spanish and English', 109000, 'Poblenou', 'Pujades 95', 'info@nomadcoffee.es', 'Low'],
            ['Little Fern Cafe', 'Brunch/Breakfast Cafe', '@littleferncafe', 'English', 15900, 'Poblenou', 'Carrer Pere IV 168', null, 'Low'],
            ['T44', 'Coffee + Pastries + Brunch', '@taulat44', 'English, Videos in Spanish', 21700, 'Poblenou', 'Carrer Taulat 44', 'grupotaulat44@gmail.com', 'Low'],
        ];

        foreach ($rows as [$name, $type, $ig, $lang, $followers, $hood, $addr, $email, $potential]) {
            CrmAccount::query()->updateOrCreate(
                ['type' => 'business', 'name' => $name],
                [
                    'status' => 'Target', 'email' => $email, 'instagram_handle' => $ig,
                    'metrics' => [
                        'category' => $type,
                        'neighborhood' => $hood,
                        'address' => $addr,
                        'followers' => $followers,
                        'instagram_language' => $lang,
                        'event_potential' => $potential,
                        // Confirmed event-history → they host events (scored factor).
                        'hosts_events' => $potential === 'Confirmed',
                    ],
                ],
            );
        }
    }
}
