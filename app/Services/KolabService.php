<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KolabService
{
    /**
     * Browse published kolabs with filters.
     *
     * @param  array{
     *     intent_type?: string,
     *     city?: string,
     *     venue_type?: string,
     *     product_type?: string,
     *     needs?: array<string>,
     *     community_types?: array<string>,
     *     search?: string,
     * }  $filters
     */
    public function browse(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Kolab::query()
            ->where('status', KolabStatus::Published)
            ->with('creatorProfile');

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    /**
     * Get kolabs created by a profile.
     *
     * @param  array{status?: string}  $filters
     */
    public function getMyKolabs(Profile $profile, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = Kolab::query()
            ->where('creator_profile_id', $profile->id)
            ->with('creatorProfile');

        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = KolabStatus::tryFrom($filters['status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Create a new kolab in draft status.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Profile $creator, array $data): Kolab
    {
        if ($data['intent_type'] === IntentType::VenuePromotion->value) {
            $data = $this->enrichVenuePromotionData($creator, $data);
        }

        return Kolab::query()->create([
            'creator_profile_id' => $creator->id,
            'intent_type' => $data['intent_type'],
            'status' => KolabStatus::Draft,
            'title' => $data['title'],
            'description' => $data['description'],
            'preferred_city' => $data['preferred_city'],
            'area' => $data['area'] ?? null,
            'media' => $data['media'] ?? null,
            'availability_mode' => $data['availability_mode'] ?? null,
            'availability_start' => $data['availability_start'] ?? null,
            'availability_end' => $data['availability_end'] ?? null,
            'selected_time' => $data['selected_time'] ?? null,
            'recurring_days' => $data['recurring_days'] ?? null,
            'needs' => $data['needs'] ?? null,
            'community_types' => $data['community_types'] ?? null,
            'community_size' => $data['community_size'] ?? null,
            'typical_attendance' => $data['typical_attendance'] ?? null,
            'offers_in_return' => $data['offers_in_return'] ?? null,
            'venue_preference' => $data['venue_preference'] ?? null,
            'venue_name' => $data['venue_name'] ?? null,
            'venue_type' => $data['venue_type'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'venue_address' => $data['venue_address'] ?? null,
            'product_name' => $data['product_name'] ?? null,
            'product_type' => $data['product_type'] ?? null,
            'offering' => $data['offering'] ?? null,
            'seeking_communities' => $data['seeking_communities'] ?? null,
            'min_community_size' => $data['min_community_size'] ?? null,
            'expects' => $data['expects'] ?? null,
            'past_events' => $data['past_events'] ?? null,
        ]);
    }

    /**
     * Update an existing kolab.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Kolab $kolab, array $data): Kolab
    {
        $intentType = $data['intent_type'] ?? $kolab->intent_type->value;

        if ($intentType === IntentType::VenuePromotion->value) {
            $data = $this->enrichVenuePromotionData($kolab->creatorProfile, $data);
        }

        $kolab->update($data);
        $kolab->refresh();

        return $kolab;
    }

    /**
     * Delete a kolab. Only draft kolabs can be deleted.
     *
     * @throws InvalidArgumentException
     */
    public function delete(Kolab $kolab): void
    {
        if (! $kolab->isDraft()) {
            throw new InvalidArgumentException(
                'Only draft kolabs can be deleted.'
            );
        }

        $kolab->delete();
    }

    /**
     * Publish a kolab. Only draft kolabs can be published.
     * Community seeking intent is free; other intents require subscription.
     *
     * @throws InvalidArgumentException
     */
    public function publish(Kolab $kolab): Kolab
    {
        if (! $kolab->isDraft()) {
            throw new InvalidArgumentException(
                'Only draft kolabs can be published.'
            );
        }

        $creator = $kolab->creatorProfile;

        if ($kolab->intent_type !== IntentType::CommunitySeeking && ! $creator->hasActiveSubscription()) {
            throw new InvalidArgumentException(
                'A subscription is required to publish this type of kolab.'
            );
        }

        $kolab->update([
            'status' => KolabStatus::Published,
            'published_at' => Carbon::now(),
        ]);

        $kolab->refresh();

        return $kolab;
    }

    /**
     * Close a published kolab.
     *
     * @throws InvalidArgumentException
     */
    public function close(Kolab $kolab): Kolab
    {
        if (! $kolab->isPublished()) {
            throw new InvalidArgumentException(
                'Only published kolabs can be closed.'
            );
        }

        $kolab->update([
            'status' => KolabStatus::Closed,
        ]);

        $kolab->refresh();

        return $kolab;
    }

    /**
     * Apply filters to the kolab query.
     *
     * @param  Builder<Kolab>  $query
     * @param  array{
     *     intent_type?: string,
     *     city?: string,
     *     venue_type?: string,
     *     product_type?: string,
     *     needs?: array<string>,
     *     community_types?: array<string>,
     *     search?: string,
     * }  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['intent_type']) && $filters['intent_type'] !== '') {
            $intentType = IntentType::tryFrom($filters['intent_type']);
            if ($intentType !== null) {
                $query->where('intent_type', $intentType);
            }
        }

        if (isset($filters['city']) && $filters['city'] !== '') {
            $query->where('preferred_city', $filters['city']);
        }

        if (isset($filters['venue_type']) && $filters['venue_type'] !== '') {
            $query->where('venue_type', $filters['venue_type']);
        }

        if (isset($filters['product_type']) && $filters['product_type'] !== '') {
            $query->where('product_type', $filters['product_type']);
        }

        if (isset($filters['needs']) && ! empty($filters['needs'])) {
            $query->where(function (Builder $q) use ($filters) {
                foreach ($filters['needs'] as $need) {
                    $q->orWhereJsonContains('needs', $need);
                }
            });
        }

        if (isset($filters['community_types']) && ! empty($filters['community_types'])) {
            $query->where(function (Builder $q) use ($filters) {
                foreach ($filters['community_types'] as $communityType) {
                    $q->orWhereJsonContains('community_types', $communityType);
                }
            });
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $searchTerm = '%'.strtolower($filters['search']).'%';
            $likeOperator = $this->getCaseInsensitiveLikeOperator();

            $query->where(function (Builder $q) use ($searchTerm, $likeOperator) {
                if ($likeOperator === 'ilike') {
                    $q->where('kolabs.title', 'ilike', $searchTerm)
                        ->orWhere('kolabs.description', 'ilike', $searchTerm);
                } else {
                    $q->whereRaw('LOWER(kolabs.title) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(kolabs.description) LIKE ?', [$searchTerm]);
                }
            });
        }
    }

    /**
     * Get the case-insensitive LIKE operator based on database driver.
     */
    private function getCaseInsensitiveLikeOperator(): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enrichVenuePromotionData(Profile $creator, array $data): array
    {
        $creator->loadMissing('businessProfile');

        $primaryVenue = $creator->businessProfile?->primary_venue;

        if (! is_array($primaryVenue) || empty($primaryVenue)) {
            throw new InvalidArgumentException(
                'A primary venue profile is required before creating a venue promotion kolab.'
            );
        }

        $data['preferred_city'] = $data['preferred_city'] ?? $primaryVenue['city'] ?? null;
        $data['venue_name'] = $primaryVenue['name'] ?? null;
        $data['venue_type'] = $primaryVenue['venue_type'] ?? null;
        $data['capacity'] = $primaryVenue['capacity'] ?? null;
        $data['venue_address'] = $primaryVenue['formatted_address'] ?? null;

        return $data;
    }
}
