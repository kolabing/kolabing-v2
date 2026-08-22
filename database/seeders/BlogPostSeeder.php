<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Community-led-footfall blog articles (GEO/SEO workstream, owner: Clark).
 *
 * Idempotent: upserts by slug, so re-running never duplicates and edits ship by
 * re-seeding. Bodies are HTML (the blog `show` view renders them raw inside a
 * `prose` container). Written to the founder voice + COPY-CRAFT standard, with the
 * GEO structure: every H2 is a literal question with a 40-60 word answer-first
 * lead, so answer engines can extract and cite. Internal links point up to the
 * category hub, sideways to siblings, and down to /for-businesses or
 * /for-communities.
 *
 * Category term (Daniel, 2026-08-18): "community-led footfall". "Community
 * Commerce" was dropped as a headline/SEO term (TikTok owns it for creator
 * commerce, and buyers search the outcome, not a category). SEO runs on the
 * searched outcome terms (footfall, event ideas, partner with local groups);
 * "community-led footfall" is the coined category we own.
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
                'slug' => 'what-is-community-led-footfall',
                'title' => 'What Is Community-Led Footfall?',
                'description' => 'Community-led footfall is the footfall a local business earns by hosting the communities that already gather nearby, trading space and perks for a crowd that comes back. Here is what it means and why it works.',
                'author_name' => 'Daniel Martinez',
                'author_title' => 'Founder of Kolabing',
                'locale' => 'en',
                'published_at' => $published->copy()->addMinutes(0),
                'cover_image_url' => null,
                'body' => <<<'HTML'
<p>A city is full of communities that already meet. A running club on Sunday morning. A book group on a Tuesday. A language exchange that needs a table and a corner. Every one of them is looking for somewhere to gather, and most local businesses have exactly that, sitting half empty on the quiet nights.</p>
<p>Put those two halves together on purpose and you get the most dependable footfall a local business can build. We call it community-led footfall, and it is the idea Kolabing is built around, so it is worth defining plainly.</p>

<h2>What is community-led footfall?</h2>
<p>Community-led footfall is the footfall a local business earns by hosting the communities that already gather near it. The venue offers its space, a perk, or a discount; the community brings its members; both sides win because both want the visit to repeat. It turns a quiet room into a gathering, and a one-time crowd into regulars.</p>
<p>The name is deliberate. Most local marketing rents strangers' attention: an ad, a boosted post, an influencer. Community-led footfall is different. The people were always going to meet. You give them a reason to meet at your place, and the crowd leads itself in.</p>

<h2>How is it different from advertising or influencer marketing?</h2>
<p>Advertising and influencers sell attention that ends when you stop paying. Community-led footfall keeps arriving because the community wants to come back. An influencer is paid once and moves on. A community has its own reason to return, and it brings friends, because a group that trusts each other spreads the word inside itself.</p>
<p>This is the lens behind all of it: incentives always win. A paid post has no incentive to care whether you succeed. A community that had a good night wants you to stay open, because it wants to come back next month. Align the incentives and the marketing keeps working after the invoice is paid.</p>

<h2>Why does community-led footfall work for local businesses?</h2>
<p>Because the hardest part of filling a room is already done. The community has the people, the schedule, and the trust. You supply the place. In Barcelona, one running club turned a slow weekend morning into about thirty people and roughly four hundred euros in a single sitting. A hotel that hosted a community evening drew close to three hundred people and around four thousand euros in one night.</p>
<p>The numbers vary, but the shape does not. A well-matched gathering fills a dead shift, some of the room becomes regulars, and the cost is a fraction of what the same reach would cost in ads.</p>

<h2>How do I start?</h2>
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

<p>This is community-led footfall in one move. Read <a href="/blog/what-is-community-led-footfall">what community-led footfall is</a>, or <a href="/blog/how-local-businesses-partner-with-community-groups">how local businesses partner with community groups</a>. When you are ready, see <a href="/for-businesses">how businesses use Kolabing</a>.</p>
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
<p>Because the incentives are aligned, and aligned incentives outlast any campaign. The community wants the evening to be good so its members keep showing up. You want it to be good so they come back as regulars. Both sides are pulling the same direction, which is exactly what a bought ad or a paid post can never do. That is the root of community-led footfall: a relationship both sides want to repeat.</p>
<p>A full room on a slow night. A group that returns next month. A regular who first came as somebody's guest. Isn't that worth more than a stranger who saw your ad once?</p>

