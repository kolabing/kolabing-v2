<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OfferStatus;
use App\Enums\UserType;
use App\Models\CollabOpportunity;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OpportunityService
{
    /**
     * Browse published opportunities with filters.
     *
     * @param  array{
     *     creator_type?: string,
     *     categories?: array<string>,
     *     city?: string,
     *     venue_mode?: string,
     *     availability_mode?: string,
     *     date_from?: string,
     *     date_to?: string,
     *     search?: string,
     * }  $filters
     */
    public function browse(Profile $viewer, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = CollabOpportunity::query()
            ->where('status', OfferStatus::Published)
            ->with('creatorProfile');

        if (! isset($filters['creator_type']) || $filters['creator_type'] === '') {
            $oppositeType = $viewer->user_type === UserType::Business
                ? UserType::Community
                : UserType::Business;

            $query->where('creator_profile_type', $oppositeType);
        }

        // Exclude opportunities the viewer has already applied to
        $query->whereDoesntHave('applications', function (Builder $q) use ($viewer) {
            $q->where('applicant_profile_id', $viewer->id);
        });

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    /**
     * Get opportunities created by a profile.
     *
     * @param  array{status?: string}  $filters
     */
    public function getMyOpportunities(Profile $profile, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = CollabOpportunity::query()
            ->where('creator_profile_id', $profile->id)
            ->with('creatorProfile');

        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = OfferStatus::tryFrom($filters['status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get a single opportunity by ID.
     */
    public function findOrFail(string $id): CollabOpportunity
    {
        return CollabOpportunity::query()
            ->with(['creatorProfile', 'applications'])
            ->findOrFail($id);
    }

    /**
     * Check if a business user has reached the freemium collaboration limit.
     *
     * Unsubscribed business profiles may only accumulate 0 collaborations before
     * being required to subscribe. Once they have ≥1 collaboration, further
     * opportunity creation is blocked until they subscribe.
     */
    public function hasReachedFreemiumCollabLimit(Profile $profile): bool
    {
        if (! $profile->isBusiness()) {
            return false;
        }

        if ($profile->hasActiveSubscription()) {
            return false;
        }

        return $profile->createdCollaborations()->count() >= 1;
    }

    /**
     * Create a new opportunity in draft status.
     *
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     business_offer?: array<string, mixed>|null,
     *     community_deliverables?: array<string, mixed>|null,
     *     categories?: array<string>|null,
     *     availability_mode?: string|null,
     *     availability_start?: string|null,
     *     availability_end?: string|null,
     *     venue_mode?: string|null,
     *     address?: string|null,
     *     preferred_city?: string|null,
     *     offer_photo?: string|null,
     * }  $data
     *
     * @throws InvalidArgumentException
     */
    public function create(Profile $creator, array $data): CollabOpportunity
    {
        if ($this->hasReachedFreemiumCollabLimit($creator)) {
            throw new InvalidArgumentException(
                'A subscription is required to create more opportunities.'
            );
        }

        return CollabOpportunity::query()->create([
            'creator_profile_id' => $creator->id,
            'creator_profile_type' => $creator->user_type,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => OfferStatus::Draft,
            'business_offer' => $data['business_offer'] ?? null,
            'community_deliverables' => $data['community_deliverables'] ?? null,
            'categories' => $data['categories'] ?? null,
            'availability_mode' => $data['availability_mode'] ?? null,
            'availability_start' => $data['availability_start'] ?? null,
            'availability_end' => $data['availability_end'] ?? null,
            'venue_mode' => $data['venue_mode'] ?? null,
            'address' => $data['address'] ?? null,
            'preferred_city' => $data['preferred_city'] ?? null,
            'offer_photo' => $data['offer_photo'] ?? null,
        ]);
    }

    /**
     * Update an existing opportunity.
     *
     * @param  array{
     *     title?: string,
     *     description?: string|null,
     *     business_offer?: array<string, mixed>|null,
     *     community_deliverables?: array<string, mixed>|null,
     *     categories?: array<string>|null,
     *     availability_mode?: string|null,
     *     availability_start?: string|null,
     *     availability_end?: string|null,
     *     venue_mode?: string|null,
     *     address?: string|null,
     *     preferred_city?: string|null,
     *     offer_photo?: string|null,
     * }  $data
     *
     * @throws InvalidArgumentException
     */
    public function update(CollabOpportunity $opportunity, array $data): CollabOpportunity
    {
        if (! $this->canBeUpdated($opportunity)) {
            throw new InvalidArgumentException(
                'Opportunity can only be updated when in draft or published status.'
            );
        }

        $opportunity->update($data);
        $opportunity->refresh();

        return $opportunity;
    }

    /**
     * Delete an opportunity.
     *
     * @throws InvalidArgumentException
     */
    public function delete(CollabOpportunity $opportunity): void
    {
        if (! $this->canBeDeleted($opportunity)) {
            throw new InvalidArgumentException(
                'Opportunity can only be deleted when in draft status with no applications.'
            );
        }

        $opportunity->delete();
    }

    /**
     * Publish an opportunity.
     *
     * @throws InvalidArgumentException
     */
    public function publish(CollabOpportunity $opportunity): CollabOpportunity
    {
        if (! $opportunity->isDraft()) {
            throw new InvalidArgumentException(
                'Only draft opportunities can be published.'
            );
        }

        $creator = $opportunity->creatorProfile;

        if ($creator->isBusiness() && ! $creator->hasActiveSubscription()) {
            throw new InvalidArgumentException(
                'Business users must have an active subscription to publish opportunities.'
            );
        }

        $opportunity->update([
            'status' => OfferStatus::Published,
            'published_at' => Carbon::now(),
        ]);

        $opportunity->refresh();

        return $opportunity;
    }

    /**
     * Close an opportunity.
     *
     * @throws InvalidArgumentException
     */
    public function close(CollabOpportunity $opportunity): CollabOpportunity
    {
        if (! $opportunity->isPublished()) {
            throw new InvalidArgumentException(
                'Only published opportunities can be closed.'
            );
        }

        $opportunity->update([
            'status' => OfferStatus::Closed,
        ]);

        $opportunity->refresh();

        return $opportunity;
    }

    /**
     * Check if the opportunity can be updated.
     */
    public function canBeUpdated(CollabOpportunity $opportunity): bool
    {
        return $opportunity->isDraft() || $opportunity->isPublished();
    }

    /**
     * Check if the opportunity can be deleted.
     */
    public function canBeDeleted(CollabOpportunity $opportunity): bool
    {
        if (! $opportunity->isDraft()) {
            return false;
        }

        return $opportunity->applications()->count() === 0;
    }

    /**
     * Apply filters to the opportunity query.
     *
     * @param  Builder<CollabOpportunity>  $query
     * @param  array{
     *     creator_type?: string,
     *     categories?: array<string>,
     *     city?: string,
     *     venue_mode?: string,
     *     availability_mode?: string,
     *     date_from?: string,
     *     date_to?: string,
     *     search?: string,
     * }  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['creator_type']) && $filters['creator_type'] !== '') {
            $query->where('creator_profile_type', $filters['creator_type']);
        }

        if (isset($filters['categories']) && ! empty($filters['categories'])) {
            $query->where(function (Builder $q) use ($filters) {
                foreach ($filters['categories'] as $category) {
                    $q->orWhereJsonContains('categories', $category);
                }
            });
        }

        if (isset($filters['city']) && $filters['city'] !== '') {
            $query->where('preferred_city', $filters['city']);
        }

        if (isset($filters['venue_mode']) && $filters['venue_mode'] !== '') {
            $query->where('venue_mode', $filters['venue_mode']);
        }

        if (isset($filters['availability_mode']) && $filters['availability_mode'] !== '') {
            $query->where('availability_mode', $filters['availability_mode']);
        }

        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $query->where('availability_start', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $query->where('availability_end', '<=', $filters['date_to']);
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $searchTerm = '%'.strtolower($filters['search']).'%';
            $likeOperator = $this->getCaseInsensitiveLikeOperator();

            $query->where(function (Builder $q) use ($searchTerm, $likeOperator) {
                if ($likeOperator === 'ilike') {
                    $q->where('collab_opportunities.title', 'ilike', $searchTerm)
                        ->orWhere('collab_opportunities.description', 'ilike', $searchTerm);
                } else {
                    $q->whereRaw('LOWER(collab_opportunities.title) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(collab_opportunities.description) LIKE ?', [$searchTerm]);
                }

                $q->orWhereHas('creatorProfile.businessProfile', function (Builder $bq) use ($searchTerm, $likeOperator) {
                    if ($likeOperator === 'ilike') {
                        $bq->where('name', 'ilike', $searchTerm);
                    } else {
                        $bq->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                    }
                });

                $q->orWhereHas('creatorProfile.communityProfile', function (Builder $cq) use ($searchTerm, $likeOperator) {
                    if ($likeOperator === 'ilike') {
                        $cq->where('name', 'ilike', $searchTerm);
                    } else {
                        $cq->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                    }
                });
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
}
