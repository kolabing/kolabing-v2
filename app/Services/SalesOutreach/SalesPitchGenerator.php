<?php

declare(strict_types=1);

namespace App\Services\SalesOutreach;

use App\Models\Profile;
use App\Services\OpenAi\OpenAiClient;
use RuntimeException;

/**
 * Writes the pitch: Kolab ideas for one business/community pair, then the email
 * around the chosen idea.
 *
 * Two calls rather than one, because they answer different questions and a
 * maintainer changes the answer to the second far more often than the first. Asking
 * for three complete emails up front would triple the cost of the common case, where
 * the recommended idea is the one that ships.
 *
 * **The model never touches the revenue figure.** {@see RevenueEstimator} computes it
 * and it is handed in already formatted. The one number with commercial consequences
 * is not the place for a language model's arithmetic, and a business that checks it
 * must find it reproducible.
 *
 * Everything the prompt knows comes from the two profiles as stored. Nothing is
 * invented about the business: if a venue has no capacity on file, the pitch does
 * not get to claim one.
 */
class SalesPitchGenerator
{
    public function __construct(
        private readonly OpenAiClient $client,
    ) {}

    /**
     * @return list<array{title: string, format: string, business_provides: string, community_delivers: string, why_it_works: string, cover_image_prompt: string}>
     *
     * @throws RuntimeException
     */
    public function generateIdeas(Profile $business, Profile $community, string $locale): array
    {
        $count = (int) config('sales_outreach.idea_count');

        $result = $this->client->json([
            ['role' => 'system', 'content' => $this->ideaSystemPrompt($count, $locale)],
            ['role' => 'user', 'content' => $this->pairBriefing($business, $community)],
        ]);

        $ideas = $result['ideas'] ?? null;

        if (! is_array($ideas) || $ideas === []) {
            throw new RuntimeException('The model returned no usable Kolab ideas. Nothing was saved.');
        }

        return array_values(array_map(
            fn (mixed $idea): array => [
                'title' => $this->str($idea, 'title'),
                'format' => $this->str($idea, 'format'),
                'business_provides' => $this->str($idea, 'business_provides'),
                'community_delivers' => $this->str($idea, 'community_delivers'),
                'why_it_works' => $this->str($idea, 'why_it_works'),
                'cover_image_prompt' => $this->str($idea, 'cover_image_prompt'),
            ],
            array_slice($ideas, 0, $count),
        ));
    }