<h2>How do I run the first event so there is a second one?</h2>
<p>Keep it small and make it easy to repeat. Agree the date, the offer, and who tells the members, then get out of the way and let the community be itself in your room. Afterwards, count who came and how many used the offer, thank the organiser, and propose the next date while the night is still fresh. A good first event is not the goal. A standing monthly one is.</p>

<p>New to the idea? Start with <a href="/blog/what-is-community-led-footfall">what community-led footfall is</a>, then <a href="/blog/how-to-get-more-footfall-without-paying-for-ads">how to get more footfall without paying for ads</a>. When you are ready to be found by communities near you, see <a href="/for-businesses">how businesses use Kolabing</a>.</p>
HTML,
            ],
            [
                'slug' => 'is-influencer-marketing-worth-it-for-a-local-business',
                'title' => 'Is Influencer Marketing Worth It for a Local Business?',
                'description' => 'For a local venue that lives on regulars, influencer marketing is usually a spark, not an engine. Here is when it is worth it, why the visits rarely repeat, and what brings footfall that comes back.',
                'author_name' => 'Daniel Martinez',
                'author_title' => 'Founder of Kolabing',
                'locale' => 'en',
                'published_at' => $published->copy()->addMinutes(3),
                'cover_image_url' => null,
                'body' => <<<'HTML'
<p>An influencer posts about your cafe. For a day, the likes roll in and a few new faces appear. Then the feed moves on, the faces do not come back, and you are left with a receipt and a quiet Tuesday again. Every local owner has felt some version of this. So the question is fair: is it worth it?</p>
<p>Here is an honest answer, and what tends to work better for a business that needs people through the door, not just eyes on a screen.</p>

<h2>Is influencer marketing worth it for a local business?</h2>
<p>Usually not on its own. An influencer sells a burst of attention: you pay, a post goes out, and a handful of one-time visitors may show up before the algorithm moves on. For a national brand chasing awareness that can pay off. For a local venue that lives on regulars and repeat visits, the maths rarely closes, because attention is not the same as footfall that returns.</p>
<p>That does not make it worthless. It makes it a spark, not an engine. Used as one part of a plan it can help. Used as the plan, it burns money.</p>

<h2>Why don't influencer posts bring repeat customers?</h2>
<p>Because the incentive ends when the post does. An influencer is paid once to say a nice thing, and has no reason to care whether anyone comes back. Their followers came for the influencer, not for you, so when the content stops, the reason to visit stops with it. You rented a crowd that was never yours.</p>
<p>This is the quiet law under all local marketing: incentives always win. Anyone paid once to send people your way will send them once. To get repeat visits, you need people who have their own reason to return.</p>

<h2>What works better than influencers for a cafe or bar?</h2>
<p>A nearby community that already gathers. A running club, a book group, a language exchange, these are people who meet every week and want somewhere to land. Host them and you get the same room an influencer promised, plus the part they cannot sell: the return visit, because the group comes back next month, and the word that spreads inside a circle that already trusts each other.</p>
<p>In Barcelona a single running club filled a slow weekend morning with about thirty people and roughly four hundred euros, and many of them came back on their own. That is footfall that compounds, not footfall you rent.</p>

<h2>When does an influencer actually make sense?</h2>
<p>As the spark, not the fire. If a well-matched local creator can tell the neighbourhood you exist, or capture a great night so it reaches more people, that is a fair use of the budget. The test is simple: are you buying awareness for something that will keep working after the post, or buying the post itself? Awareness that feeds a community event compounds. A post that feeds nothing does not.</p>

<h2>How do I get the word of mouth influencers promise, without paying for it?</h2>
<p>Let a community do it, because word of mouth is what a community is. Give a local group a good night in your venue and its members tell each other, unprompted, because they trust each other more than they trust an ad. Kolabing matches your venue with nearby communities that fit, so the recommendation you were trying to buy happens for free, inside a group that meant it.</p>
<p>An influencer for the spark. A community for the fire. Which one do you want your Tuesdays to run on?</p>

<p>Read <a href="/blog/what-is-community-led-footfall">what community-led footfall is</a>, or <a href="/blog/how-to-get-more-footfall-without-paying-for-ads">how to get more footfall without paying for ads</a>. When you are ready, see <a href="/for-businesses">how businesses use Kolabing</a>.</p>
HTML,
            ],
            [
                'slug' => 'how-your-community-can-get-a-free-venue-for-its-next-event',
                'title' => 'How Your Community Can Get a Free Venue (and Perks) for Its Next Event',
                'description' => 'Local venues will host your community event for free, because your members fill a room that would sit empty. Here is how to get a free venue, and what the business wants in return.',
                'author_name' => 'Daniel Martinez',
                'author_title' => 'Founder of Kolabing',
                'locale' => 'en',
                'published_at' => $published->copy()->addMinutes(4),
                'cover_image_url' => null,
                'body' => <<<'HTML'
<p>Your community needs a room. Somewhere to run the monthly meetup, launch the season, hold the workshop, without paying a fee that eats the whole budget. The good news is that plenty of local venues will give you that room for nothing, and thank you for it. You just have to understand why.</p>
<p>Here is how communities get a free venue, and what the venue wants back.</p>

<h2>How can my community get a free venue for an event?</h2>
<p>Offer a venue the one thing it is short of on a quiet night: people. A cafe, bar, or gym with an empty Tuesday will host your group for free, because your members fill a room that was going to sit empty anyway. You are not asking for a favour. You are bringing the crowd a business would otherwise pay to reach.</p>
<p>Approach it as a fair trade. Tell the venue how many people you bring, when, and how often, and let them picture the quiet shift you would fill. A full room on a slow night is worth far more to them than the cost of the space.</p>

<h2>Why would a business host my group for free?</h2>
<p>Because your members are the customers they are trying to reach, and you deliver them at no cost. Every ad a venue buys rents a stranger's attention for a second. Your community walks in as a group, spends while it is there, and some of it comes back as regulars. For the venue that is the cheapest, warmest marketing there is, so hosting you is not charity, it is a good deal.</p>
<p>The incentives line up, which is why it lasts. You want a good room for your members. They want your members to become regulars. Both sides win when the night goes well, so both sides want to do it again.</p>

<h2>What do venues want from a community in return?</h2>
<p>Usually just the crowd, and a little visibility. Bring your members, spend a normal amount while you are there, and tag the place in a post or a story so the night reaches a few more people. Some venues will ask for a minimum spend or a set number of attendees. That is fair. You are trading your reliability for their room.</p>
<p>Be the group that is easy to host: show up when you said, treat the space well, and the same venue will want you back every month. A reputation as a good guest is the most valuable thing a community can carry.</p>

<h2>What kind of communities can do this?</h2>
<p>Almost any that meets in person with a handful of people. Running and cycling clubs, book and language groups, board-game and hobby nights, new-parent meetups, university and alumni circles, expat and neighbourhood groups. You do not need thousands of followers. You need a real group that shows up, because reliability is worth more to a venue than raw size.</p>

<h2>How do I find venues that want to host my community?</h2>
<p>Start with the places your members already like, and ask. Many will say yes to a quiet night. Doing it by hand works, and it takes time and a few awkward messages. Kolabing does the matching for you: it connects your community with nearby venues looking for exactly the kind of crowd you bring, and on Kolabing it is free for communities, always.</p>
<p>A room for your next event. A perk for your members. A venue that wants you back. Isn't that worth a message?</p>

<p>See <a href="/for-communities">how communities join Kolabing</a>, or read <a href="/blog/what-is-community-led-footfall">what community-led footfall is</a> to understand why venues want you there.</p>
HTML,
            ],
        ];
    }
}
