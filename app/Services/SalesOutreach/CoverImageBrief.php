<?php

declare(strict_types=1);

namespace App\Services\SalesOutreach;

use App\Models\SalesOutreachDraft;

/**
 * Composes the cover image brief from what we actually know about the pair.
 *
 * The first version handed OpenAI whatever one-line scene the text model had
 * invented alongside the Kolab idea. It produced generic stock-looking rooms,
 * because the brief carried nothing specific: not the venue's type, not its size,
 * not the city, not who is in the room.
 *
 * This builds the brief from stored data instead — venue type and capacity, city,
 * the business's categories and what it offers, the community's type and how many
 * people it brings, and the chosen Kolab's own format. The model's creative line is
 * kept as the opening scene direction, but it is now surrounded by facts.
 *
 * **Two audiences, one picture.** The email asks a business to host a community, so
 * the cover has to make both sides want it: the business needs to see its own room
 * busy and its product moving, the community needs to see its people enjoying
 * themselves. A brief that describes only a pretty interior sells neither.
 *
 * **On logos and text.** Reference images are passed for palette, materials and the
 * feel of the place — never to be reproduced. Generative models render logos and
 * lettering as convincing gibberish, and a mangled version of a business's own logo
 * in a cold sales email is worse than no logo at all. If the real marks are wanted
 * on the image, that is a compositing step over the finished render, not something
 * to ask the model for.
 */
class CoverImageBrief
{
    /**
     * @return array{prompt: string, references: list<string>}
     */
    public function for(SalesOutreachDraft $draft): array
    {
        $business = $draft->business?->businessProfile;
        $community = $draft->community?->communityProfile;
        $idea = $draft->selectedIdea() ?? [];
        $venue = is_array($business?->primary_venue) ? $business->primary_venue : [];

        return [
            'prompt' => $this->prompt($draft, $idea, $venue),
            'references' => $this->references($business, $community, $venue),
        ];
    }

    /**
     * @param  array<string, mixed>  $idea
     * @param  array<string, mixed>  $venue
     */
    private function prompt(SalesOutreachDraft $draft, array $idea, array $venue): string
    {
        $business = $draft->business?->businessProfile;
        $community = $draft->community?->communityProfile;

        $facts = array_filter([
            'Venue type' => $venue['venue_type'] ?? $business?->business_type,
            'Venue capacity' => $venue['capacity'] ?? null,
            'City' => $business?->city_name ?? $community?->city?->name,
            'Business does' => is_array($business?->categories) ? implode(', ', $business->categories) : null,
            'Business provides for this event' => $idea['business_provides'] ?? null,
            'Community type' => $community?->community_type,
            'People expected' => $draft->expected_attendees,
            'Community brings' => $idea['community_delivers'] ?? null,
            'Event format' => $idea['format'] ?? null,
        ], fn ($value): bool => filled($value));

        $factLines = implode("\n", array_map(
            static fn (string $key, mixed $value): string => "- {$key}: {$value}",
            array_keys($facts),
            $facts,
        ));

        $scene = trim((string) ($idea['cover_image_prompt'] ?? ''));
        $sceneLine = $scene !== '' ? "Creative direction: {$scene}\n\n" : '';

        return <<<PROMPT
        A photorealistic, editorial-quality photograph of this exact event happening,
        for use as the hero image of a partnership proposal email.

        {$sceneLine}The event:
        {$factLines}

        What the picture has to do — it is shown to BOTH sides:
        - The business owner must see THEIR kind of room, full and working: their
          space busy at a time it usually is not, their product in people's hands,
          staff comfortably serving rather than overwhelmed. Match the room to the
          venue type and keep the crowd plausible for the stated capacity.
        - The community organiser must see THEIR people having a genuinely good time
          together — the activity actually happening, not an audience watching.

        Style: natural available light, candid documentary framing, shallow depth of
        field, warm and inviting, true to the city named above. Real-looking people
        of mixed ages and backgrounds, relaxed body language, mid-activity.

        Hard constraints:
        - NO text, letters, numbers, signage, menus or writing of any kind anywhere.
        - NO logos, brand marks or recognisable trademarks.
        - No recognisable faces in sharp focus; keep faces turned, blurred or partial.
        - Not a stock-photo composition: no one posing for the camera, no thumbs up,
          no centred smiling group staring at the lens.
        PROMPT;
    }

    /**
     * Their real photographs, in the order they are worth having.
     *
     * The venue photo first: it is the one that decides whether the generated room
     * looks like *their* room. Then the two logos, which carry brand colour. Capped
     * at three because each one costs upload time on an already slow call, and past
     * the third the model is being pulled in more directions than it can honour.
     *
     * @param  array<string, mixed>  $venue
     * @return list<string>
     */
    private function references(mixed $business, mixed $community, array $venue): array
    {
        $venuePhotos = is_array($venue['photos'] ?? null) ? $venue['photos'] : [];
        $offerPhotos = is_array($business?->offer_photos) ? $business->offer_photos : [];

        $candidates = [
            $venuePhotos[0] ?? null,
            $offerPhotos[0] ?? null,
            $business?->profile_photo,
            $community?->profile_photo,
        ];

        $urls = [];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            // Only real URLs: a Places photo resource name is not fetchable here.
            if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            if (! in_array($candidate, $urls, true)) {
                $urls[] = $candidate;
            }
        }

        return array_slice($urls, 0, 3);
    }
}
