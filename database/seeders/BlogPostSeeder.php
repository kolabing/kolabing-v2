<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Community-Commerce blog articles (GEO/SEO workstream, owner: Clark).
 *
 * Idempotent: upserts by slug, so re-running never duplicates and edits ship by
 * re-seeding. Bodies are HTML (the blog `show` view renders them raw inside a
 * `prose` container). Written to the founder voice + COPY-CRAFT standard, with the
 * GEO structure: every H2 is a literal question with a 40-60 word answer-first
 * lead, so answer engines can extract and cite. Internal links point up to the
 * category hub, sideways to siblings, and down to /for-businesses or
 * /for-communities.
 *
 * Run on deploy: `php artisan db:seed --class=Database\Seeders\BlogPostSeeder`.
 */
class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->posts() as $post) {
            BlogPost::query()->updateOrCreate(
                ['slug' => $post['slug']],
                $post,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function posts(): array
    {
        // Deterministic, back-dated publish times so the ordering is stable across
        // environments (the seeder must not depend on wall-clock at run time).
        $published = Carbon::parse('2026-08-18 09:00:00');

        return [
            [
                'slug' => 'what-is-community-commerce',
                'title' => 'What Is Community Commerce?',
                'description' => 'Community Commerce is when a local business grows by hosting the communities that already gather nearby, trading space and perks for a crowd that comes back. Here is what it means and why it works.',
                'author_name' => 'Daniel Martinez',
                'author_title' => 'Founder of Kolabing',
                'locale' => 'en',
                'published_at' => $published->copy()->addMinutes(0),
                'cover_image_url' => null,
                'body' => <<<'HTML'
<p>A city is full of communities that already meet. A running club on Sunday morning. A book group on a Tuesday. A language exchange that needs a table and a corner. Every one of them is looking for somewhere to gather, and most local businesses have exactly that, sitting half empty on the quiet nights.</p>
<p>Community Commerce is the practice of putting those two halves together on purpose. It is the category Kolabing is built to own, so it is worth defining plainly.</p>

<h2>What is Community Commerce?</h2>
<p>Community Commerce is when a local business grows by hosting the communities that already gather near it. The venue offers its space, a perk, or a discount; the community brings its members; both sides win because both want the visit to repeat. It turns a quiet room into a gathering, and a one-time crowd into regulars.</p>
<p>The word matters because it names a shift. For a decade, local marketing meant renting strangers' attention: an ad, a boosted post, an influencer. Community Commerce buys something different. It buys a relationship with a group of people who were always going to meet, and who now meet at your place.</p>

<h2>How is it different from advertising or influencer marketing?</h2>
<p>Advertising and influencers sell attention that ends when you stop paying. Community Commerce builds footfall that keeps arriving because the community wants to come back. An influencer is paid once and moves on. A community has its own reason to return, and it brings friends, because a group that trusts each other spreads the word inside itself.</p>
<p>This is the quiet lens behind all of it: incentives always win. A paid post has no incentive to care whether you succeed. A community that had a good night wants you to stay open, because it wants to come back next month. Align the incentives and the marketing keeps working after the invoice is paid.</p>

<h2>Why does Community Commerce work for local businesses?</h2>
<p>It works because the hardest part of filling a room is already done. The community has the people, the schedule, and the trust. You supply the place. In Barcelona, one running club turned a slow weekend morning into about thirty people and roughly four hundred euros in a single sitting. A hotel that hosted a community evening drew close to three hundred people and around four thousand euros in one night.</p>
<p>The numbers vary, but the shape does not. A well-matched gathering fills a dead shift, some of the room becomes regulars, and the cost is a fraction of what the same reach would cost in ads.</p>

<h2>How do I start with Community Commerce?</h2>
<p>Start by naming the shifts you want to fill and the communities near you that might fill them. You can do the matching by hand: find the local clubs, message the organisers, offer a date. It works, and it is slow. Kolabing exists to make that match happen in an afternoon instead of a month, by connecting your venue with nearby communities that fit your space and your city.</p>
<p>A crowd on your quietest night. A community that returns. A regular who first came for a meetup. Isn't that a better trade than renting a stranger's glance?</p>

<p>If you run a venue, see <a href="/for-businesses">how businesses use Kolabing</a>. If you organise a community, it is free, and here is <a href="/for-communities">how communities join</a>. Keep reading: <a href="/blog/how-to-get-more-footfall-without-paying-for-ads">how to get more footfall without paying for ads</a>, and <a href="/blog/how-local-businesses-partner-with-community-groups">how local businesses partner with community groups</a>.</p>
HTML,
            ],
            [
                'slug' => 'how-to-get-more-footfall-without-paying-for-ads',
                'title' => 'How to Get More Footfall Without Paying for Ads',
                'description' => 'The most reliable footfall is not an ad. It is a nearby community that already gathers, given a reason to gather at your place. Here is how it works and how to measure it.',
                'author_name' => 'Daniel Martinez',
                'author_title' => 'Founder of Kolabing',
                'locale' => 'en',
                'published_at' => $published->copy()->addMinutes(1),
                'cover_image_url' => null,
                'body' => <<<'HTML'
<p>Every ad you buy rents attention for a second. The person scrolls, the second passes, and nobody walked through your door. A community works the other way. A running club, a book group, a language exchange, these are people who already planned to be somewhere on a Tuesday evening, looking for a place to land. Give them a reason and they arrive together. Many of them come back.</p>
<p>This is the cheapest, most durable footfall a local business can build, and almost nobody does it on purpose. Here is how.</p>

<h2>What is the cheapest way to get more footfall?</h2>
<p>The cheapest reliable footfall comes from hosting a local community that already meets nearby. They bring their own crowd, so you pay nothing for attention: the group carries it in with them. One well-matched gathering can fill a dead weekday afternoon for the price of a few coffees, and the people who liked the room come back on their own.</p>
<p>The free basics still matter, and you should do them first. Claim and fill your Google Business Profile, ask happy customers for reviews, keep the window worth stopping for. These raise the floor. But they compete for strangers who happen to be searching or passing. A community brings people who were always going to gather, and simply chooses your place to do it.</p>

<h2>Do local events actually bring in customers?</h2>
<p>Yes, when the event brings its own audience. An event you promote alone competes with everything else for a stranger's attention, and most of the room stays empty. An event built around an existing group arrives with the crowd already attached, because the members show up for their own meetup, not for your flyer.</p>
<p>The numbers make the point. In Barcelona, one running club turned a slow weekend morning into about thirty people and roughly four hundred euros in a single sitting. A hotel that hosted a themed evening for a community drew close to three hundred people and around four thousand euros in one night. Even a modest collaboration tends to return several times a small monthly cost in a single event. The upside is not the average. The upside is that the floor is already profitable.</p>

<h2>How do I find community groups near me to partner with?</h2>
<p>Start with the groups that already exist around you: running and cycling clubs, book and language clubs, hobby and sports meetups, university and alumni networks, neighbourhood associations. Many live in Facebook groups, on Meetup, or on Instagram. Message the organiser, offer your space, and propose a first date. It works, and it is slow, because you are doing the matching by hand.</p>
<p>The faster way is a platform built for exactly this. Kolabing matches your venue with nearby communities by location, audience, and the kind of event you want, so instead of cold-messaging strangers you receive proposals from groups that fit. That is the whole reason it exists: to make a match that used to take a month happen in an afternoon.</p>

<h2>What should I offer a community to collaborate?</h2>
<p>Offer a fair trade, not a favour. The space for the evening, a discount for members, a round of drinks or a tasting, sometimes a small budget. In return you get a full room and a group that talks. The trade works because the incentives line up on both sides: the community wants a good experience so its members keep showing up, and you want a good experience so they come back as regulars. Nobody is renting anybody.</p>
<p>That alignment is the quiet difference from most marketing. A paid post is a transaction that ends when the post does. A collaboration is a relationship that both sides want to repeat.</p>

<h2>Is this better than influencer marketing?</h2>
<p>For a local business that needs people through the door, usually yes. An influencer sells you a burst of attention: you pay, a post goes out, and a few one-time visitors may show up before the feed moves on. A community gives you the same room plus the part influencers cannot sell, which is the return visit and the word that spreads inside a group that already trusts each other.</p>
<p>Used well, the two are not rivals. An influencer can be the spark that tells the neighbourhood you exist. A community is the fire that keeps burning after, because its members become regulars and bring friends. If you only have budget for one, buy the fire.</p>

<h2>How do I measure footfall from a collaboration?</h2>
<p>Count three things: heads at the door, redemptions of the offer you made, and the gap against a normal day. Attendance tells you the reach. A simple code or a QR on the community's offer tells you how many actually bought (your foot traffic that converted). Comparing the day against a typical one tells you what the event truly added, which is the only number that matters. A busy night means little if that night is always busy.</p>

<p>This is Community Commerce in one move. Read <a href="/blog/what-is-community-commerce">what Community Commerce is</a>, or <a href="/blog/how-local-businesses-partner-with-community-groups">how local businesses partner with community groups</a>. When you are ready, see <a href="/for-businesses">how businesses use Kolabing</a>.</p>
HTML,
            ],
            [
                'slug' => 'how-local-businesses-partner-with-community-groups',
                'title' => 'How Local Businesses Partner With Community Groups (and What to Offer)',
                'description' => 'To partner with a local community, find a group whose members are your customers, offer a fair trade of space or perks, and agree a date. Here is the playbook, and what to put on the table.',
                'author_name' => 'Daniel Martinez',
                'author_title' => 'Founder of Kolabing',
                'locale' => 'en',
                'published_at' => $published->copy()->addMinutes(2),
                'cover_image_url' => null,
                'body' => <<<'HTML'
<p>Somewhere within a few streets of your venue, a group is looking for a room. A cycling club that needs coffee after the ride. A board-games night that outgrew someone's flat. A new-parents group that wants a quiet morning corner. They have the people. You have the place. The only thing missing is the introduction.</p>
<p>Here is how local businesses make that introduction, and what to offer once you do.</p>

<h2>How do local businesses partner with community groups?</h2>
<p>Find a group whose members are the customers you want, offer a fair trade of space or perks, and agree a first date. The community brings its crowd for their own meetup; you host it and turn a quiet shift into a full room. Keep the first one small, make it good, and let the second one grow from there.</p>
<p>The order matters. Pick the group before the offer, because the right community makes a modest offer land and the wrong one wastes a generous one. A wine bar and a running club can work, but a wine bar and a book club is a warmer fit. Match first, then be generous.</p>

<h2>Where do I find community groups near me?</h2>
<p>They are already visible if you look. Search Meetup and Facebook groups for your city and your category. Scan Instagram for local clubs and the hashtags of your neighbourhood. Ask your own regulars which groups they belong to, because the best matches are often already drinking your coffee. Then message the organiser directly.</p>
<p>Doing it by hand works, and it takes time: you send messages, you wait, you chase. Kolabing removes that step by matching your venue with nearby communities that fit your space and audience, so the proposals come to you. It is the difference between hunting and being found.</p>

<h2>What should I offer a community to collaborate?</h2>
<p>Offer something the group values and you can give without pain. The usual currencies are your space for a few hours, a members' discount, a welcome drink or a tasting, a private corner, or a small budget for a bigger event. You are not writing a cheque into the dark. You are trading a quiet shift you were already paying for against a full room and word of mouth.</p>
<ul>
<li><strong>Your space.</strong> The single most valuable thing you own on a quiet night, and it costs you almost nothing to share.</li>
<li><strong>A perk for members.</strong> A discount or a free first drink gives the organiser something to announce to their group.</li>
<li><strong>An experience.</strong> A tasting, a short workshop, a themed evening. This is what turns a visit into a story people repeat.</li>
</ul>

<h2>Why do these partnerships keep working after the first event?</h2>
<p>Because the incentives are aligned, and aligned incentives outlast any campaign. The community wants the evening to be good so its members keep showing up. You want it to be good so they come back as regulars. Both sides are pulling the same direction, which is exactly what a bought ad or a paid post can never do. That is the root of Community Commerce: a relationship both sides want to repeat.</p>
<p>A full room on a slow night. A group that returns next month. A regular who first came as somebody's guest. Isn't that worth more than a stranger who saw your ad once?</p>

<h2>How do I run the first event so there is a second one?</h2>
<p>Keep it small and make it easy to repeat. Agree the date, the offer, and who tells the members, then get out of the way and let the community be itself in your room. Afterwards, count who came and how many used the offer, thank the organiser, and propose the next date while the night is still fresh. A good first event is not the goal. A standing monthly one is.</p>

<p>New to the idea? Start with <a href="/blog/what-is-community-commerce">what Community Commerce is</a>, then <a href="/blog/how-to-get-more-footfall-without-paying-for-ads">how to get more footfall without paying for ads</a>. When you are ready to be found by communities near you, see <a href="/for-businesses">how businesses use Kolabing</a>.</p>
HTML,
            ],
        ];
    }
}
