<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Kolab;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Kolab
 */
class DiscoveryOpportunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $creatorProfile = $this->creatorProfile;
        $displayName = $creatorProfile?->isBusiness()
            ? $creatorProfile->businessProfile?->name
            : $creatorProfile?->communityProfile?->name;

        return [
            'item_type' => 'kolab',
            'id' => $this->id,
            'creator_type' => $creatorProfile?->user_type?->value,
            'intent_type' => $this->intent_type->value,
            'title' => $this->title,
            'description' => $this->description,
            'offer_headline' => $this->resolveOfferHeadline(),
            'preferred_city' => $this->preferred_city,
            'area' => $this->resolveArea(),
            'cover_photo_url' => $this->resolveCoverPhotoUrl(),
            'published_at' => $this->published_at?->toIso8601String(),
            'availability' => [
                'mode' => $this->availability_mode,
                'start' => $this->availability_start?->format('Y-m-d'),
                'end' => $this->availability_end?->format('Y-m-d'),
                'selected_time' => $this->selected_time,
                'recurring_days' => $this->recurring_days ?? [],
            ],
            'creator_profile' => [
                'id' => $creatorProfile?->id,
                'display_name' => $displayName,
                'avatar_url' => $creatorProfile?->avatar_url,
            ],
            'match_score' => (int) ($this->getAttribute('discovery_match_score') ?? 0),
            'match_breakdown' => $this->getAttribute('discovery_match_breakdown') ?? [],
            'past_events_count' => $this->getAttribute('discovery_past_events_count'),
            'active_this_month' => $this->getAttribute('discovery_active_this_month'),
            'active_this_month_label' => $this->getAttribute('discovery_active_this_month_label'),
            'business_offer' => $this->getAttribute('discovery_business_offer'),
            'community_request' => $this->getAttribute('discovery_community_request'),
            'match' => $this->getAttribute('discovery_match'),
        ];
    }

    private function resolveCoverPhotoUrl(): ?string
    {
        $media = $this->normalizeMediaCollection($this->media);

        if ($media !== []) {
            return $media[0]['url'];
        }

        foreach ($this->normalizePastEvents($this->past_events) as $event) {
            if (($event['media'][0]['url'] ?? null) !== null) {
                return $event['media'][0]['url'];
            }
        }

        return $this->creatorProfile?->avatar_url;
    }

    private function resolveOfferHeadline(): ?string
    {
        if ($this->intent_type->value === 'community_seeking') {
            return null;
        }

        if (is_string($this->offer_headline) && $this->offer_headline !== '') {
            return $this->offer_headline;
        }

        return Str::limit($this->description, 50, '');
    }

    private function resolveArea(): ?string
    {
        $area = $this->getAttribute('discovery_area');

        return is_string($area) || $area === null
            ? $area
            : $this->area;
    }

    /**
     * @return array<int, array{url: string, type: string, thumbnail_url: string|null, sort_order: int}>
     */
    private function normalizeMediaCollection(mixed $media): array
    {
        if (! is_array($media)) {
            return [];
        }

        $normalized = [];

        foreach (array_values($media) as $index => $item) {
            if (is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
                $normalized[] = [
                    'url' => $item,
                    'type' => 'image',
                    'thumbnail_url' => null,
                    'sort_order' => $index,
                ];

                continue;
            }

            if (! is_array($item) || ! isset($item['url']) || ! is_string($item['url'])) {
                continue;
            }

            $normalized[] = [
                'url' => $item['url'],
                'type' => $this->normalizeMediaType($item['type'] ?? null),
                'thumbnail_url' => isset($item['thumbnail_url']) && is_string($item['thumbnail_url'])
                    ? $item['thumbnail_url']
                    : null,
                'sort_order' => isset($item['sort_order']) && is_numeric($item['sort_order'])
                    ? (int) $item['sort_order']
                    : $index,
            ];
        }

        return array_values($normalized);
    }

    private function normalizeMediaType(mixed $type): string
    {
        if (! is_string($type) || $type === '') {
            return 'image';
        }

        return $type === 'photo' ? 'image' : $type;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizePastEvents(mixed $pastEvents): array
    {
        if (! is_array($pastEvents)) {
            return [];
        }

        $normalized = [];

        foreach ($pastEvents as $event) {
            if (! is_array($event)) {
                continue;
            }

            $photos = $event['photos'] ?? [];
            unset($event['photos']);

            $event['media'] = $this->normalizeMediaCollection($event['media'] ?? $photos);
            $normalized[] = $event;
        }

        return array_values($normalized);
    }
}
