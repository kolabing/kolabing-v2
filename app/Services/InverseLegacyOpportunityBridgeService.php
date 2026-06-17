<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Enums\OfferStatus;
use App\Enums\UserType;
use App\Models\CollabOpportunity;
use App\Models\Kolab;

/**
 * Materializes canonical kolabs from legacy collab_opportunities.
 *
 * This is intentionally one-way: legacy rows are read as migration source data,
 * then applications/collaborations are re-pointed to kolab_id.
 *
 * It is idempotent: if a kolab with the opportunity's id already exists, it is a
 * no-op and returns that kolab.
 */
class InverseLegacyOpportunityBridgeService
{
    /**
     * Ensure a kolab exists for the given legacy opportunity. Returns the kolab
     * (existing or freshly created). Idempotent.
     */
    public function ensureKolabForOpportunity(CollabOpportunity $opportunity): Kolab
    {
        $existing = Kolab::query()->find($opportunity->id);

        if ($existing !== null) {
            return $existing;
        }

        $kolab = new Kolab;
        $kolab->id = $opportunity->id;
        $kolab->forceFill($this->mapFromOpportunity($opportunity));
        $kolab->save();

        // Preserve created_at/updated_at from the source opportunity so ordering
        // (e.g. "newest first" in /me/opportunities) stays faithful.
        if ($opportunity->created_at !== null || $opportunity->updated_at !== null) {
            Kolab::query()->where('id', $kolab->id)->update(array_filter([
                'created_at' => $opportunity->created_at,
                'updated_at' => $opportunity->updated_at,
            ], static fn ($v) => $v !== null));
            $kolab->refresh();
        }

        return $kolab;
    }

    /**
     * Build the kolab attribute array from a legacy collab_opportunity.
     *
     * @return array<string, mixed>
     */
    private function mapFromOpportunity(CollabOpportunity $opportunity): array
    {
        $isBusiness = $opportunity->creator_profile_type === UserType::Business;
        $intent = $this->mapIntentType($opportunity);

        // The kolabs table has NOT-NULL columns: title, description, preferred_city.
        // Legacy opportunities may have null/empty description or city; fall back to
        // safe non-empty values so the insert never violates the constraint.
        $title = $opportunity->title !== '' ? $opportunity->title : 'Untitled';
        $description = ($opportunity->description !== null && $opportunity->description !== '')
            ? $opportunity->description
            : $title;
        $preferredCity = ($opportunity->preferred_city !== null && $opportunity->preferred_city !== '')
            ? $opportunity->preferred_city
            : 'Unknown';

        $categories = is_array($opportunity->categories) ? array_values($opportunity->categories) : null;

        return [
            'creator_profile_id' => $opportunity->creator_profile_id,
            'recipient_community_id' => $opportunity->recipient_community_id,
            'intent_type' => $intent->value,
            'status' => $this->mapStatus($opportunity->status)->value,
            'title' => $title,
            'description' => $description,
            'offer_headline' => $opportunity->offer_headline,
            'base_offer' => $opportunity->base_offer,
            'negotiation_triggers' => $opportunity->negotiation_triggers,
            'preferred_city' => $preferredCity,
            'area' => null,

            // Media: forward bridge collapses kolab media to a single offer_photo.
            // We reverse by wrapping the offer_photo (when a URL) back into media[].
            'media' => $this->mapMedia($opportunity->offer_photo),

            'availability_mode' => $opportunity->availability_mode,
            'availability_start' => $opportunity->availability_start,
            'availability_end' => $opportunity->availability_end,
            'selected_time' => $opportunity->selected_time,
            'recurring_days' => $opportunity->recurring_days,

            // Offer/needs mapping (inverse of fillFromKolab):
            //   business creator: business_offer -> offering, community_deliverables -> expects
            //   community creator: business_offer -> needs,   community_deliverables -> offers_in_return
            'needs' => $isBusiness ? null : $opportunity->business_offer,
            'offers_in_return' => $isBusiness ? null : $opportunity->community_deliverables,
            'offering' => $isBusiness ? $opportunity->business_offer : null,
            'expects' => $isBusiness ? $opportunity->community_deliverables : null,

            // Categories: forward bridge sources from community_types (community_seeking)
            // or seeking_communities (other intents). Reverse accordingly.
            'community_types' => $intent === IntentType::CommunitySeeking ? $categories : null,
            'seeking_communities' => $intent === IntentType::CommunitySeeking ? null : $categories,

            // Venue: reverse of mapVenueMode. venue_address mirrors opportunity.address.
            'venue_preference' => $this->mapVenuePreference($opportunity, $intent),
            'venue_address' => $opportunity->address,

            'published_at' => $opportunity->published_at,
        ];
    }

