<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Enums\OfferStatus;
use App\Models\CollabOpportunity;
use App\Models\Kolab;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LegacyOpportunityBridgeService
{
    public function resolveOrFail(string $opportunityId, bool $persistFromKolab = false): CollabOpportunity
    {
        $kolab = Kolab::query()
            ->with('creatorProfile')
            ->find($opportunityId);

        if ($kolab !== null) {
            return $persistFromKolab
                ? $this->persistCompatibilityOpportunity($kolab)
                : $this->makeCompatibilityOpportunity($kolab);
        }

        $opportunity = CollabOpportunity::query()->find($opportunityId);

        if ($opportunity !== null) {
            return $opportunity;
        }

        $exception = new ModelNotFoundException;
        $exception->setModel(CollabOpportunity::class, [$opportunityId]);

        throw $exception;
    }

    private function persistCompatibilityOpportunity(Kolab $kolab): CollabOpportunity
    {
        $opportunity = CollabOpportunity::query()->find($kolab->id) ?? new CollabOpportunity;

        $this->fillFromKolab($opportunity, $kolab);
        $opportunity->save();
        $opportunity->setRelation('creatorProfile', $kolab->creatorProfile);

        return $opportunity;
    }

    private function makeCompatibilityOpportunity(Kolab $kolab): CollabOpportunity
    {
        $opportunity = CollabOpportunity::query()->find($kolab->id) ?? new CollabOpportunity;

        $this->fillFromKolab($opportunity, $kolab);
        $opportunity->setRelation('creatorProfile', $kolab->creatorProfile);

        return $opportunity;
    }

    private function fillFromKolab(CollabOpportunity $opportunity, Kolab $kolab): void
    {
        $creator = $kolab->creatorProfile;

        $opportunity->id = $kolab->id;
        $opportunity->forceFill([
            'creator_profile_id' => $kolab->creator_profile_id,
            'recipient_community_id' => $kolab->recipient_community_id,
            'creator_profile_type' => $creator->user_type,
            'title' => $kolab->title,
            'description' => $kolab->description,
            'offer_headline' => $kolab->offer_headline,
            'base_offer' => $kolab->base_offer,
            'negotiation_triggers' => $kolab->negotiation_triggers,
            'status' => $this->mapStatus($kolab->status),
            'business_offer' => $creator->isBusiness() ? $kolab->offering : $kolab->needs,
            'community_deliverables' => $creator->isBusiness() ? $kolab->expects : $kolab->offers_in_return,
            'categories' => $this->extractCategories($kolab),
            'availability_mode' => $kolab->availability_mode,
            'availability_start' => $kolab->availability_start,
            'availability_end' => $kolab->availability_end,
            'selected_time' => $kolab->selected_time,
            'recurring_days' => $kolab->recurring_days,
            'venue_mode' => $this->mapVenueMode($kolab),
            'address' => $kolab->venue_address,
            'preferred_city' => $kolab->preferred_city,
            'offer_photo' => $this->resolveOfferPhoto($kolab),
            'published_at' => $kolab->published_at,
        ]);
    }

    private function mapStatus(KolabStatus $status): OfferStatus
    {
        return match ($status) {
            KolabStatus::Draft => OfferStatus::Draft,
            KolabStatus::Published => OfferStatus::Published,
            KolabStatus::Closed => OfferStatus::Closed,
        };
    }

    /**
     * @return array<int, string>|null
     */
    private function extractCategories(Kolab $kolab): ?array
    {
        if ($kolab->intent_type === IntentType::CommunitySeeking) {
            return $this->extractStringValues($kolab->community_types);
        }

        return $this->extractStringValues($kolab->seeking_communities);
    }

    private function mapVenueMode(Kolab $kolab): ?string
    {
        return match ($kolab->intent_type) {
            IntentType::VenuePromotion => 'business_venue',
            IntentType::ProductPromotion => 'no_venue',
            IntentType::CommunitySeeking => match ($kolab->venue_preference) {
                'business_provides' => 'business_venue',
                'community_provides' => 'community_venue',
                default => 'no_venue',
            },
        };
    }

    private function resolveOfferPhoto(Kolab $kolab): ?string
    {
        foreach ($this->extractMediaUrls($kolab->media) as $url) {
            return $url;
        }

        foreach ((array) $kolab->past_events as $event) {
            if (! is_array($event)) {
                continue;
            }

            foreach ($this->extractMediaUrls($event['media'] ?? $event['photos'] ?? null) as $url) {
                return $url;
            }
        }

        return $kolab->creatorProfile?->avatar_url;
    }

    /**
     * @return list<string>
     */
    private function extractMediaUrls(mixed $media): array
    {
        if (! is_array($media)) {
            return [];
        }

        $urls = [];

        foreach ($media as $item) {
            if (is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
                $urls[] = $item;

                continue;
            }

            if (is_array($item) && isset($item['url']) && is_string($item['url'])) {
                $urls[] = $item['url'];
            }
        }

        return $urls;
    }

    /**
     * @return array<int, string>
     */
    private function extractStringValues(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = $value['types'] ?? $value;
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (is_string($item) && $item !== '') {
                $normalized[] = $item;
            }
        }

        return array_values(array_unique($normalized));
    }
}
