<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Enums\PartnerStatusTier;
use App\Models\BusinessPartnerStatus;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Profile;

class BusinessPartnerStatusService
{
    /**
     * Recalculate and persist a business's partner status from its collaboration history.
     * Returns the resulting status; callers can compare against the profile's previous
     * status (via statusFor()) before calling this to detect an upgrade worth announcing.
     */
    public function recalculate(Profile $businessProfile): PartnerStatusTier
    {
        $completedCollaborations = $this->completedCollaborationsQuery($businessProfile)->get();
        $completedKolabsCount = $completedCollaborations->count();
        $repeatPartnerCount = $this->countRepeatPartners($businessProfile, $completedCollaborations);

        $reviews = CollaborationReview::query()
            ->where('reviewed_profile_id', $businessProfile->id)
            ->get();
        $reviewCount = $reviews->count();
        $averageRating = $reviewCount > 0 ? round((float) $reviews->avg('rating'), 2) : null;

        $status = $this->resolveTier($completedKolabsCount, $reviewCount, $averageRating, $repeatPartnerCount);

        BusinessPartnerStatus::query()->updateOrCreate(
            ['profile_id' => $businessProfile->id],
            [
                'status' => $status,
                'completed_kolabs_count' => $completedKolabsCount,
                'review_count' => $reviewCount,
                'repeat_partner_count' => $repeatPartnerCount,
                'average_rating' => $averageRating,
                'recalculated_at' => now(),
            ]
        );

        return $status;
    }

    /**
     * Cheap read for API/resource use. Falls back to New Partner without
     * recomputing if no status row exists yet.
     */
    public function statusFor(Profile $businessProfile): PartnerStatusTier
    {
        return $businessProfile->businessPartnerStatus?->status ?? PartnerStatusTier::NewPartner;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Collaboration>
     */
    private function completedCollaborationsQuery(Profile $businessProfile): \Illuminate\Database\Eloquent\Builder
    {
        return Collaboration::query()
            ->where('status', CollaborationStatus::Completed)
            ->where(function ($query) use ($businessProfile): void {
                $query->where('creator_profile_id', $businessProfile->id)
                    ->orWhere('applicant_profile_id', $businessProfile->id);
            });
    }

    /**
     * A repeat partner is a counterpart profile the business has completed
     * more than one Kolab with.
     *
     * @param  \Illuminate\Support\Collection<int, Collaboration>  $completedCollaborations
     */
    private function countRepeatPartners(Profile $businessProfile, \Illuminate\Support\Collection $completedCollaborations): int
    {
        return $completedCollaborations
            ->map(fn (Collaboration $collaboration): string => $collaboration->creator_profile_id === $businessProfile->id
                ? $collaboration->applicant_profile_id
                : $collaboration->creator_profile_id)
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->count();
    }

    private function resolveTier(
        int $completedKolabsCount,
        int $reviewCount,
        ?float $averageRating,
        int $repeatPartnerCount,
    ): PartnerStatusTier {
        $tiers = config('gamification_business.tiers');

        if ($this->meetsThresholds($tiers['community_favourite'], $completedKolabsCount, $reviewCount, $averageRating, $repeatPartnerCount)) {
            return PartnerStatusTier::CommunityFavourite;
        }

        if ($this->meetsThresholds($tiers['trusted_partner'], $completedKolabsCount, $reviewCount, $averageRating, $repeatPartnerCount)) {
            return PartnerStatusTier::TrustedPartner;
        }

        if ($this->meetsThresholds($tiers['active_partner'], $completedKolabsCount, $reviewCount, $averageRating, $repeatPartnerCount)) {
            return PartnerStatusTier::ActivePartner;
        }

        return PartnerStatusTier::NewPartner;
    }

    /**
     * @param  array{min_completed_kolabs?: int, min_reviews?: int, min_average_rating?: float, min_repeat_partners?: int}  $thresholds
     */
    private function meetsThresholds(
        array $thresholds,
        int $completedKolabsCount,
        int $reviewCount,
        ?float $averageRating,
        int $repeatPartnerCount,
    ): bool {
        if ($completedKolabsCount < ($thresholds['min_completed_kolabs'] ?? 0)) {
            return false;
        }

        if ($reviewCount < ($thresholds['min_reviews'] ?? 0)) {
            return false;
        }

        if (isset($thresholds['min_average_rating']) && ($averageRating ?? 0.0) < $thresholds['min_average_rating']) {
            return false;
        }

        if ($repeatPartnerCount < ($thresholds['min_repeat_partners'] ?? 0)) {
            return false;
        }

        return true;
    }
}