    /**
     * Map legacy status into the closest canonical Kolab status.
     * OfferStatus::Completed has no KolabStatus equivalent; treat it as Closed.
     */
    private function mapStatus(OfferStatus $status): KolabStatus
    {
        return match ($status) {
            OfferStatus::Draft => KolabStatus::Draft,
            OfferStatus::Published => KolabStatus::Published,
            OfferStatus::Closed, OfferStatus::Completed => KolabStatus::Closed,
        };
    }

    /**
     * Derive an intent_type for the new kolab.
     *
     * The forward bridge collapses three intents into a venue_mode, so the
     * reverse is lossy. We pick the most faithful intent:
     *  - community creator  -> CommunitySeeking
     *  - business creator with venue_mode business_venue -> VenuePromotion
     *  - business creator otherwise -> ProductPromotion
     */
    private function mapIntentType(CollabOpportunity $opportunity): IntentType
    {
        if ($opportunity->creator_profile_type === UserType::Community) {
            return IntentType::CommunitySeeking;
        }

        return $opportunity->venue_mode === 'business_venue'
            ? IntentType::VenuePromotion
            : IntentType::ProductPromotion;
    }

    /**
     * Preserve legacy venue mode for CommunitySeeking kolabs (the only intent that stores a
     * venue_preference). For VenuePromotion/ProductPromotion the forward bridge
     * derives venue_mode from the intent itself, so we leave it null.
     */
    private function mapVenuePreference(CollabOpportunity $opportunity, IntentType $intent): ?string
    {
        if ($intent !== IntentType::CommunitySeeking) {
            return null;
        }

        return match ($opportunity->venue_mode) {
            'business_venue' => 'business_provides',
            'community_venue' => 'community_provides',
            'no_venue' => 'no_venue',
            default => null,
        };
    }

    /**
     * Wrap a single offer_photo URL back into the kolab media[] shape.
     *
     * @return array<int, array{url: string}>|null
     */
    private function mapMedia(?string $offerPhoto): ?array
    {
        if ($offerPhoto === null || $offerPhoto === '') {
            return null;
        }

        if (! filter_var($offerPhoto, FILTER_VALIDATE_URL)) {
            return null;
        }

        return [['url' => $offerPhoto]];
    }

    /**
     * Backfill kolabs for EVERY legacy collab_opportunity that has no backing
     * kolab, then re-point applications.kolab_id / collaborations.kolab_id to
     * collab_opportunity_id for the now-backfilled rows.
     *
     * Returns before/after counts for logging.
     *
     * @return array{
     *     opportunities_without_kolab_before: int,
     *     kolabs_created: int,
     *     opportunities_without_kolab_after: int,
     *     applications_null_kolab_before: int,
     *     applications_null_kolab_after: int,
     *     collaborations_null_kolab_before: int,
     *     collaborations_null_kolab_after: int
     * }
     */
    public function backfillAll(): array
    {
        $existingKolabIds = Kolab::query()->pluck('id')->all();

        $applicationsNullBefore = \App\Models\Application::query()->whereNull('kolab_id')->count();
        $collaborationsNullBefore = \App\Models\Collaboration::query()->whereNull('kolab_id')->count();

        $opportunitiesWithoutKolab = CollabOpportunity::query()
            ->whereNotIn('id', $existingKolabIds)
            ->get();

        $before = $opportunitiesWithoutKolab->count();
        $created = 0;

        foreach ($opportunitiesWithoutKolab as $opportunity) {
            $this->ensureKolabForOpportunity($opportunity);
            $created++;
        }

        // Re-point FKs for rows whose collab_opportunity_id now resolves to a kolab.
        foreach (['applications', 'collaborations'] as $table) {
            \Illuminate\Support\Facades\DB::table($table)
                ->whereNull('kolab_id')
                ->whereNotNull('collab_opportunity_id')
                ->whereIn('collab_opportunity_id', function ($query): void {
                    $query->select('id')->from('kolabs');
                })
                ->update(['kolab_id' => \Illuminate\Support\Facades\DB::raw('collab_opportunity_id')]);
        }

        $after = CollabOpportunity::query()
            ->whereNotIn('id', Kolab::query()->pluck('id')->all())
            ->count();

        return [
            'opportunities_without_kolab_before' => $before,
            'kolabs_created' => $created,
            'opportunities_without_kolab_after' => $after,
            'applications_null_kolab_before' => $applicationsNullBefore,
            'applications_null_kolab_after' => \App\Models\Application::query()->whereNull('kolab_id')->count(),
            'collaborations_null_kolab_before' => $collaborationsNullBefore,
            'collaborations_null_kolab_after' => \App\Models\Collaboration::query()->whereNull('kolab_id')->count(),
        ];
    }
}
