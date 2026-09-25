<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-off content publish, not a schema change (same pattern as the
 * Exploradores backfill): the three compliance-cleared Community Commerce
 * launch articles, authored in the fleet content pipeline (deliverable
 * 72eee826, Monika-passed 2026-09-04, publish ordered by Daniel 2026-09-16).
 * A migration is deterministic and runs on every deploy via
 * `php artisan migrate --force` — no admin-UI session, no stale-tab risk.
 *
 * Bodies live as markdown in database/content/blog/{slug}.md and are
 * converted to the trusted HTML `blog_posts.body` expects at migrate time
 * (league/commonmark via Str::markdown). The FAQ is NOT part of the body: it
 * is stored only in `blog_posts.faq`, and /blog/{slug} renders both the
 * visible FAQ section and the FAQPage JSON-LD from it, so the two cannot
 * drift (and /admin/blog edits it as structured rows). Guarded per slug: if
 * a post with the slug already exists (e.g. a maintainer published it via
 * /admin/blog first), that article is skipped, never overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->articles() as $position => $article) {
            $exists = DB::table('blog_posts')->where('slug', $article['slug'])->exists();
            if ($exists) {
                continue;
            }

            // Content file missing from the build: skip rather than fail the deploy
            // (file_get_contents on a missing path throws under Laravel's error
            // handler) and never insert an empty body.
            $path = database_path('content/blog/'.$article['slug'].'.md');
            if (! is_file($path)) {
                continue;
            }
            $markdown = (string) file_get_contents($path);

            DB::table('blog_posts')->insert([
                'id' => (string) Str::orderedUuid(),
                'slug' => $article['slug'],
                'title' => $article['title'],
                'description' => $article['description'],
                'body' => Str::markdown($markdown),
                'faq' => json_encode($article['faq'], JSON_UNESCAPED_UNICODE),
                'author_name' => 'Daniel Martinez',
                'author_title' => 'Founder of Kolabing',
                'locale' => 'en',
                // Staggered so /blog and "Keep reading" have a stable order
                // (first article in the list = newest).
                'published_at' => now()->subMinutes($position),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Only rows this migration wrote: a post a maintainer created by hand
        // under the same slug (which up() skipped) must survive a rollback.
        foreach ($this->articles() as $article) {
            DB::table('blog_posts')
                ->where('slug', $article['slug'])
                ->where('title', $article['title'])
                ->where('author_name', 'Daniel Martinez')
                ->delete();
        }
    }

    /**
     * @return list<array{slug: string, title: string, description: string, faq: list<array{question: string, answer: string}>}>
     */
    private function articles(): array
    {
        return [
            [
                'slug' => 'run-community-events-instead-of-buying-ads',
                'title' => 'Why Your Gym, Studio, or Café Should Run Community Events Instead of Buying More Ads',
                'description' => "An ad rents you a stranger's attention for a few seconds and leaves you nothing. A community event on your street brings a group that already trusts each other, and a list of names you keep. Here is the honest comparison for a local owner.",
                'faq' => [
                    ['question' => 'Are community events better than ads for a small local business?', 'answer' => 'For steady, repeat footfall, yes. Ads are best for short, reach-driven pushes with a deadline, such as a launch. For turning a local catchment into regulars, a community event brings a pre-trusting group and a list of names you keep, while an ad brings anonymous reach that disappears when you stop paying.'],
                    ['question' => 'How much does it cost to run a community event at my venue?', 'answer' => 'Usually far less than a sustained ad campaign. The community already exists and the organiser brings the people, so your main cost is the room you already have plus a small welcome. Unlike ads, reaching the same people a second time costs you almost nothing.'],
                    ['question' => 'What kinds of local businesses can run community events?', 'answer' => 'Any venue with a room and a slow slot — gyms, fitness and yoga studios, cafés, bars, restaurants, coworking spaces, bookshops. If you have space that sits underused on certain nights, a local community can fill it.'],
                    ['question' => 'Should I stop running ads if I start doing community events?', 'answer' => 'No. Keep ads for time-boxed, reach-first goals like an opening or a seasonal deadline. Shift the everyday "boost the post" budget toward events with local communities, which build the repeat customers ads rarely deliver.'],
                ],
            ],
            [
                'slug' => 'how-to-pick-which-community-to-partner-with',
                'title' => 'How to Pick Which Community to Partner With for Your First Event',
                'description' => "Your first community event only works if you pick the right group. Here is how a local business chooses which community to partner with — the fit tests that matter, the ones that don't, and how to decide when several want in.",
                'faq' => [
                    ['question' => 'What matters most when choosing a community to partner with?', 'answer' => 'Whether the group\'s members are the customers you want — people who would come back to your door on a normal day. That fit matters far more than follower count, the organiser\'s profile, or how many events the group has run.'],
                    ['question' => 'Is a bigger community a better partner?', 'answer' => 'Not usually. A smaller, tightly matched group beats a large loose one, because attendance and repeat visits come from relevance and proximity, not headline numbers. A focused group of thirty who fit your place brings more regulars than a diluted group of thousands.'],
                    ['question' => 'How do I tell if a community is a genuine fit for my venue?', 'answer' => 'Run four checks: proximity (can members reach you), overlap (would they want what you sell), activity (does the group actually meet), and rhythm (do they gather on a night you need to fill). A group that clears all four is a strong first partner.'],
                    ['question' => 'Several communities want to work with me — how do I choose?', 'answer' => 'Rank them on fit and commitment, not size. Apply the same four checks side by side and favour the group whose members map most cleanly onto your regulars and whose meeting rhythm matches your quiet slot, because that group is likeliest to want a second event.'],
                    ['question' => 'Which communities should I avoid for a first event?', 'answer' => 'The mismatched (members who would never become customers), the dormant (impressive numbers but no real gatherings), and the far-away (too distant to return between events). Any of the three gives you a pleasant night and no regulars.'],
                ],
            ],
            [
                'slug' => 'how-to-turn-a-community-event-into-repeat-customers',
                'title' => 'How to Turn a One-Off Community Event Into Repeat Customers',
                'description' => 'A single community event fills your venue for a night. The money is in what happens after. Here is how to turn that one crowd into people who come back on their own, and how to measure it.',
                'faq' => [
                    ['question' => 'Why is a repeat customer more valuable than a new one?', 'answer' => 'Repeat customers cost far less to reach and spend more when they return. Work long cited from Bain & Company found a 5% lift in retention can raise profit by 25% to 95%, and keeping a customer costs several times less than acquiring a new one.'],
                    ['question' => 'How do I turn a one-off community event into a recurring one?', 'answer' => 'Book the next date while the group is still in the room and still happy. Offer the same weekday each month, a small standing perk for members, and a spot that feels like theirs. A recurring collaboration fills a predictable slot instead of a marketing task you repeat each month.'],
                    ['question' => 'How do I get individual attendees to come back after the group leaves?', 'answer' => 'Give each person one small reason to return alone: a member discount, a follow-us offer redeemed at the door, or a loyalty stamp started the night of the event. The community brings the crowd once; a light, specific incentive turns individuals into weekday regulars.'],
                    ['question' => 'Do community events really build more loyalty than influencer marketing?', 'answer' => 'Usually yes, for a local business that needs return visits. An influencer is paid once and has no stake in whether anyone comes back. A community wants the place to stay good because it wants to keep gathering there, and that shared interest produces regulars.'],
                    ['question' => 'How do I measure repeat customers from an event?', 'answer' => 'Track how many attendees returned, how often, and the lift against a normal week in the weeks after. Return visits over the following month, not headcount on the night, are the number that tells you the event created regulars.'],
                ],
            ],
        ];
    }
};