    /**
     * @param  array<string, mixed>  $idea
     * @return array{subject: string, body_markdown: string}
     *
     * @throws RuntimeException
     */
    public function composeEmail(
        Profile $business,
        Profile $community,
        array $idea,
        int $attendees,
        string $formattedRevenue,
        string $formattedAvgSpend,
        string $locale,
    ): array {
        $result = $this->client->json([
            ['role' => 'system', 'content' => $this->emailSystemPrompt($locale)],
            ['role' => 'user', 'content' => implode("\n\n", [
                $this->pairBriefing($business, $community),
                "THE KOLAB TO PITCH:\n".json_encode($idea, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                implode("\n", [
                    'THE NUMBERS — use these EXACTLY as written, never recalculate or round them:',
                    "- expected attendees: {$attendees}",
                    "- average spend per attendee: {$formattedAvgSpend}",
                    "- estimated revenue for the night: {$formattedRevenue}",
                ]),
            ])],
        ]);

        $subject = $this->str($result, 'subject');
        $body = $this->str($result, 'body_markdown');

        if ($subject === '' || $body === '') {
            throw new RuntimeException('The model returned an empty subject or body. Nothing was saved.');
        }

        return ['subject' => $subject, 'body_markdown' => $body];
    }

    private function ideaSystemPrompt(int $count, string $locale): string
    {
        return <<<PROMPT
        You are a partnerships salesperson at Kolabing, a platform that matches local
        businesses with community organisers in their city.

        Propose exactly {$count} DIFFERENT collaboration ideas ("Kolabs") for the
        business and community described by the user. Each must be something this
        specific pair could actually run — use the venue, the category, the city and
        the community's own character. Vary the format meaningfully across the
        {$count}: do not return the same evening three times with different names.

        Ground every idea in the facts given. If a detail is not provided (capacity,
        opening hours, a venue at all), do not invent it — build an idea that does not
        depend on it.

        Write all prose fields in the language with code: {$locale}.

        `cover_image_prompt` is different: write it in ENGLISH regardless of {$locale},
        as a photographic brief for an image model. Describe the scene, lighting and
        mood of the event in the venue. No text, no logos, no recognisable faces, no
        brand names.

        Return JSON of exactly this shape:
        {"ideas":[{"title":"","format":"","business_provides":"","community_delivers":"","why_it_works":"","cover_image_prompt":""}]}
        PROMPT;
    }

    private function emailSystemPrompt(string $locale): string
    {
        return <<<PROMPT
        You are a partnerships salesperson at Kolabing writing a first cold outreach
        email to a business owner, in the language with code: {$locale}.

        The pitch: host this community for this specific event, and here is what a
        night like it is plausibly worth to you.

        Rules:
        - Short. Under 200 words of body. An owner reads this on a phone between shifts.
        - Open with the concrete idea, not with who we are. No "I hope this email
          finds you well", no "I'm reaching out".
        - State the numbers exactly as given. Present the revenue as an ESTIMATE and
          show the arithmetic that produced it (attendees x average spend), so the
          reader can check it. Never state it as a promise, a guarantee, or a fact.
        - Never invent statistics, case studies, named clients, or past results.
        - One clear ask at the end: are they interested. No pressure, no fake urgency,
          no deadline that does not exist.
        - Warm and direct. A person who knows the neighbourhood, not a brochure.
        - Do not write a greeting line or a sign-off — the email template adds both.
        - Do not include a call-to-action button or link; the template adds that too.

        `body_markdown` is markdown: paragraphs and at most one short bullet list.
        Return JSON of exactly this shape:
        {"subject":"","body_markdown":""}
        PROMPT;
    }

    /**
     * Everything the model is allowed to know about the pair — drawn from the stored
     * profiles, with absent fields omitted rather than filled with placeholders, so
     * the prompt never implies a fact the database does not hold.
     */
    private function pairBriefing(Profile $business, Profile $community): string
    {
        $b = $business->businessProfile;
        $c = $community->communityProfile;
        $venue = is_array($b?->primary_venue) ? $b->primary_venue : [];

        $businessFacts = array_filter([
            'name' => $b?->name,
            'category' => $b?->business_type,
            'categories' => is_array($b?->categories) ? implode(', ', $b->categories) : null,
            'city' => $b?->city_name,
            'about' => $b?->about,
            'what they can offer' => $b?->offering,
            'has a venue' => $b?->has_venue ? 'yes' : 'no',
            'venue name' => $venue['name'] ?? null,
            'venue type' => $venue['venue_type'] ?? null,
            'venue capacity' => $venue['capacity'] ?? null,
            'venue address' => $venue['formatted_address'] ?? null,
        ], fn ($v): bool => filled($v));

        $communityFacts = array_filter([
            'name' => $c?->name,
            'type' => $c?->community_type,
            'members' => $c?->community_size,
            'city' => $c?->city?->name,
            'about' => $c?->about,
            'instagram' => $c?->instagram,
        ], fn ($v): bool => filled($v));

        return "THE BUSINESS (the recipient of the email):\n"
            .$this->bullets($businessFacts)
            ."\n\nTHE COMMUNITY (who we are proposing they host):\n"
            .$this->bullets($communityFacts);
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function bullets(array $facts): string
    {
        if ($facts === []) {
            return '- (no details on file)';
        }

        return implode("\n", array_map(
            static fn (string $k, mixed $v): string => "- {$k}: {$v}",
            array_keys($facts),
            $facts,
        ));
    }

    private function str(mixed $source, string $key): string
    {
        return is_array($source) && isset($source[$key]) && is_scalar($source[$key])
            ? trim((string) $source[$key])
            : '';
    }
}
