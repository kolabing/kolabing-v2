<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Kolab;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class KolabLifecycleService
{
    public const DRAFT = 'draft';

    public const OPEN = 'open';

    public const RECEIVING = 'receiving_applicants';

    public const MATCHED = 'matched';

    public const SCHEDULED = 'scheduled';

    public const ACTIVE = 'active';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const CLOSED = 'closed';

    /**
     * All lifecycle keys in display order.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::DRAFT, self::OPEN, self::RECEIVING, self::MATCHED,
            self::SCHEDULED, self::ACTIVE, self::COMPLETED, self::CANCELLED, self::CLOSED,
        ];
    }

    public static function label(string $lifecycle): string
    {
        return match ($lifecycle) {
            self::DRAFT => 'Draft',
            self::OPEN => 'Open',
            self::RECEIVING => 'Receiving applicants',
            self::MATCHED => 'Matched',
            self::SCHEDULED => 'Scheduled',
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::CLOSED => 'Closed',
            default => ucfirst(str_replace('_', ' ', $lifecycle)),
        };
    }

    public static function badgeClass(string $lifecycle): string
    {
        return match ($lifecycle) {
            self::DRAFT, self::CLOSED => 'badge-secondary',
            self::OPEN => 'badge-info',
            self::RECEIVING => 'badge-warning',
            self::MATCHED => 'badge-primary',
            self::SCHEDULED => 'badge-light',
            self::ACTIVE => 'badge-success',
            self::COMPLETED => 'badge-dark',
            self::CANCELLED => 'badge-danger',
            default => 'badge-light',
        };
    }

    /**
     * Derive the lifecycle for one Kolab.
     *
     * @param  array{pending: int, accepted: int}  $counts
     */
    public function derive(Kolab $kolab, ?Collaboration $collaboration, array $counts): string
    {
        if ($kolab->status === KolabStatus::Draft) {
            return self::DRAFT;
        }

        if ($collaboration instanceof Collaboration) {
            return match ($collaboration->status) {
                CollaborationStatus::Scheduled => self::SCHEDULED,
                CollaborationStatus::Active => self::ACTIVE,
                CollaborationStatus::Completed => self::COMPLETED,
                CollaborationStatus::Cancelled => self::CANCELLED,
            };
        }

        if (($counts['accepted'] ?? 0) > 0) {
            return self::MATCHED;
        }

        if ($kolab->status === KolabStatus::Closed) {
            return self::CLOSED;
        }

        if (($counts['pending'] ?? 0) > 0) {
            return self::RECEIVING;
        }

        return self::OPEN;
    }

    /**
     * Hydrate per-kolab lifecycle context for a list page.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<Kolab>|Collection<int, Kolab>  $kolabs
     * @return SupportCollection<string, array{lifecycle: string, pending: int, accepted: int, collaboration: Collaboration|null}>
     */
    public function summarizeMany($kolabs): SupportCollection
    {
        $ids = collect($kolabs)->pluck('id');

        $collaborations = Collaboration::query()
            ->with(['businessProfile', 'communityProfile'])
            ->whereIn('collab_opportunity_id', $ids)
            ->get()
            ->keyBy('collab_opportunity_id');

        return collect($kolabs)->mapWithKeys(function (Kolab $kolab) use ($collaborations): array {
            $collaboration = $collaborations->get($kolab->id);
            $counts = [
                'pending' => (int) ($kolab->pending_applications_count ?? 0),
                'accepted' => (int) ($kolab->accepted_applications_count ?? 0),
            ];

            return [$kolab->id => [
                'lifecycle' => $this->derive($kolab, $collaboration, $counts),
                'pending' => $counts['pending'],
                'accepted' => $counts['accepted'],
                'collaboration' => $collaboration,
            ]];
        });
    }

    /**
     * Full lifecycle payload for a single Kolab (edit page).
     *
     * @return array{
     *     lifecycle: string,
     *     counts: array<string, int>,
     *     recent_applications: Collection<int, Application>,
     *     collaboration: Collaboration|null,
     *     reviews: Collection<int, CollaborationReview>,
     *     average_rating: float|null,
     * }
     */
    public function summarize(Kolab $kolab): array
    {
        $counts = [
            'pending' => 0, 'accepted' => 0, 'declined' => 0, 'withdrawn' => 0,
        ];

        Application::query()
            ->toBase()
            ->where('collab_opportunity_id', $kolab->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->each(function ($row) use (&$counts): void {
                $counts[(string) $row->status] = (int) $row->total;
            });

        $recent = Application::query()
            ->with('applicantProfile.businessProfile', 'applicantProfile.communityProfile')
            ->where('collab_opportunity_id', $kolab->id)
            ->latest()
            ->limit(8)
            ->get();

        $collaboration = Collaboration::query()
            ->with(['businessProfile', 'communityProfile', 'event'])
            ->where('collab_opportunity_id', $kolab->id)
            ->first();

        $reviews = $collaboration
            ? CollaborationReview::query()
                ->with('reviewerProfile')
                ->where('collaboration_id', $collaboration->id)
                ->get()
            : new Collection;

        $ratings = $reviews->pluck('rating')->filter()->values();
        $averageRating = $ratings->isNotEmpty() ? (float) round($ratings->avg(), 1) : null;

        return [
            'lifecycle' => $this->derive($kolab, $collaboration, $counts),
            'counts' => $counts,
            'recent_applications' => $recent,
            'collaboration' => $collaboration,
            'reviews' => $reviews,
            'average_rating' => $averageRating,
        ];
    }

    /**
     * Apply a lifecycle filter to a Kolab query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Kolab>  $query
     */
    public function applyFilter($query, string $lifecycle): void
    {
        $hasCollabWith = fn (CollaborationStatus $status) => function ($sub) use ($status): void {
            $sub->from('collaborations')
                ->whereColumn('collaborations.collab_opportunity_id', 'kolabs.id')
                ->where('collaborations.status', $status->value);
        };

        $hasAnyCollab = function ($sub): void {
            $sub->from('collaborations')
                ->whereColumn('collaborations.collab_opportunity_id', 'kolabs.id');
        };

        $hasApplicationWith = fn (ApplicationStatus $status) => function ($sub) use ($status): void {
            $sub->from('applications')
                ->whereColumn('applications.collab_opportunity_id', 'kolabs.id')
                ->where('applications.status', $status->value);
        };

        match ($lifecycle) {
            self::DRAFT => $query->where('kolabs.status', KolabStatus::Draft->value),
            self::CLOSED => $query->where('kolabs.status', KolabStatus::Closed->value)
                ->whereNotExists($hasAnyCollab),
            self::OPEN => $query->where('kolabs.status', KolabStatus::Published->value)
                ->whereNotExists($hasAnyCollab)
                ->whereNotExists($hasApplicationWith(ApplicationStatus::Pending))
                ->whereNotExists($hasApplicationWith(ApplicationStatus::Accepted)),
            self::RECEIVING => $query->where('kolabs.status', KolabStatus::Published->value)
                ->whereNotExists($hasAnyCollab)
                ->whereExists($hasApplicationWith(ApplicationStatus::Pending))
                ->whereNotExists($hasApplicationWith(ApplicationStatus::Accepted)),
            self::MATCHED => $query->whereNotExists($hasAnyCollab)
                ->whereExists($hasApplicationWith(ApplicationStatus::Accepted)),
            self::SCHEDULED => $query->whereExists($hasCollabWith(CollaborationStatus::Scheduled)),
            self::ACTIVE => $query->whereExists($hasCollabWith(CollaborationStatus::Active)),
            self::COMPLETED => $query->whereExists($hasCollabWith(CollaborationStatus::Completed)),
            self::CANCELLED => $query->whereExists($hasCollabWith(CollaborationStatus::Cancelled)),
            default => null,
        };
    }
}
