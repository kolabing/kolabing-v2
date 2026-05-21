<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Application;
use App\Enums\IntentType;
use App\Models\Kolab;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @mixin Kolab
 */
class KolabResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'intent_type' => $this->intent_type->value,
            'status' => $this->status->value,
            'title' => $this->title,
            'description' => $this->description,
            'offer_headline' => $this->resolveOfferHeadline(),
            'base_offer' => $this->resolveBaseOffer(),
            'negotiation_triggers' => $this->when(
                $this->shouldExposeNegotiationTriggers($request),
                fn () => $this->negotiation_triggers ?? []
            ),
            'preferred_city' => $this->preferred_city,
            'area' => $this->area,
            'media' => $this->normalizeMediaCollection($this->media),
            'availability_mode' => $this->availability_mode,
            'availability_start' => $this->availability_start?->format('Y-m-d'),
            'availability_end' => $this->availability_end?->format('Y-m-d'),
            'selected_time' => $this->selected_time,
            'recurring_days' => $this->recurring_days ?? [],
            'needs' => $this->needs ?? [],
            'community_types' => $this->community_types ?? [],
            'community_size' => $this->community_size,
            'typical_attendance' => $this->typical_attendance,
            'offers_in_return' => $this->offers_in_return ?? [],
            'venue_preference' => $this->resolvedVenuePreference(),
            'venue_name' => $this->venue_name,
            'venue_type' => $this->venue_type,
            'capacity' => $this->capacity,
            'venue_address' => $this->venue_address,
            'product_name' => $this->product_name,
            'product_type' => $this->product_type,
            'offering' => $this->offering ?? [],
            'seeking_communities' => $this->seeking_communities ?? [],
            'min_community_size' => $this->min_community_size,
            'expects' => $this->expects ?? [],
            'past_events' => $this->normalizePastEvents($this->past_events),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'creator_profile' => $this->whenLoaded('creatorProfile', function () {
                return new ProfileSummaryResource($this->creatorProfile);
            }),
        ];
    }

    /**
     * @return array<int, array{url: string, type: string, thumbnail_url: string|null, sort_order: int}>
     */
    private function normalizeMediaCollection(mixed $media): array
    {
        if (! is_array($media)) {
            return [];
        }

        return collect($media)
            ->map(function (mixed $item, int $index): ?array {
                if (is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
                    return [
                        'url' => $item,
                        'type' => 'image',
                        'thumbnail_url' => null,
                        'sort_order' => $index,
                    ];
                }

                if (! is_array($item) || ! isset($item['url']) || ! is_string($item['url'])) {
                    return null;
                }

                return [
                    'url' => $item['url'],
                    'type' => $this->normalizeMediaType($item['type'] ?? null),
                    'thumbnail_url' => isset($item['thumbnail_url']) && is_string($item['thumbnail_url'])
                        ? $item['thumbnail_url']
                        : null,
                    'sort_order' => isset($item['sort_order']) && is_numeric($item['sort_order'])
                        ? (int) $item['sort_order']
                        : $index,
                ];
            })
            ->filter()
            ->values()
            ->all();
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

        return collect($pastEvents)
            ->map(function (mixed $event): ?array {
                if (! is_array($event)) {
                    return null;
                }

                return [
                    ...Arr::except($event, ['photos', 'media']),
                    'media' => $this->normalizeMediaCollection($event['media'] ?? $event['photos'] ?? []),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolvedVenuePreference(): ?string
    {
        if ($this->venue_preference !== null) {
            return $this->venue_preference;
        }

        return $this->intent_type === IntentType::CommunitySeeking
            ? 'no_venue'
            : null;
    }

    private function resolveOfferHeadline(): ?string
    {
        if ($this->intent_type === IntentType::CommunitySeeking) {
            return null;
        }

        if (is_string($this->offer_headline) && $this->offer_headline !== '') {
            return $this->offer_headline;
        }

        return Str::limit($this->description, 50, '');
    }

    private function resolveBaseOffer(): ?string
    {
        if ($this->intent_type === IntentType::CommunitySeeking) {
            return null;
        }

        if (is_string($this->base_offer) && $this->base_offer !== '') {
            return $this->base_offer;
        }

        return $this->description;
    }

    private function shouldExposeNegotiationTriggers(Request $request): bool
    {
        if ($this->intent_type === IntentType::CommunitySeeking) {
            return false;
        }

        $viewer = $request->user();
        if ($viewer === null) {
            return false;
        }

        if ($viewer->id === $this->creator_profile_id) {
            return true;
        }

        return Application::query()
            ->where('collab_opportunity_id', $this->id)
            ->where('applicant_profile_id', $viewer->id)
            ->exists();
    }
}
